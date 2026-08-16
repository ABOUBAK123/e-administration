<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowExecution;
use App\Models\WorkflowTemplate;
use App\Models\SignatureRequest;
use App\Models\Notification;
use App\Models\Document;
use App\Models\User;
use App\Services\NotificationService;
use App\Traits\GuardsPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    use GuardsPermissions;

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function visibleWorkflowIdsForUser(string $userId)
    {
        $notifiedWorkflowIds = Notification::where('recipient_id', $userId)
            ->whereNotNull('workflow_id')
            ->pluck('workflow_id');

        return Workflow::query()
            ->where('created_by', $userId)
            ->orWhereHas('steps', fn($q) => $q->where('assignee_id', $userId))
            ->orWhereIn('id', $notifiedWorkflowIds)
            ->pluck('id');
    }

    private function ensureWorkflowVisibility(Workflow $workflow): void
    {
        $userId = Auth::id();
        abort_unless($userId, 403);

        $isCreator = $workflow->created_by === $userId;
        $isAssignee = $workflow->steps()->where('assignee_id', $userId)->exists();
        $isNotified = Notification::where('recipient_id', $userId)
            ->where('workflow_id', $workflow->id)
            ->exists();

        abort_unless($isCreator || $isAssignee || $isNotified, 403);
    }

    private function getSignatureStepIndex(Workflow $workflow, WorkflowStep $step): ?int
    {
        if (!$step->requires_signature) {
            return null;
        }

        $signatureSteps = $workflow->steps()
            ->where('requires_signature', true)
            ->orderBy('order')
            ->get(['id']);

        $index = $signatureSteps->search(fn($signatureStep) => $signatureStep->id === $step->id);

        return $index === false ? null : $index;
    }

    private function getDocumentZoneForSignatureStep(array $docZones, string $docId, ?int $signatureStepIndex): array
    {
        $zones = $docZones[$docId] ?? [];

        if (!is_array($zones)) {
            return [];
        }

        if (array_key_exists('page', $zones)) {
            return $zones;
        }

        if ($signatureStepIndex === null) {
            return [];
        }

        $zone = $zones[$signatureStepIndex] ?? [];

        return is_array($zone) ? $zone : [];
    }

    private function createSignatureRequestsForStep(Workflow $workflow, WorkflowStep $step, array $docZones): void
    {
        if (!$step->assignee_id || !$step->requires_signature) {
            return;
        }

        $docsToSign = $workflow->docs_to_sign ?? [];
        $signatureStepIndex = $this->getSignatureStepIndex($workflow, $step);

        foreach ($docsToSign as $docId) {
            $exists = SignatureRequest::where('document_id', $docId)
                ->where('requested_to', $step->assignee_id)
                ->where('status', 'pending')
                ->exists();

            if ($exists) {
                continue;
            }

            $zone = $this->getDocumentZoneForSignatureStep($docZones, (string) $docId, $signatureStepIndex);

            SignatureRequest::create([
                'id'           => Str::uuid(),
                'document_id'  => $docId,
                'requested_by' => Auth::id(),
                'requested_to' => $step->assignee_id,
                'message'      => "Workflow: {$workflow->name} — Étape {$step->order}: " . ($step->name ?? 'Action requise'),
                'status'       => 'pending',
                'zone_page'    => $zone['page']   ?? null,
                'zone_x'       => $zone['x']      ?? null,
                'zone_y'       => $zone['y']      ?? null,
                'zone_width'   => $zone['width']  ?? $zone['w'] ?? null,
                'zone_height'  => $zone['height'] ?? $zone['h'] ?? null,
                'zone_label'   => $zone['label']  ?? null,
            ]);
        }
    }

    private function formatExecution(WorkflowExecution $e): array
    {
        return [
            'id'               => $e->id,
            'workflow_id'      => $e->workflow_id,
            'status'           => $e->status,
            'current_step'     => $e->current_step,
            'document_id'      => $e->document_id,
            'signed_file_path' => $e->document?->signed_file_path,
        ];
    }

    private function formatWorkflow(Workflow $wf): array
    {
        return [
            'id'          => $wf->id,
            'name'        => $wf->name,
            'description' => $wf->description,
            'status'      => $wf->status,
            'created_by'  => $wf->created_by,
            'docs_to_sign'   => $wf->docs_to_sign ?? [],
            'attached_docs'  => $wf->attached_docs ?? [],
            'notification_config' => null,
            'updated_at'  => $wf->updated_at?->toISOString(),
            'steps'       => ($wf->steps ?? collect())->map(fn($s) => [
                'id'                 => $s->id,
                'name'               => $s->name,
                'type'               => $s->type,
                'order'              => $s->order,
                'assignee_id'        => $s->assignee_id,
                'requires_signature' => (bool)$s->requires_signature,
                'assignee'           => $s->assignee
                    ? ['id' => $s->assignee->id, 'name' => $s->assignee->name, 'email' => $s->assignee->email]
                    : null,
            ])->values(),
            'executions' => ($wf->executions ?? collect())->map(fn($e) => [
                'id'           => $e->id,
                'workflow_id'  => $e->workflow_id,
                'status'       => $e->status,
                'current_step' => $e->current_step,
                'document_id'  => $e->document_id,
                'signed_file_path' => $e->document?->signed_file_path,
            ])->values(),
        ];
    }

    private function resolveCurrentAdministrationId(): ?string
    {
        $userId = (string) Auth::id();
        if ($userId === '') {
            return null;
        }

        // Source prioritaire: affectation active de l'utilisateur.
        $adminIdFromAssignment = DB::table('user_direction_assignments')
            ->where('user_id', $userId)
            ->whereNotNull('direction_scope_id')
            ->orderByDesc('created_at')
            ->value('direction_scope_id');

        if (!empty($adminIdFromAssignment)) {
            return (string) $adminIdFromAssignment;
        }

        // Fallback: ancien rattachement via profil d'administration.
        return (string) (Auth::user()?->profile?->administration_id ?: '');
    }

    private function buildSignerUsersQueryForAdministration(?string $administrationId)
    {
        $usersQuery = User::query()
            ->where('status', 'active')
            ->where('role', 'signer');

        if (!$administrationId) {
            // Fallback: keep signer list available even if admin scope is missing.
            return $usersQuery;
        }

        return $usersQuery->where(function ($query) use ($administrationId) {
            $query->whereHas('profile', function ($profileQuery) use ($administrationId) {
                $profileQuery->where('administration_id', $administrationId);
            })->orWhereExists(function ($subQuery) use ($administrationId) {
                $subQuery->select(DB::raw(1))
                    ->from('user_direction_assignments as uda')
                    ->whereColumn('uda.user_id', 'users.id')
                    ->where('uda.direction_scope_id', $administrationId);
            });
        });
    }

    private function resolveAllowedSignerIdsForCurrentAdministration(): array
    {
        $currentAdminId = $this->resolveCurrentAdministrationId();

        return $this->buildSignerUsersQueryForAdministration($currentAdminId)
            ->pluck('users.id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    // ── Pages ────────────────────────────────────────────────────────────────

    public function index()
    {
        $this->guardPermission('workflows.view');
        $userId = Auth::id();
        $workflows = Workflow::with(['steps.assignee', 'executions.document'])
            ->whereIn('id', $this->visibleWorkflowIdsForUser($userId))
            ->latest()->get();

        if (request()->wantsJson()) {
            return response()->json($workflows->map(fn($wf) => $this->formatWorkflow($wf))->values());
        }

        $currentAdminId = $this->resolveCurrentAdministrationId();
        $usersQuery = $this->buildSignerUsersQueryForAdministration($currentAdminId);

        $users = $usersQuery
            ->orderBy('name')
            ->distinct('users.id')
            ->get(['id', 'name', 'full_name', 'email']);

        $documents = Document::where('owner_id', Auth::id())
            ->whereNull('deleted_at')
            ->where('mime_type', '!=', 'application/x-folder')
            ->latest()->get(['id', 'title', 'file_path', 'mime_type']);

        return view('workflows.index', compact('workflows', 'users', 'documents'));
    }

    public function create()
    {
        $this->guardPermission('workflows.create');
        $currentAdminId = $this->resolveCurrentAdministrationId();
        $usersQuery = $this->buildSignerUsersQueryForAdministration($currentAdminId);

        $users = $usersQuery
            ->orderBy('name')
            ->distinct('users.id')
            ->get(['id', 'name', 'email']);
        return view('workflows.create', compact('users'));
    }

    // ── CRUD Workflows ───────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->guardPermission('workflows.create');
        $request->validate([
            'name'        => 'required|string|max:500',
            'steps'       => 'required|array|min:1',
            'steps.*.type'=> 'required|in:review,sign,approve,reject,notify',
            'steps.*.assignee_id' => 'nullable|exists:users,id',
        ]);

        // Sécurité serveur: empêcher toute attribution hors administration même
        // en cas de requête forcée depuis le navigateur.
        $allowedSignerIds = $this->resolveAllowedSignerIdsForCurrentAdministration();
        $requestedAssigneeIds = collect((array) $request->input('steps', []))
            ->map(fn ($step) => (string) ($step['assignee_id'] ?? ''))
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values();

        $forbiddenAssigneeIds = $requestedAssigneeIds
            ->reject(fn ($id) => in_array($id, $allowedSignerIds, true))
            ->values();

        if ($forbiddenAssigneeIds->isNotEmpty()) {
            Log::warning('SECURITY_WORKFLOW_OUT_OF_SCOPE_ASSIGNEE_BLOCKED', [
                'actor_user_id' => (string) Auth::id(),
                'actor_email' => (string) (Auth::user()?->email ?? ''),
                'resolved_administration_id' => (string) ($this->resolveCurrentAdministrationId() ?: ''),
                'workflow_name' => (string) ($request->input('name') ?? ''),
                'requested_assignee_ids' => $requestedAssigneeIds->all(),
                'forbidden_assignee_ids' => $forbiddenAssigneeIds->all(),
                'allowed_assignee_count' => count($allowedSignerIds),
                'ip' => (string) $request->ip(),
                'user_agent' => (string) ($request->userAgent() ?? ''),
                'url' => (string) $request->fullUrl(),
            ]);

            $message = 'Attribution non autorisée: un ou plusieurs utilisateurs ne sont pas des signataires actifs de votre administration.';

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $message,
                    'forbidden_assignee_ids' => $forbiddenAssigneeIds,
                ], 422);
            }

            return back()->withInput()->with('error', $message);
        }

        $workflow = Workflow::create([
            'id'          => Str::uuid(),
            'name'        => $request->name,
            'description' => $request->description,
            'status'      => 'active',
            'created_by'  => Auth::id(),
            'docs_to_sign'   => $request->docs_to_sign ?? [],
            'attached_docs'  => $request->attached_docs ?? [],
        ]);

        foreach ($request->steps as $i => $stepData) {
            WorkflowStep::create([
                'id'                 => Str::uuid(),
                'workflow_id'        => $workflow->id,
                'order'              => $i + 1,
                'name'               => $stepData['name'] ?? null,
                'type'               => $stepData['type'],
                'assignee_id'        => $stepData['assignee_id'] ?? null,
                'description'        => $stepData['description'] ?? null,
                'requires_signature' => !empty($stepData['requires_signature']),
            ]);
        }

        $workflow->load(['steps.assignee', 'executions.document']);

        // Notifier les assignés des étapes
        NotificationService::workflowStepsAssigned($workflow, $workflow->steps, Auth::user()->name);

        if ($request->wantsJson()) {
            return response()->json(['workflow' => $this->formatWorkflow($workflow)], 201);
        }

        return redirect()->route('workflows.index')->with('success', 'Workflow créé avec succès.');
    }

    public function show(Workflow $workflow)
    {
        $this->guardPermission('workflows.view');
        $this->ensureWorkflowVisibility($workflow);
        $workflow->load(['steps.assignee', 'executions.document', 'creator']);
        return view('workflows.show', compact('workflow'));
    }

    public function edit(Workflow $workflow)
    {
        $this->guardPermission('workflows.create');
        abort_if($workflow->created_by !== Auth::id(), 403);
        $users = User::where('status', 'active')->get(['id', 'name', 'email']);
        $workflow->load('steps');
        return view('workflows.edit', compact('workflow', 'users'));
    }

    public function update(Request $request, Workflow $workflow)
    {
        $this->guardPermission('workflows.create');
        abort_if($workflow->created_by !== Auth::id(), 403);
        $workflow->update($request->only('name', 'description', 'status'));

        if ($request->wantsJson()) {
            $workflow->load(['steps.assignee', 'executions.document']);
            return response()->json(['workflow' => $this->formatWorkflow($workflow)]);
        }

        return redirect()->route('workflows.show', $workflow)->with('success', 'Workflow mis à jour.');
    }

    public function destroy(Workflow $workflow)
    {
        $this->guardPermission('workflows.delete');
        abort_if($workflow->created_by !== Auth::id(), 403);
        $workflow->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('workflows.index')->with('success', 'Workflow supprimé.');
    }

    public function duplicate(Request $request, Workflow $workflow)
    {
        $this->guardPermission('workflows.create');
        $this->ensureWorkflowVisibility($workflow);
        $workflow->load('steps');
        $copy = Workflow::create([
            'id'          => Str::uuid(),
            'name'        => $workflow->name . ' (copie)',
            'description' => $workflow->description,
            'status'      => 'active',
            'created_by'  => Auth::id(),
            'docs_to_sign'   => $workflow->docs_to_sign ?? [],
            'attached_docs'  => $workflow->attached_docs ?? [],
        ]);

        foreach ($workflow->steps as $step) {
            WorkflowStep::create([
                'id'                 => Str::uuid(),
                'workflow_id'        => $copy->id,
                'order'              => $step->order,
                'name'               => $step->name,
                'type'               => $step->type,
                'assignee_id'        => $step->assignee_id,
                'description'        => $step->description,
                'requires_signature' => $step->requires_signature,
            ]);
        }

        $copy->load(['steps.assignee', 'executions.document']);

        return response()->json(['workflow' => $this->formatWorkflow($copy)], 201);
    }

    // ── Exécutions ───────────────────────────────────────────────────────────

    public function execute(Request $request, Workflow $workflow)
    {
        $this->guardPermission('workflows.create');
        $this->ensureWorkflowVisibility($workflow);
        $request->validate(['document_id' => 'nullable|exists:documents,id']);

        try {
            // Zones de signature par document {docId: [{page,x,y,width,height,label}, ...]}
            $docZones = $request->input('doc_zones', []);

            $docIds = $workflow->docs_to_sign ?? [];
            if (empty($docIds) && $request->document_id) {
                $docIds = [$request->document_id];
            }
            if (empty($docIds)) {
                $docIds = [null];
            }

            $executions = collect($docIds)->map(function ($docId) use ($workflow, $docZones) {
                return WorkflowExecution::create([
                    'workflow_id'  => $workflow->id,
                    'document_id'  => $docId,
                    'status'       => 'in_progress',
                    'current_step' => 1,
                    'step_data'    => ['doc_zones' => $docZones],
                ]);
            });

            // Créer des SignatureRequests pour la première étape du workflow
            $firstStep = $workflow->steps()->orderBy('order')->first();
            if ($firstStep && $firstStep->assignee_id && $firstStep->requires_signature) {
                $this->createSignatureRequestsForStep($workflow, $firstStep, $docZones);
            }

            // Notifier l'assigné de la première étape
            if ($firstStep) {
                NotificationService::workflowExecutionStarted($workflow, $firstStep, Auth::user()->name);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'execution'  => $executions->first(),
                    'executions' => $executions->values(),
                ]);
            }

            return redirect()->route('workflows.show', $workflow)->with('success', 'Workflow lancé.');

        } catch (\Throwable $e) {
            Log::error('WorkflowController::execute exception', [
                'workflow_id' => $workflow->id,
                'message'     => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Erreur lors du lancement du workflow: ' . $e->getMessage(),
                    'debug'   => config('app.debug') ? ['file' => $e->getFile(), 'line' => $e->getLine()] : null,
                ], 500);
            }

            return back()->with('error', 'Erreur lors du lancement du workflow: ' . $e->getMessage());
        }
    }

    public function advance(Request $request, Workflow $workflow)
    {
        $this->guardPermission('workflows.validate');
        $this->ensureWorkflowVisibility($workflow);
        $execution = $workflow->executions()->where('status', 'in_progress')->first();
        if (!$execution) {
            return response()->json(['error' => 'Aucune exécution en cours'], 404);
        }

        $totalSteps = $workflow->steps()->count();
        $next = $execution->current_step + 1;

        if ($next > $totalSteps) {
            $execution->update(['status' => 'completed', 'current_step' => $totalSteps]);
            NotificationService::workflowCompleted($workflow, Auth::user()->name ?? 'Système');
        } else {
            $execution->update(['current_step' => $next]);
            $nextStep = $workflow->steps()->where('order', $next)->first();
            if ($nextStep && $nextStep->requires_signature) {
                $docZones = $execution->step_data['doc_zones'] ?? [];
                $this->createSignatureRequestsForStep($workflow, $nextStep, is_array($docZones) ? $docZones : []);
            }
            // Notifier l'assigné de la prochaine étape
            NotificationService::workflowStepAdvanced($workflow, $next, Auth::user()->name);
        }

        return response()->json(['execution' => $this->formatExecution($execution->fresh()->load('document'))]);
    }

    public function reject(Request $request, Workflow $workflow)
    {
        $this->guardPermission('workflows.validate');
        $this->ensureWorkflowVisibility($workflow);
        $execution = $workflow->executions()->where('status', 'in_progress')->first();
        if (!$execution) {
            return response()->json(['error' => 'Aucune exécution en cours'], 404);
        }

        $execution->update(['status' => 'rejected']);

        return response()->json(['execution' => $this->formatExecution($execution->fresh()->load('document'))]);
    }

    // ── Templates ────────────────────────────────────────────────────────────

    public function indexTemplates()
    {
        $this->guardPermission('workflows.view');

        $administrationId = $this->resolveCurrentAdministrationId();
        $templatesQuery = WorkflowTemplate::query()->where('created_by', Auth::id());

        if ($administrationId) {
            $templatesQuery->where('administration_id', $administrationId);
        }

        $templates = $templatesQuery->latest()->get();

        return response()->json($templates->map(fn($t) => [
            'id'               => $t->id,
            'name'             => $t->name,
            'description'      => $t->description,
            'validation_steps' => $t->validation_steps ?? [],
            'signature_steps'  => $t->signature_steps ?? [],
            'notification_config' => $t->notification_config ?? null,
        ])->values());
    }

    public function storeTemplate(Request $request)
    {
        $this->guardPermission('workflows.create');
        $request->validate([
            'name' => 'required|string|max:500',
        ]);

        $administrationId = $this->resolveCurrentAdministrationId();
        if (!$administrationId) {
            $message = 'Aucune administration active n’est associée à cet utilisateur.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withInput()->with('error', $message);
        }

        $template = WorkflowTemplate::create([
            'id'               => Str::uuid(),
            'administration_id' => $administrationId,
            'name'             => $request->name,
            'description'      => $request->description,
            'validation_steps' => $request->validation_steps ?? [],
            'signature_steps'  => $request->signature_steps ?? [],
            'notification_config' => $request->notification_config ?? null,
            'status'           => 'active',
            'created_by'       => Auth::id(),
        ]);

        return response()->json([
            'template' => [
                'id'               => $template->id,
                'name'             => $template->name,
                'description'      => $template->description,
                'validation_steps' => $template->validation_steps ?? [],
                'signature_steps'  => $template->signature_steps ?? [],
                'notification_config' => $template->notification_config ?? null,
            ]
        ], 201);
    }
}

