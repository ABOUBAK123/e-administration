<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\PersonnelEmployee;
use App\Models\PersonnelEmployeeDocument;
use App\Models\Signature;
use App\Models\SignatureProviderConfig;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowStep;
use App\Models\Notification;
use App\Services\ClamAvScanner;
use App\Services\NotificationService;
use App\Services\Templates\TemplateGenerationCoreService;
use App\Traits\GuardsPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SignatureController extends Controller
{
    use GuardsPermissions;

    private ?string $lastPlatformError = null;
    private ?string $lastPlatformWorkflowId = null;

    private function syncCompletedVirtualCardToAgentDocuments(WorkflowExecution $execution): void
    {
        $workflow = $execution->workflow;
        $document = $execution->document;
        if (!$workflow || !$document) {
            return;
        }

        $stepData = is_array($execution->step_data) ? $execution->step_data : [];
        $workflowType = strtolower(trim((string) ($stepData['workflow_type'] ?? '')));
        $workflowName = strtolower(trim((string) ($workflow->name ?? '')));

        if ($workflowType !== 'virtual_card_signature' && !str_contains($workflowName, 'carte pour signature')) {
            return;
        }

        $employeeId = (string) ($stepData['employee_id'] ?? '');
        if ($employeeId === '') {
            return;
        }

        $employee = PersonnelEmployee::find($employeeId);
        if (!$employee) {
            return;
        }

        $sourcePath = (string) ($document->final_file_path ?: $document->signed_file_path ?: $document->file_path ?: '');
        if ($sourcePath === '') {
            return;
        }

        $normalized = ltrim($sourcePath, '/');
        $disk = 'local';
        $path = $normalized;

        if (str_starts_with($normalized, 'storage/')) {
            $disk = 'public';
            $path = ltrim(substr($normalized, strlen('storage/')), '/');
        }

        if (!Storage::disk($disk)->exists($path)) {
            $altDisk = $disk === 'public' ? 'local' : 'public';
            if (Storage::disk($altDisk)->exists($path)) {
                $disk = $altDisk;
            } else {
                return;
            }
        }

        $alreadyLinked = PersonnelEmployeeDocument::query()
            ->where('employee_id', $employee->id)
            ->where('category', 'virtual_card_signed')
            ->where('path', $path)
            ->exists();

        if ($alreadyLinked) {
            return;
        }

        $employee->documents()->create([
            'category' => 'virtual_card_signed',
            'label' => 'Carte virtuelle signée',
            'disk' => $disk,
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => $document->mime_type ?: null,
            'size' => Storage::disk($disk)->exists($path) ? Storage::disk($disk)->size($path) : null,
        ]);
    }

    private function appendWorkflowRejectionHistory(WorkflowExecution $execution, array $entry): void
    {
        $stepData = is_array($execution->step_data) ? $execution->step_data : [];
        $history = $stepData['rejection_history'] ?? [];
        if (!is_array($history)) {
            $history = [];
        }

        $history[] = $entry;
        $stepData['rejection_history'] = $history;
        $stepData['latest_rejection'] = $entry;

        $execution->update(['step_data' => $stepData]);
    }

    private function advanceExecutionAfterPlatformDone(WorkflowExecution $execution, ?string $platformStatus = null): array
    {
        if ($execution->status !== 'in_progress') {
            return ['completed' => $execution->status === 'completed'];
        }

        $workflow = $execution->workflow()->with('steps.assignee', 'creator')->first();
        $steps = $workflow?->steps?->sortBy('order')->values() ?? collect();
        $totalSteps = max($steps->count(), 1);
        $currentStep = (int) ($execution->current_step ?? 1);
        $currentStepObj = $steps->firstWhere('order', $currentStep);
        $isSignatureStep = (bool) ($currentStepObj?->requires_signature ?? false);

        // Fermer la demande de signature en cours pour éviter de réutiliser la même zone
        // sur une étape suivante (cas multi-signatures avec même signataire).
        if ($isSignatureStep && $execution->document_id && $currentStepObj?->assignee_id) {
            SignatureRequest::query()
                ->where('document_id', $execution->document_id)
                ->where('requested_to', $currentStepObj->assignee_id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'signed',
                    'responded_at' => now(),
                ]);
        }

        $nextStep = $currentStep + 1;

        if ($nextStep > $totalSteps) {
            $execution->update([
                'status' => 'completed',
                'current_step' => $totalSteps,
                'completed_at' => now(),
                'platform_status' => $platformStatus,
            ]);

            $execution->refresh();
            $execution->loadMissing(['workflow', 'document']);
            $this->syncCompletedVirtualCardToAgentDocuments($execution);

            $isSignatureStep = (bool) ($currentStepObj?->requires_signature ?? false);
            if ($execution->document_id && $isSignatureStep) {
                Document::find($execution->document_id)?->update(['status' => 'signed', 'signed_at' => now()]);
                Signature::create([
                    'id'          => Str::uuid(),
                    'document_id' => $execution->document_id,
                    'signer_id'   => $currentStepObj?->assignee_id ?? Auth::id(),
                    'signature'   => hash('sha256', (string) ($currentStepObj?->assignee_id ?? Auth::id()) . $execution->document_id . now()),
                    'status'      => 'valid',
                    'is_valid'    => true,
                    'signed_at'   => now(),
                    'reason'      => 'Signature via plateforme workflow ' . ($workflow?->name ?? ''),
                ]);
            }

            if ($workflow) {
                NotificationService::workflowCompleted($workflow, Auth::user()?->name ?? 'Signataire');
            }

            return ['completed' => true];
        }

        $nextStepObj = $steps->firstWhere('order', $nextStep);
        $docZones = $execution->step_data['doc_zones'] ?? [];

        $execution->update([
            'current_step' => $nextStep,
            // Réinitialiser la liaison plateforme pour éviter un nouvel avancement sur le même workflow externe.
            'platform_workflow_id' => null,
            'platform_status' => null,
        ]);

        if ($workflow && $nextStepObj && $nextStepObj->requires_signature) {
            $this->createSignatureRequestsForStep($workflow, $nextStepObj, is_array($docZones) ? $docZones : []);
        }

        if ($workflow) {
            NotificationService::workflowStepAdvanced($workflow, $nextStep, $currentStepObj?->assignee?->name ?? 'Signataire');
        }

        return ['completed' => false];
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

            $created = SignatureRequest::create([
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

            Log::info('SunnyStamp Audit: zone assignée à une étape de signature', [
                'workflow_id' => $workflow->id,
                'workflow_name' => $workflow->name,
                'step_id' => $step->id,
                'step_order' => $step->order,
                'step_name' => $step->name,
                'signer_id' => $step->assignee_id,
                'document_id' => $docId,
                'signature_request_id' => $created->id,
                'zone_page' => $zone['page'] ?? null,
                'zone_x' => $zone['x'] ?? null,
                'zone_y' => $zone['y'] ?? null,
                'zone_width' => $zone['width'] ?? $zone['w'] ?? null,
                'zone_height' => $zone['height'] ?? $zone['h'] ?? null,
            ]);
        }
    }

    /**
     * Convertit le détail technique plateforme en message métier plus clair.
     */
    private static function toBusinessPlatformMessage(?string $detail): string
    {
        $d = (string) ($detail ?? '');
        if ($d === '') {
            return 'Erreur de communication avec la plateforme de signature.';
        }

        if (str_contains($d, 'EntityLocked') || str_contains($d, 'entity is being updated')) {
            return 'La plateforme est en cours de synchronisation du workflow. Merci de reessayer dans quelques secondes.';
        }

        if (str_contains($d, 'RecipientPhoneNumberRequired')) {
            return 'Le profil du signataire doit contenir un numéro de téléphone pour cette page de consentement.';
        }

        if (str_starts_with($d, 'create_workflow:')) {
            return 'Impossible de créer le workflow de signature sur la plateforme.';
        }

        if (str_starts_with($d, 'upload_document:')) {
            return 'Le workflow a été créé, mais le document n\'a pas pu être chargé sur la plateforme.';
        }

        if (str_starts_with($d, 'create_document:')) {
            return 'Le fichier a été transmis, mais la création du document de signature sur la plateforme a échoué.';
        }

        if (str_starts_with($d, 'start_workflow:')) {
            return 'Le document a été chargé, mais le démarrage du workflow a échoué sur la plateforme.';
        }

        if (str_contains($d, 'invite: aucun lien exploitable trouvé')) {
            return 'Le workflow est lancé, mais la plateforme ne fournit aucun lien d\'accès signataire (endpoints invite indisponibles ou sans token).';
        }

        if (str_starts_with($d, 'invite:')) {
            return 'Le workflow est lancé, mais la génération du lien d\'invitation a échoué sur la plateforme.';
        }

        return 'Erreur lors de l\'orchestration de la signature sur la plateforme.';
    }

    /**
     * Résout un numéro de téléphone exploitable pour le recipient plateforme.
     */
    private static function resolveRecipientPhone(User $user): ?string
    {
        $employee = PersonnelEmployee::where('linked_user_id', $user->id)->first();

        $candidate = (string) (
            $employee?->phone
            ?? $employee?->secondary_phone
            ?? $user->phone
            ?? $user->phone_number
            ?? $user->mobile
            ?? ''
        );

        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        return self::normalizePhoneNumber($candidate);
    }

    private static function normalizePhoneNumber(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        $phone = preg_replace('/[\s\-\(\)\.]/u', '', $phone);
        if (!$phone) {
            return null;
        }

        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '00')) {
                $phone = '+' . substr($phone, 2);
            } elseif (ctype_digit($phone)) {
                $phone = '+225' . $phone;
            }
        }

        if (!preg_match('/^\+\d{7,15}$/', $phone)) {
            return null;
        }

        return $phone;
    }

    /**
     * Formate une erreur API avec status + body + requestId/logId quand disponibles.
     */
    private static function formatApiErrorDetail(\Illuminate\Http\Client\Response $resp): string
    {
        $body = (string) $resp->body();
        $detail = 'HTTP ' . $resp->status() . ' - ' . Str::limit($body, 500, '...');

        $json = $resp->json();
        if (is_array($json)) {
            $requestId = $json['requestId'] ?? $json['request_id'] ?? null;
            $logId = $json['logId'] ?? $json['log_id'] ?? null;

            if (is_string($requestId) && $requestId !== '') {
                $detail .= ' | requestId=' . $requestId;
            }
            if (is_string($logId) && $logId !== '') {
                $detail .= ' | logId=' . $logId;
            }
        }

        return $detail;
    }

    /**
     * Détecte le conflit transitoire API "EntityLocked".
     */
    private static function isEntityLockedResponse(\Illuminate\Http\Client\Response $resp): bool
    {
        if ($resp->status() !== 409) {
            return false;
        }

        $json = $resp->json();
        $code = '';
        $message = '';
        if (is_array($json)) {
            $code = (string) ($json['code'] ?? $json['errorCode'] ?? $json['error'] ?? '');
            $message = (string) ($json['message'] ?? '');
        }

        return strcasecmp($code, 'EntityLocked') === 0 || str_contains(strtolower($message), 'entity is being updated');
    }

    /**
     * Extrait la première URL utile trouvée dans une réponse API (parcours récursif).
     */
    private static function extractFirstUrlFromPayload(mixed $payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $stack = [$payload];
        while (!empty($stack)) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }

            foreach ($node as $key => $value) {
                if (is_string($value) && preg_match('/^https?:\/\//i', $value)) {
                    $k = strtolower((string) $key);
                    if (str_contains($k, 'invite') || str_contains($k, 'url') || str_contains($k, 'link')) {
                        return $value;
                    }
                }

                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return null;
    }

    /**
     * Extrait une URL absolue depuis un texte brut (fallback API non JSON).
     */
    private static function extractFirstUrlFromText(string $text): ?string
    {
        if (preg_match('/https?:\/\/[^\s"\'<>]+/i', $text, $matches) === 1) {
            return $matches[0] ?? null;
        }

        return null;
    }

    /**
     * Extrait l'URL d'invitation depuis la réponse API.
     * Gère : URL complète (inviteUrl/url/link), token JWT (→ construit /invite?token=...),
     * champs imbriqués, tableaux de recipients/steps.
     */
    private function extractInviteUrl(mixed $payload, string $baseEndpoint): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $base = rtrim($baseEndpoint, '/');

        // Chercher les champs directs d'abord
        $urlFields  = ['inviteUrl', 'invite_url', 'url', 'link', 'redirectUrl', 'signingUrl', 'accessUrl'];
        $tokenFields = ['token', 'inviteToken', 'invite_token', 'accessToken', 'signingToken'];

        // Parcours récursif (BFS)
        $queue = [$payload];
        while (!empty($queue)) {
            $node = array_shift($queue);
            if (!is_array($node)) {
                continue;
            }

            // URL complète
            foreach ($urlFields as $f) {
                if (isset($node[$f]) && is_string($node[$f]) && str_starts_with($node[$f], 'http')) {
                    return $node[$f];
                }
            }

            // Token → construire URL
            foreach ($tokenFields as $f) {
                if (isset($node[$f]) && is_string($node[$f]) && $node[$f] !== '') {
                    return $base . '/invite?token=' . $node[$f];
                }
            }

            // Descendre dans les clés
            foreach ($node as $key => $child) {
                if (is_array($child)) {
                    $queue[] = $child;
                }
            }
        }

        // Dernier recours : première URL avec invite/url/link dans le nom de clé
        return self::extractFirstUrlFromPayload($payload);
    }

    public function index()
    {
        $this->guardPermission('signatures.view');
        $userId = Auth::id();

        // Demandes de signature en attente pour moi
        $pendingRequests = SignatureRequest::with(['document', 'requester'])
            ->where('requested_to', $userId)
            ->where('status', 'pending')
            ->latest('created_at')->get();

        // Mes signatures effectuées
        $mySignatures = Signature::with('document')
            ->where('signer_id', $userId)
            ->latest('created_at')->paginate(10);

        // Documents disponibles (pour le formulaire de demande/signature)
        $documents = Document::whereNull('deleted_at')
            ->where(function ($q) use ($userId) {
                $q->where('owner_id', $userId)->orWhere('created_by', $userId);
            })
            ->orderBy('title')->get(['id', 'title', 'status', 'mime_type']);

        // Tous les utilisateurs (pour choisir un destinataire)
        $users = User::where('id', '!=', $userId)->orderBy('name')->get(['id', 'name', 'email']);

        // Mes workflows avec étapes et exécutions
        $myWorkflows = Workflow::with(['steps.assignee', 'executions.document', 'creator'])
            ->where('created_by', $userId)
            ->latest()->get();

        // Boîte de réception workflow : exécutions où c'est mon tour
        $workflowInbox = $this->buildWorkflowInbox($userId);

        return view('signatures.index', compact(
            'pendingRequests', 'mySignatures', 'documents', 'users', 'myWorkflows', 'workflowInbox'
        ));
    }

    /**
     * Construire la boîte de réception des actions workflow pour l'utilisateur courant.
     */
    private function buildWorkflowInbox(string $userId): array
    {
        $rows = [];

        $notifiedWorkflowIds = Notification::where('recipient_id', $userId)
            ->whereNotNull('workflow_id')
            ->pluck('workflow_id');

        $workflows = Workflow::with(['steps.assignee', 'executions.document', 'creator'])
            ->where('created_by', $userId)
            ->orWhereHas('steps', fn($q) => $q->where('assignee_id', $userId))
            ->orWhereIn('id', $notifiedWorkflowIds)
            ->get();

        foreach ($workflows as $wf) {
            $steps      = $wf->steps->sortBy('order')->values();
            $executions = $wf->executions;

            // Regrouper par étape courante + type d'action
            $grouped = [];
            foreach ($executions as $exec) {
                $currentStep = (int) ($exec->current_step ?? 1);
                $currentStepObj = $steps->firstWhere('order', $currentStep);
                $totalSteps = max($steps->count(), 1);

                $desc = strtolower($currentStepObj->description ?? $currentStepObj->name ?? '');
                $isSignature = str_contains($desc, 'signature') || ($currentStepObj->requires_signature ?? false);
                $actionType = $isSignature ? 'signature' : 'validation';

                $assigneeId = $currentStepObj->assignee_id ?? null;
                $isMyTurn   = !$assigneeId || $assigneeId === $userId;

                $status = $exec->status ?? 'pending';
                $completed = $status === 'completed' ? $totalSteps : max(0, min($currentStep - 1, $totalSteps));
                $progress  = $totalSteps > 0 ? round(($completed / $totalSteps) * 100) : 0;
                if ($status === 'completed') $progress = 100;

                $nextStepObj  = $steps->firstWhere('order', $currentStep + 1);
                $nextActorLabel = match(true) {
                    $status === 'completed' => 'Workflow terminé',
                    $status === 'rejected'  => 'Workflow rejeté',
                    $nextStepObj !== null   => $nextStepObj->assignee?->name ?? $nextStepObj->assignee?->email ?? 'Fin de workflow',
                    default                 => 'Fin de workflow',
                };

                $key = $wf->id . '::' . $actionType . '::' . $currentStep;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'workflowId'    => $wf->id,
                        'workflowName'  => $wf->name,
                        'creatorLabel'  => $wf->creator?->name ?? $wf->creator?->email ?? 'Inconnu',
                        'actionType'    => $actionType,
                        'items'         => [],
                    ];
                }

                $grouped[$key]['items'][] = [
                    'executionId'   => $exec->id,
                    'documentId'    => $exec->document_id,
                    'documentTitle' => $exec->document?->title ?? 'Document',
                    'signedFilePath'=> $exec->document?->signed_file_path ?? null,
                    'docStatus'     => $exec->document?->status ?? null,
                    'status'        => $status,
                    'progress'      => $progress,
                    'nextActorLabel'=> $nextActorLabel,
                    'isMyTurn'      => $isMyTurn,
                ];
            }

            foreach ($grouped as $row) {
                $statuses  = array_column($row['items'], 'status');
                $rowStatus = in_array('in_progress', $statuses) ? 'in_progress'
                    : (in_array('pending', $statuses) ? 'pending'
                    : (in_array('rejected', $statuses) ? 'rejected' : 'completed'));

                $statusLabel = match($rowStatus) {
                    'completed'  => 'Terminé',
                    'rejected'   => 'Rejeté',
                    'in_progress'=> 'En cours',
                    default      => 'Démarré',
                };

                $avgProgress = count($row['items']) > 0
                    ? (int) round(array_sum(array_column($row['items'], 'progress')) / count($row['items']))
                    : 0;

                $inProgress  = collect($row['items'])->firstWhere('status', 'in_progress');
                $representative = $inProgress ?? $row['items'][0] ?? [];
                $actionableIds  = collect($row['items'])
                    ->filter(fn($i) => $i['status'] === 'in_progress' && $i['isMyTurn'])
                    ->pluck('executionId')->all();

                $rows[] = [
                    'workflowId'         => $row['workflowId'],
                    'workflowName'       => $row['workflowName'],
                    'creatorLabel'       => $row['creatorLabel'],
                    'actionType'         => $row['actionType'],
                    'documentTitle'      => count($row['items']) === 1 ? ($representative['documentTitle'] ?? 'Document') : count($row['items']) . ' documents',
                    'documentId'         => $representative['documentId'] ?? null,
                    'signedFilePath'     => $representative['signedFilePath'] ?? null,
                    'firstExecutionId'   => $representative['executionId'] ?? null,
                    'status'             => $rowStatus,
                    'statusLabel'        => $statusLabel,
                    'progress'           => $avgProgress,
                    'nextActorLabel'     => $rowStatus === 'completed' ? 'Workflow terminé' : ($representative['nextActorLabel'] ?? 'Fin de workflow'),
                    'isMyTurn'           => count($actionableIds) > 0,
                    'actionableIds'      => $actionableIds,
                ];
            }
        }

        // Trier : mon tour en premier, puis en cours, puis par nom
        usort($rows, function ($a, $b) {
            if ($a['isMyTurn'] !== $b['isMyTurn']) return $a['isMyTurn'] ? -1 : 1;
            if ($a['status'] !== $b['status']) {
                $ap = $a['status'] === 'in_progress';
                $bp = $b['status'] === 'in_progress';
                if ($ap !== $bp) return $ap ? -1 : 1;
            }
            return strcmp($a['workflowName'], $b['workflowName']);
        });

        return $rows;
    }

    public function store(Request $request)
    {
        $this->guardPermission('signatures.request');
        $request->validate([
            'document_id' => 'required|exists:documents,id',
            'signature'   => 'required|string',
            'reason'      => 'nullable|string|max:500',
        ]);

        $document = Document::find($request->document_id);
        $qrToken = $document->qr_token ?: Str::random(40);

        Signature::create([
            'id'          => Str::uuid(),
            'document_id' => $request->document_id,
            'signer_id'   => Auth::id(),
            'signature'   => $request->signature,
            'reason'      => $request->reason,
            'status'      => 'valid',
            'is_valid'    => true,
            'qr_code_token' => Str::random(40),
        ]);

        $document->update([
            'status' => 'signed',
            'signed_at' => now(),
            'qr_token' => $qrToken,
        ]);

        SignatureRequest::where('document_id', $request->document_id)
            ->where('requested_to', Auth::id())
            ->where('status', 'pending')
            ->update(['status' => 'signed', 'responded_at' => now()]);

        return redirect()->route('signatures.index')->with('success', 'Document signé avec succès.');
    }

    public function show(Signature $signature)
    {
        $this->guardPermission('signatures.view');
        return view('signatures.show', compact('signature'));
    }

    public function request(Request $request)
    {
        $this->guardPermission('signatures.request');
        $request->validate([
            'document_id'  => 'required|exists:documents,id',
            'requested_to' => 'required|exists:users,id',
            'message'      => 'nullable|string',
            'expiry_date'  => 'nullable|date|after:today',
        ]);

        SignatureRequest::create([
            'id'           => Str::uuid(),
            'document_id'  => $request->document_id,
            'requested_by' => Auth::id(),
            'requested_to' => $request->requested_to,
            'message'      => $request->message,
            'status'       => 'pending',
            'expiry_date'  => $request->expiry_date,
        ]);

        return back()->with('success', 'Demande de signature envoyée.');
    }

    public function decline(SignatureRequest $signatureRequest)
    {
        $this->guardPermission('signatures.reject');
        abort_if($signatureRequest->requested_to !== Auth::id(), 403);
        $signatureRequest->update(['status' => 'declined', 'responded_at' => now()]);
        return back()->with('success', 'Demande refusée.');
    }

    /**
     * Afficher la page de positionnement de la zone de signature
     */
    public function position(SignatureRequest $signatureRequest)
    {
        abort_if($signatureRequest->requested_to !== Auth::id(), 403);
        abort_if($signatureRequest->status !== 'pending', 403);

        $signatureRequest->load('document', 'requester');

        // Récupérer les zones de signature pré-définies sur le template du document
        $templateZones = [];
        $doc = $signatureRequest->document;
        if ($doc && $doc->template_id) {
            $tpl = \App\Models\DocumentTemplate::find($doc->template_id);
            if ($tpl && $tpl->signature_zones) {
                $decoded = json_decode($tpl->signature_zones, true);
                if (is_array($decoded)) {
                    $templateZones = $decoded;
                }
            }
        }

        return view('signatures.position', compact('signatureRequest', 'templateZones'));
    }

    /**
     * Sauvegarder la zone de signature et enregistrer la signature
     */
    public function sign(Request $request, SignatureRequest $signatureRequest)
    {
        abort_if($signatureRequest->requested_to !== Auth::id(), 403);
        abort_if($signatureRequest->status !== 'pending', 403);

        $request->validate([
            'signature'   => 'required|string',
            'reason'      => 'nullable|string|max:500',
            'zone_page'   => 'required|integer|min:1',
            'zone_x'      => 'required|numeric|min:0|max:100',
            'zone_y'      => 'required|numeric|min:0|max:100',
            'zone_width'  => 'required|numeric|min:1|max:100',
            'zone_height' => 'required|numeric|min:1|max:100',
            'zone_label'  => 'nullable|string|max:255',
        ]);

        // Sauvegarder la zone dans la demande
        $signatureRequest->update([
            'zone_page'   => $request->zone_page,
            'zone_x'      => $request->zone_x,
            'zone_y'      => $request->zone_y,
            'zone_width'  => $request->zone_width,
            'zone_height' => $request->zone_height,
            'zone_label'  => $request->zone_label,
            'status'      => 'signed',
            'responded_at'=> now(),
        ]);

        // Créer la signature
        Signature::create([
            'id'          => Str::uuid(),
            'document_id' => $signatureRequest->document_id,
            'signer_id'   => Auth::id(),
            'signature'   => $request->signature,
            'reason'      => $request->reason,
            'status'      => 'valid',
            'is_valid'    => true,
            'signed_at'   => now(),
        ]);

        // Mettre à jour le document
        Document::find($signatureRequest->document_id)?->update([
            'status'    => 'signed',
            'signed_at' => now(),
        ]);

        return redirect()->route('signatures.index')
            ->with('success', 'Document signé avec succès. La signature a été positionnée à la page ' . $request->zone_page . '.');
    }

    public function create()
    {
        $this->guardPermission('signatures.request');
        return view('signatures.create');
    }

    public function serveWorkflowDocument(string $executionId)
    {
        if (
            !$this->canPermission('workflows.view')
            && !$this->canPermission('signatures.view')
            && !$this->canPermission('signatures.request')
        ) {
            abort(403, 'Accès refusé.');
        }
        $execution = WorkflowExecution::with(['workflow.steps.assignee', 'document'])->find($executionId);
        abort_unless($execution && $execution->document, 404);

        $userId = (string) Auth::id();
        $workflow = $execution->workflow;
        $steps = $workflow?->steps?->sortBy('order') ?? collect();
        $currentStep = (int) ($execution->current_step ?? 1);
        $currentStepObj = $steps->firstWhere('order', $currentStep);

        $isCreator = (string) ($workflow?->created_by ?? '') === $userId;
        $isCurrentActor = (string) ($currentStepObj?->assignee_id ?? '') === $userId;

        abort_unless($isCreator || $isCurrentActor, 403);

        $document = $execution->document;
        $path = ltrim(str_replace('/storage/', '', (string) $document->file_path), '/');
        abort_if($path === '' || !Storage::disk('public')->exists($path), 404, 'Fichier introuvable sur le serveur.');

        $ext  = pathinfo((string) $document->file_path, PATHINFO_EXTENSION) ?: 'bin';
        $safeTitle = preg_replace('/[\/\\\\\x00-\x1f]+/', '-', (string) $document->title);
        $safeTitle = trim((string) $safeTitle, '-') ?: 'document';
        $name = pathinfo($safeTitle, PATHINFO_EXTENSION) === $ext ? $safeTitle : ($safeTitle . '.' . $ext);

        return Storage::disk('public')->response($path, $name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
        ]);
    }

    /**
     * Action workflow depuis la boîte de réception (signer ou valider une exécution).
     */
    public function workflowAction(Request $request)
    {
        if (!$this->canPermission('workflows.validate') && !$this->canPermission('signatures.request')) {
            abort(403, 'Accès refusé.');
        }
        $request->validate([
            'execution_ids'   => 'required|array',
            'execution_ids.*' => 'required|string',
            'action_type'     => 'required|in:signature,validation',
            'action_decision' => 'nullable|in:approve,reject',
            'reject_reason'   => 'nullable|string|max:2000',
        ]);

        $userId = Auth::id();
        $actionType = $request->action_type;
        $decision = $request->input('action_decision', 'approve');
        $rejectReason = trim((string) $request->input('reject_reason', ''));
        $successCount = 0;

        foreach ($request->execution_ids as $executionId) {
            $execution = WorkflowExecution::find($executionId);
            if (!$execution || $execution->status !== 'in_progress') continue;

            $wf    = $execution->workflow()->with('steps')->first();
            $steps = $wf?->steps?->sortBy('order') ?? collect();
            $currentStep = (int) ($execution->current_step ?? 1);
            $currentStepObj = $steps->firstWhere('order', $currentStep);
            $assigneeId = $currentStepObj?->assignee_id;
            $isSignatureStep = (bool) ($currentStepObj?->requires_signature ?? false);

            // Vérifier que c'est bien le tour de l'utilisateur
            if ($assigneeId && $assigneeId !== $userId) continue;

            if ($actionType === 'validation' && $isSignatureStep) continue;
            if ($actionType === 'signature' && !$isSignatureStep) continue;

            if ($decision === 'reject') {
                if ($rejectReason === '') {
                    continue;
                }

                $actorName = Auth::user()?->name ?? 'Un utilisateur';
                $rejectedAt = now();
                $actorRole = $isSignatureStep ? 'signataire' : 'valideur';
                $historyEntry = [
                    'actor_id' => $userId,
                    'actor_name' => $actorName,
                    'actor_role' => $actorRole,
                    'reason' => $rejectReason,
                    'step_order' => $currentStep,
                    'step_name' => $currentStepObj?->name ?? ($isSignatureStep ? 'Signature' : 'Validation'),
                    'action_type' => $actionType,
                    'rejected_at' => $rejectedAt->toIso8601String(),
                ];

                $execution->update([
                    'status' => 'rejected',
                    'completed_at' => $rejectedAt,
                ]);
                $execution->refresh();
                $this->appendWorkflowRejectionHistory($execution, $historyEntry);

                if ($wf?->created_by) {
                    NotificationService::notify(
                        recipientId: (string) $wf->created_by,
                        type: 'workflow',
                        title: 'Workflow refusé',
                        message: sprintf(
                            '%s (%s) a refusé le workflow "%s" le %s. Motif: %s',
                            $actorName,
                            $actorRole,
                            $wf->name ?? 'Sans nom',
                            $rejectedAt->format('d/m/Y à H:i'),
                            $rejectReason
                        ),
                        actionUrl: route('workflows.index') . '#rejete',
                        workflowId: (string) ($wf->id ?? null),
                        executionId: (string) $execution->id
                    );
                }

                $successCount++;
                continue;
            }

            $totalSteps = $steps->count();
            $nextStep   = $currentStep + 1;

            if ($nextStep > $totalSteps) {
                // Dernière étape : terminer le workflow
                $execution->update(['status' => 'completed', 'current_step' => $totalSteps, 'completed_at' => now()]);

                $execution->refresh();
                $execution->loadMissing(['workflow', 'document']);
                $this->syncCompletedVirtualCardToAgentDocuments($execution);

                if ($execution->document_id && $isSignatureStep) {
                    Document::find($execution->document_id)?->update(['status' => 'signed', 'signed_at' => now()]);
                    // Créer une signature automatique
                    Signature::create([
                        'id'          => Str::uuid(),
                        'document_id' => $execution->document_id,
                        'signer_id'   => $userId,
                        'signature'   => hash('sha256', $userId . $execution->document_id . now()),
                        'status'      => 'valid',
                        'is_valid'    => true,
                        'signed_at'   => now(),
                        'reason'      => 'Signature via workflow ' . ($wf?->name ?? ''),
                    ]);
                }

                if ($wf?->created_by) {
                    NotificationService::notify(
                        recipientId: (string) $wf->created_by,
                        type: 'workflow',
                        title: 'Workflow terminé',
                        message: $isSignatureStep
                            ? sprintf('Le workflow "%s" est terminé et le document a été signé.', $wf->name ?? 'Sans nom')
                            : sprintf('Le workflow "%s" est terminé après validation finale.', $wf->name ?? 'Sans nom'),
                        actionUrl: route('workflows.index') . '#termine',
                        workflowId: (string) ($wf->id ?? null),
                        executionId: (string) $execution->id
                    );
                }
            } else {
                $execution->update(['current_step' => $nextStep]);

                $nextStepObj = $steps->firstWhere('order', $nextStep);
                if ($wf && $nextStepObj && $nextStepObj->requires_signature) {
                    $docZones = $execution->step_data['doc_zones'] ?? [];
                    $this->createSignatureRequestsForStep($wf, $nextStepObj, is_array($docZones) ? $docZones : []);
                }

                if ($wf) {
                    NotificationService::workflowStepAdvanced($wf, $nextStep, Auth::user()->name);
                }
            }

            $successCount++;
        }

        $msg = $successCount > 0
            ? ($decision === 'reject'
                ? "Refus enregistré sur {$successCount} document(s)."
                : ($actionType === 'signature'
                    ? "Signature effectuée sur {$successCount} document(s)."
                    : "Validation effectuée sur {$successCount} document(s)."))
            : 'Aucune action effectuée (vérifiez les droits ou le statut des exécutions).';

        return back()->with($successCount > 0 ? 'success' : 'error', $msg);
    }

    /**
     * Créer un nouveau workflow de signature depuis le modal.
     */
    public function workflowCreate(Request $request)
    {
        $this->guardPermission('signatures.request');
        $request->validate([
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string',
            'validation_approvers'    => 'nullable|array',
            'validation_approvers.*'  => 'nullable|exists:users,id',
            'signature_signers'       => 'nullable|array',
            'signature_signers.*'     => 'nullable|exists:users,id',
            'docs_to_sign'            => 'nullable|array',
            'docs_to_sign.*'          => 'nullable|exists:documents,id',
        ]);

        $userId = Auth::id();

        // Filtrer les valeurs vides
        $approvers = array_filter($request->input('validation_approvers', []), fn($v) => !empty($v));
        $signers   = array_filter($request->input('signature_signers', []),   fn($v) => !empty($v));
        $docsToSign = array_filter($request->input('docs_to_sign', []),       fn($v) => !empty($v));

        if (empty($approvers) && empty($signers)) {
            return back()->with('error', 'Ajoutez au moins une étape de validation ou de signature.')->withInput();
        }

        $wf = Workflow::create([
            'id'          => Str::uuid(),
            'name'        => $request->name,
            'description' => $request->description,
            'status'      => 'active',
            'created_by'  => $userId,
            'docs_to_sign'=> array_values($docsToSign),
        ]);

        $order = 1;
        foreach (array_values($approvers) as $approverId) {
            $wf->steps()->create([
                'id'          => Str::uuid(),
                'name'        => 'Validation ' . $order,
                'type'        => 'validation',
                'assignee_id' => $approverId,
                'order'       => $order,
                'description' => 'Étape de validation',
                'requires_signature' => false,
            ]);
            $order++;
        }
        foreach (array_values($signers) as $signerId) {
            $wf->steps()->create([
                'id'          => Str::uuid(),
                'name'        => 'Signature ' . $order,
                'type'        => 'signature',
                'assignee_id' => $signerId,
                'order'       => $order,
                'description' => 'Étape de signature',
                'requires_signature' => true,
            ]);
            $order++;
        }

        // Démarrer les exécutions pour chaque document
        foreach (array_values($docsToSign) as $docId) {
            WorkflowExecution::create([
                'id'          => Str::uuid(),
                'workflow_id' => $wf->id,
                'document_id' => $docId,
                'current_step'=> 1,
                'status'      => 'in_progress',
                'started_at'  => now(),
            ]);
        }

        return back()->with('success', "Workflow \"{$wf->name}\" créé et démarré avec succès.");
    }
    public function edit(string $id) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}

    /**
     * Afficher la page d'upload & signer (fichier depuis l'ordinateur)
     */
    public function showUpload()
    {
        $this->guardPermission('signatures.request');
        return view('signatures.upload');
    }

    /**
     * Traiter l'upload du PDF + zone + signature
     */
    public function handleUpload(Request $request)
    {
        $this->guardPermission('signatures.request');
        $request->validate([
            'pdf_file'    => 'required|file|mimes:pdf|max:20480',
            'signature'   => 'required|string',
            'reason'      => 'nullable|string|max:500',
            'zone_page'   => 'required|integer|min:1',
            'zone_x'      => 'required|numeric|min:0|max:100',
            'zone_y'      => 'required|numeric|min:0|max:100',
            'zone_width'  => 'required|numeric|min:1|max:100',
            'zone_height' => 'required|numeric|min:1|max:100',
            'zone_label'  => 'nullable|string|max:255',
            'doc_title'   => 'nullable|string|max:255',
        ]);

        // Stocker le fichier
        $file      = $request->file('pdf_file');
        ClamAvScanner::scan($file, 'signatures');
        $filePath  = $file->store('uploads/signatures', 'public');
        $title     = $request->doc_title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $user = Auth::user();
        $service = new TemplateGenerationCoreService();
        $docNumberData = $service->generateDocumentNumber($user);

        // Créer le document
        $document = Document::create([
            'id'         => Str::uuid(),
            'title'      => $title,
            'file_path'  => 'storage/' . $filePath,
            'final_file_path' => 'storage/' . $filePath,
            'file_size'  => $file->getSize(),
            'mime_type'  => 'application/pdf',
            'status'     => 'signed',
            'owner_id'   => Auth::id(),
            'created_by' => Auth::id(),
            'signed_at'  => now(),
            'document_number' => $docNumberData['document_number'],
            'sub_entity_code' => $docNumberData['sub_entity_code'],
            'issuing_administration_id' => $docNumberData['issuing_administration_id'],
        ]);

        // Créer la demande de signature (pour enregistrer la zone)
        $sigRequest = SignatureRequest::create([
            'id'           => Str::uuid(),
            'document_id'  => $document->id,
            'requested_by' => Auth::id(),
            'requested_to' => Auth::id(),
            'status'       => 'signed',
            'responded_at' => now(),
            'zone_page'    => $request->zone_page,
            'zone_x'       => $request->zone_x,
            'zone_y'       => $request->zone_y,
            'zone_width'   => $request->zone_width,
            'zone_height'  => $request->zone_height,
            'zone_label'   => $request->zone_label,
        ]);

        // Créer la signature
        Signature::create([
            'id'          => Str::uuid(),
            'document_id' => $document->id,
            'signer_id'   => Auth::id(),
            'signature'   => $request->signature,
            'reason'      => $request->reason,
            'status'      => 'valid',
            'is_valid'    => true,
            'signed_at'   => now(),
        ]);

        return redirect()->route('signatures.index')
            ->with('success', 'Document "' . $title . '" signé et enregistré avec succès.');
    }

    // ────────────────────────────────────────────────────────────────────────
    // API Signature externe (Goodflag / SunnyStamp)
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Résoudre la configuration API de signature pour l'utilisateur connecté.
     */
    private function resolveSignatureConfig(): ?SignatureProviderConfig
    {
        $user = Auth::user();
        if (!$user) return null;
        $adminId = $user->profile?->administration_id ?? null;
        if (!$adminId) return null;

        return SignatureProviderConfig::where('administration_id', $adminId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Résoudre le user_id de la plateforme pour un email donné.
     * Cherche l'utilisateur sur la plateforme via GET /api/users?email={email}.
     * Résultat mis en cache 1 h pour éviter des appels répétés.
     */
    /** Dernière erreur API lors du lookup utilisateur (pour diagnostic). */
    private static ?string $lastLookupApiError = null;

    public static function resolvePlatformUserIdByEmail(SignatureProviderConfig $cfg, string $email): ?string
    {
        $email = strtolower(trim($email));
        $endpoint = self::normalizeEndpoint($cfg->endpoint);
        $cacheKey = 'sunnystamp_uid_' . md5($endpoint . '|' . $email);
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $resolved = (function () use ($cfg, $email, $endpoint) {
            try {
                $resp = Http::withToken($cfg->api_key)
                    ->timeout(10)
                    ->when(!(bool) $cfg->verify_ssl, fn($h) => $h->withoutVerifying())
                    ->get($endpoint . '/api/users', ['email' => $email]);

                if (!$resp->successful()) {
                    self::$lastLookupApiError = 'GET /api/users HTTP ' . $resp->status() . ' — ' . \Illuminate\Support\Str::limit((string) $resp->body(), 300);
                }

                if ($resp->successful()) {
                    $body = $resp->json();

                    $extractUserId = function (array $user) use ($email): ?string {
                        $userEmail = strtolower(trim((string) ($user['email'] ?? $user['emailAddress'] ?? $user['mail'] ?? '')));
                        $userId = (string) ($user['id'] ?? $user['userId'] ?? $user['uuid'] ?? '');
                        if ($userId === '') {
                            return null;
                        }
                        if ($userEmail !== '' && $userEmail === $email) {
                            return $userId;
                        }
                        return null;
                    };

                    $scanUsers = function ($collection) use ($extractUserId): ?string {
                        if (!is_array($collection)) {
                            return null;
                        }

                        foreach ($collection as $row) {
                            if (is_array($row)) {
                                $id = $extractUserId($row);
                                if ($id) {
                                    return $id;
                                }
                            }
                        }

                        // Fallback: premier élément avec id quand l'API ne renvoie pas l'email.
                        foreach ($collection as $row) {
                            if (is_array($row) && !empty($row['id'])) {
                                return (string) $row['id'];
                            }
                        }

                        return null;
                    };

                    // Réponse tableau brut
                    $id = $scanUsers($body);
                    if ($id) {
                        return $id;
                    }

                    // Réponse objet (direct/paginé)
                    if (is_array($body)) {
                        if (!empty($body['id'])) {
                            return (string) $body['id'];
                        }

                        foreach (['data', 'items', 'results', 'users', 'content'] as $bucket) {
                            $id = $scanUsers($body[$bucket] ?? null);
                            if ($id) {
                                return $id;
                            }
                        }

                        // Certaines API encapsulent sous _embedded.users
                        $id = $scanUsers($body['_embedded']['users'] ?? null);
                        if ($id) {
                            return $id;
                        }
                    }
                }

                Log::warning('SunnyStamp: utilisateur non trouvé par email', [
                    'email'  => $email,
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                ]);
            } catch (\Throwable $e) {
                self::$lastLookupApiError = 'Exception: ' . $e->getMessage();
                Log::warning('SunnyStamp: erreur recherche utilisateur par email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
            return null;
        })();

        // Ne met en cache que les IDs valides pour éviter un faux négatif persistant.
        if (is_string($resolved) && $resolved !== '') {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $resolved, 3600);
        }

        return $resolved;
    }

    /**
     * Résoudre l'utilisateur owner du token API via /api/users/me.
     * Utilisé en fallback quand la recherche par email n'est pas disponible.
     */
    public static function resolvePlatformOwnerUserId(SignatureProviderConfig $cfg): ?string
    {
        try {
            $endpoint = self::normalizeEndpoint($cfg->endpoint);
            $resp = Http::withToken($cfg->api_key)
                ->timeout(10)
                ->when(!(bool) $cfg->verify_ssl, fn($h) => $h->withoutVerifying())
                ->get($endpoint . '/api/users/me');

            if (!$resp->successful()) {
                self::$lastLookupApiError = 'GET /api/users/me HTTP ' . $resp->status() . ' — ' . \Illuminate\Support\Str::limit((string) $resp->body(), 300);
                Log::warning('SunnyStamp: échec /api/users/me', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);
                return null;
            }

            $body = $resp->json();
            if (is_array($body)) {
                if (!empty($body['id'])) {
                    return (string) $body['id'];
                }
                if (!empty($body['data']['id'])) {
                    return (string) $body['data']['id'];
                }
                if (!empty($body['user']['id'])) {
                    return (string) $body['user']['id'];
                }
            }

            Log::warning('SunnyStamp: /api/users/me sans id exploitable', [
                'body' => $resp->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SunnyStamp: exception /api/users/me', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Crée le workflow sur la plateforme, upload le document, le démarre
     * et génère le lien d'invitation pour le signataire/approbateur.
     */
    /**
     * Normalise l'endpoint en retirant /api à la fin s'il existe.
     * Permet de gérer à la fois:
     * - https://uvci.artci-sign.ci (ajoute /api/)
     * - https://sigfae.artci-sign.ci/api (retire /api, puis ajoute /api/)
     */
    private static function normalizeEndpoint(string $endpoint): string
    {
        $endpoint = rtrim($endpoint, '/');
        if (str_ends_with($endpoint, '/api')) {
            $endpoint = substr($endpoint, 0, -4); // Retire les 4 derniers chars "/api"
        }
        return $endpoint;
    }

    /**
     * Convertit une zone exprimée en pourcentage (UI locale) en champ PDF API SunnyStamp.
     */
    private static function mapPercentZoneToPdfSignatureField(array $zone): ?array
    {
        $x = (float) ($zone['x'] ?? 0);
        $y = (float) ($zone['y'] ?? 0);
        $w = (float) ($zone['w'] ?? $zone['width'] ?? 0);
        $h = (float) ($zone['h'] ?? $zone['height'] ?? 0);
        $page = (int) ($zone['page'] ?? -1);

        if ($w <= 0 || $h <= 0) {
            return null;
        }

        // Si la zone ressemble déjà à des coordonnées PDF absolues, on la conserve.
        if ($x > 100 || $y > 100 || $w > 100 || $h > 100) {
            return [
                'imagePage' => $page === 0 ? -1 : $page,
                'imageX' => round($x, 2),
                'imageY' => round($y, 2),
                'imageWidth' => round($w, 2),
                'imageHeight' => round($h, 2),
            ];
        }

        // Conversion en points PDF (A4 portrait: 595 x 842).
        $pageWidth = 595.0;
        $pageHeight = 842.0;

        $imageX = max(0.0, min($pageWidth - 10.0, ($x / 100.0) * $pageWidth));
        $imageY = max(0.0, min($pageHeight - 10.0, ($y / 100.0) * $pageHeight));
        $imageW = max(20.0, min($pageWidth - $imageX, ($w / 100.0) * $pageWidth));
        $imageH = max(20.0, min($pageHeight - $imageY, ($h / 100.0) * $pageHeight));

        return [
            'imagePage' => $page <= 0 ? -1 : $page,
            'imageX' => round($imageX, 2),
            'imageY' => round($imageY, 2),
            'imageWidth' => round($imageW, 2),
            'imageHeight' => round($imageH, 2),
        ];
    }

    /**
     * Résout la zone de signature à envoyer à la plateforme.
     * Priorité: zone posée par utilisateur (signature_requests), sinon zone template.
     */
    private function resolveSignatureZoneForPlatform(Document $document, User $signer): ?array
    {
        $requestZone = SignatureRequest::query()
            ->where('document_id', $document->id)
            ->where('requested_to', $signer->id)
            ->whereNotNull('zone_x')
            ->whereNotNull('zone_y')
            ->whereNotNull('zone_width')
            ->whereNotNull('zone_height')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->first();

        if ($requestZone) {
            return [
                'page' => (int) ($requestZone->zone_page ?? -1),
                'x' => (float) $requestZone->zone_x,
                'y' => (float) $requestZone->zone_y,
                'w' => (float) $requestZone->zone_width,
                'h' => (float) $requestZone->zone_height,
                'source' => 'signature_request',
                'signature_request_id' => (string) $requestZone->id,
            ];
        }

        $templateId = (string) ($document->template_id ?? '');
        if ($templateId !== '') {
            $template = DocumentTemplate::find($templateId);
            if ($template && !empty($template->signature_zones)) {
                $zones = is_string($template->signature_zones)
                    ? json_decode($template->signature_zones, true)
                    : $template->signature_zones;

                if (is_array($zones) && !empty($zones[0]) && is_array($zones[0])) {
                    return [
                        'page' => (int) ($zones[0]['page'] ?? -1),
                        'x' => (float) ($zones[0]['x'] ?? 0),
                        'y' => (float) ($zones[0]['y'] ?? 0),
                        'w' => (float) ($zones[0]['w'] ?? $zones[0]['width'] ?? 0),
                        'h' => (float) ($zones[0]['h'] ?? $zones[0]['height'] ?? 0),
                        'source' => 'document_template',
                        'template_id' => (string) $template->id,
                    ];
                }
            }
        }

        return null;
    }

    private function buildPdfSignatureFields(Document $document, User $signer, array $auditContext = []): array
    {
        $defaultField = [[
            'imagePage' => -1,
            'imageX' => 390.0,
            'imageY' => 710.0,
            'imageWidth' => 150.0,
            'imageHeight' => 80.0,
        ]];

        $zone = $this->resolveSignatureZoneForPlatform($document, $signer);
        if (!$zone) {
            Log::warning('SunnyStamp Audit: aucune zone trouvée, fallback par défaut', array_merge($auditContext, [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'zone_page' => -1,
                'zone_x' => null,
                'zone_y' => null,
            ]));
            return $defaultField;
        }

        $field = self::mapPercentZoneToPdfSignatureField($zone);
        if (!$field) {
            Log::warning('SunnyStamp Audit: zone invalide, fallback par défaut', array_merge($auditContext, [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'zone_page' => $zone['page'] ?? null,
                'zone_x' => $zone['x'] ?? null,
                'zone_y' => $zone['y'] ?? null,
            ]));
            return $defaultField;
        }

        Log::info('SunnyStamp Audit: zone utilisée pour la signature plateforme', array_merge($auditContext, [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'signer_email' => $signer->email,
            'zone_source' => $zone['source'] ?? 'unknown',
            'signature_request_id' => $zone['signature_request_id'] ?? null,
            'zone_page' => $zone['page'] ?? null,
            'zone_x' => $zone['x'] ?? null,
            'zone_y' => $zone['y'] ?? null,
            'zone_width' => $zone['w'] ?? null,
            'zone_height' => $zone['h'] ?? null,
            'pdf_field' => $field,
        ]));

        return [$field];
    }

    private function buildPlatformInviteUrl(
        SignatureProviderConfig $cfg,
        string $ownerUserId,
        string $actionType,
        Document $document,
        User $signer,
        array $auditContext = []
    ): ?string {
        $this->lastPlatformError = null;

        $endpoint  = self::normalizeEndpoint($cfg->endpoint);
        $token     = $cfg->api_key;
        $timeout   = max(10, (int) round($cfg->timeout_ms / 1000));
        $verifySSL = (bool) $cfg->verify_ssl;

        $consentPageId = $actionType === 'signature'
            ? ($cfg->consent_page_id ?? '')
            : ($cfg->consent_page_id_approval ?: $cfg->consent_page_id ?? '');

        $sigProfileId = $cfg->signature_profile_id ?? '';

        $recipientPlatformUserId = self::resolvePlatformUserIdByEmail($cfg, (string) $signer->email);
        $nameParts = preg_split('/\s+/', trim((string) $signer->name)) ?: [];
        $recipientFirstName = (string) ($nameParts[0] ?? $signer->name ?? 'Utilisateur');
        $recipientLastName = trim((string) implode(' ', array_slice($nameParts, 1)));
        $recipientPhone = self::resolveRecipientPhone($signer);

        // Certaines pages de consentement requièrent un numéro de téléphone.
        // Ne pas l'inclure si le téléphone n'est pas disponible.
        Log::debug('SunnyStamp: phone resolution', [
            'signer_email' => $signer->email,
            'phone_resolved' => $recipientPhone,
            'consent_page_id_before' => $consentPageId,
        ]);

        if (!empty($consentPageId) && empty($recipientPhone)) {
            Log::warning('SunnyStamp: consentPageId ignored - no phone for recipient', [
                'signer_email' => $signer->email,
                'consent_page_id' => $consentPageId,
                'recipient_phone' => $recipientPhone,
            ]);
            $consentPageId = '';
        }

        Log::debug('SunnyStamp: consentPageId final', [
            'consent_page_id_after' => $consentPageId,
            'will_be_sent' => !empty($consentPageId),
        ]);

        $client = Http::withToken($token)
            ->timeout($timeout)
            ->when(!$verifySSL, fn($h) => $h->withoutVerifying());

        // 1. Créer le workflow
        $stepType  = $actionType === 'signature' ? 'signature' : 'approval';
        $recipient = [
            'email' => $signer->email,
            'firstName' => $recipientFirstName,
            'lastName' => $recipientLastName,
        ];
        if (!empty($recipientPhone)) {
            $recipient['phoneNumber'] = $recipientPhone;
        }

        $webhookToken = config('services.signature_platform.webhook_secret', '');
        $platformWebhookUrl = rtrim(config('app.url'), '/') . '/api/signature/platform-webhook'
            . ($webhookToken !== '' ? '?token=' . urlencode($webhookToken) : '');

        $workflowPayload = [
            'name'            => 'e-Parapheur — ' . $document->title,
            'steps'           => [[
                'stepType'           => $stepType,
                'recipients'         => [$recipient],
                'requiredRecipients' => 1,
            ]],
            'webhookUrl'      => $platformWebhookUrl,
        ];

        if (!empty($consentPageId)) {
            $workflowPayload['consentPageId'] = $consentPageId;
        }
        if (!empty($sigProfileId)) {
            $workflowPayload['signatureProfileId'] = $sigProfileId;
        }

        Log::debug('SunnyStamp: workflow creation request', [
            'endpoint' => "{$endpoint}/api/users/{$ownerUserId}/workflows",
            'ownerUserId' => $ownerUserId,
            'recipient_email' => $signer->email,
            'recipient_phone' => $recipientPhone,
            'consent_page_id' => $consentPageId,
            'payload_keys' => array_keys($workflowPayload),
            'full_payload' => $workflowPayload,
        ]);

        $wflResp = $client
            ->withHeaders(['Content-Type' => 'application/json'])
            ->asJson()
            ->post("{$endpoint}/api/users/{$ownerUserId}/workflows", $workflowPayload);

        // LOG la réponse BRUTE complète
        Log::info('SunnyStamp: **WORKFLOW CREATION RESPONSE FULL**', [
            'status' => $wflResp->status(),
            'body_raw' => $wflResp->body(),
            'json_decoded' => $wflResp->json(),
        ]);

        if (!$wflResp->successful()) {
            // Fallback payload: certaines versions API refusent des champs avancés.
            $fallbackPayload = [
                'name' => 'e-Parapheur — ' . $document->title,
                'steps' => [[
                    'stepType' => $stepType,
                    'recipients' => [array_filter([
                        'email' => $signer->email,
                        'firstName' => $recipientFirstName,
                        'lastName' => $recipientLastName,
                        'phoneNumber' => $recipientPhone ?: null,
                        'id' => $recipientPlatformUserId ?: null,
                        'userId' => $recipientPlatformUserId ?: null,
                    ], fn($v) => $v !== null && $v !== '')],
                    'requiredRecipients' => 1,
                ]],
                'webhookUrl'      => $platformWebhookUrl,
            ];

            if (!empty($consentPageId)) {
                $fallbackPayload['consentPageId'] = $consentPageId;
            }
            if (!empty($sigProfileId)) {
                $fallbackPayload['signatureProfileId'] = $sigProfileId;
            }

            $fallbackResp = $client
                ->withHeaders(['Content-Type' => 'application/json'])
                ->asJson()
                ->post("{$endpoint}/api/users/{$ownerUserId}/workflows", $fallbackPayload);
            if ($fallbackResp->successful()) {
                $wflResp = $fallbackResp;
            }
        }

        if (!$wflResp->successful()) {
            $errorCode = is_array($wflResp->json()) ? ($wflResp->json('code') ?? '') : '';
            if ($errorCode === 'RecipientPhoneNumberRequired' && empty($recipientPhone)) {
                $this->lastPlatformError = 'create_workflow: RecipientPhoneNumberRequired - le profil signataire ne contient pas de numero de telephone (champ personnel requis).';
                Log::error('SunnyStamp: création workflow bloquée - phone manquant pour recipient', [
                    'signer_user_id' => $signer->id,
                    'signer_email' => $signer->email,
                ]);
                return null;
            }

            $this->lastPlatformError = 'create_workflow: ' . self::formatApiErrorDetail($wflResp);
            Log::error('SunnyStamp: échec création workflow', [
                'status' => $wflResp->status(), 'body' => $wflResp->body(),
            ]);
            return null;
        }

        // Extraire l'ID du workflow depuis la réponse (clés variées selon le tenant)
        $wflRespJson = $wflResp->json();
        $workflowId = $wflRespJson['id']
            ?? $wflRespJson['workflowId']
            ?? $wflRespJson['workflow_id']
            ?? ($wflRespJson['workflow']['id'] ?? null)
            ?? null;

        // Vérifier si la réponse contient déjà l'URL d'invitation
        $inviteUrlFromCreation = $wflRespJson['inviteUrl']
            ?? $wflRespJson['invite']['url']
            ?? $wflRespJson['invites'][0]['url']
            ?? $wflRespJson['stepInvites'][0]['url']
            ?? null;

        Log::debug('SunnyStamp: workflow creation response', [
            'workflow_id' => $workflowId,
            'invite_url_in_response' => $inviteUrlFromCreation,
            'response_keys' => array_keys($wflRespJson),
            'steps' => $wflRespJson['steps'] ?? null,
            'currentRecipientEmails' => $wflRespJson['currentRecipientEmails'] ?? null,
            'currentRecipientUsers' => $wflRespJson['currentRecipientUsers'] ?? null,
        ]);

        if (is_string($inviteUrlFromCreation) && $inviteUrlFromCreation !== '') {
            Log::info('SunnyStamp: URL d\'invitation trouvée dans réponse workflow', [
                'workflow_id' => $workflowId,
                'invite_url' => $inviteUrlFromCreation,
            ]);
            return $inviteUrlFromCreation;
        }

        if (!is_string($workflowId) || $workflowId === '') {
            $this->lastPlatformError = 'create_workflow: ID du workflow non trouvé dans la réponse API - ' . self::formatApiErrorDetail($wflResp);
            Log::error('SunnyStamp: ID workflow absent', [
                'response' => $wflResp->json(),
            ]);
            return null;
        }

        $this->lastPlatformWorkflowId = $workflowId;

        // 2. Uploader le document PDF
        // Si un PDF déjà signé existe, l'utiliser comme base pour conserver les signatures précédentes.
        $sourceFilePath = !empty($document->signed_file_path)
            ? (string) $document->signed_file_path
            : (string) ($document->final_file_path ?: $document->file_path ?: '');

        $filePath = trim($sourceFilePath);
        $normalizedPublicDiskPath = ltrim($filePath, '/');
        if (str_starts_with($normalizedPublicDiskPath, 'public/')) {
            $normalizedPublicDiskPath = substr($normalizedPublicDiskPath, 7);
        }
        if (str_starts_with($normalizedPublicDiskPath, 'storage/')) {
            $normalizedPublicDiskPath = substr($normalizedPublicDiskPath, 8);
        }

        // Gérer les différents formats historiques de file_path.
        $candidatePaths = array_values(array_unique(array_filter([
            $filePath !== '' ? public_path(ltrim($filePath, '/')) : null,
            $normalizedPublicDiskPath !== '' ? Storage::disk('public')->path($normalizedPublicDiskPath) : null,
            $normalizedPublicDiskPath !== '' ? storage_path('app/public/' . $normalizedPublicDiskPath) : null,
        ])));

        $absolutePath = null;
        foreach ($candidatePaths as $candidatePath) {
            if (is_string($candidatePath) && $candidatePath !== '' && file_exists($candidatePath)) {
                $absolutePath = $candidatePath;
                break;
            }
        }

        if (!$absolutePath) {
            $this->lastPlatformError = 'upload_document: fichier introuvable (' . $filePath . ')';
            Log::error('SunnyStamp: fichier introuvable', [
                'file_path' => $filePath,
                'normalized' => $normalizedPublicDiskPath,
                'candidates' => $candidatePaths,
            ]);
            return null;
        }

        $pdfBytes = file_get_contents($absolutePath);
        if ($pdfBytes === false) {
            $this->lastPlatformError = 'upload_document: impossible de lire le fichier (' . $absolutePath . ')';
            Log::error('SunnyStamp: lecture fichier upload impossible', [
                'absolute_path' => $absolutePath,
            ]);
            return null;
        }

        $fileSize = strlen($pdfBytes);
        $fileHash = base64_encode(hash('sha256', $pdfBytes, true));
        $fileName = basename($absolutePath);

        // Étape 2a: POST /parts (raw application/pdf) selon Postman ARTCI.
        $uploadUrl = "{$endpoint}/api/workflows/{$workflowId}/parts";
        $uploadResp = null;

        try {
            $uploadResp = Http::withToken($token)
                ->timeout($timeout)
                ->when(!$verifySSL, fn($h) => $h->withoutVerifying())
                ->withBody($pdfBytes, 'application/pdf')
                ->withHeaders([
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                ])
                ->post($uploadUrl);

            Log::info('SunnyStamp: tentative upload raw PDF', [
                'workflow_id' => $workflowId,
                'url' => $uploadUrl,
                'status' => $uploadResp->status(),
                'body_excerpt' => substr($uploadResp->body(), 0, 250),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SunnyStamp: exception upload raw PDF', [
                'workflow_id' => $workflowId,
                'url' => $uploadUrl,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback multipart si le tenant refuse le raw.
        if (!$uploadResp || !$uploadResp->successful()) {
            foreach (['document', 'file', 'part'] as $field) {
                try {
                    $candidate = $client
                        ->attach($field, $pdfBytes, $fileName, ['Content-Type' => 'application/pdf'])
                        ->post($uploadUrl);

                    Log::info('SunnyStamp: fallback upload multipart', [
                        'workflow_id' => $workflowId,
                        'field' => $field,
                        'url' => $uploadUrl,
                        'status' => $candidate->status(),
                    ]);

                    if ($candidate->successful()) {
                        $uploadResp = $candidate;
                        break;
                    }

                    $uploadResp = $candidate;
                    if (in_array($candidate->status(), [401, 403], true)) {
                        break;
                    }
                } catch (\Throwable $e) {
                    Log::warning('SunnyStamp: exception fallback upload multipart', [
                        'workflow_id' => $workflowId,
                        'field' => $field,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!$uploadResp || !$uploadResp->successful()) {
            if (!$uploadResp) {
                $this->lastPlatformError = 'upload_document: aucune réponse exploitable reçue';
                Log::error('SunnyStamp: échec upload document (aucune réponse)', ['workflow_id' => $workflowId]);
                return null;
            }

            $this->lastPlatformError = 'upload_document: ' . self::formatApiErrorDetail($uploadResp);
            Log::error('SunnyStamp: échec upload document', [
                'status' => $uploadResp->status(),
                'body' => $uploadResp->body(),
            ]);
            return null;
        }

        // 2b. Créer l'invite IMMÉDIATEMENT après l'upload, AVANT le document
        // Cela empêche le workflow de se lancer automatiquement avant qu'on ait l'URL d'invitation
        Log::debug('SunnyStamp: creating invite before document creation', [
            'workflow_id' => $workflowId,
            'recipient_email' => $signer->email,
        ]);

        // Essayer de récupérer les invites (pluriel) pour voir si elles existent déjà
        $getInvitesResp = $client
            ->withHeaders(['Content-Type' => 'application/json'])
            ->asJson()
            ->get("{$endpoint}/api/workflows/{$workflowId}/invites");

        $inviteUrl = null;
        Log::debug('SunnyStamp: GET /invites response', [
            'workflow_id' => $workflowId,
            'status' => $getInvitesResp->status(),
            'successful' => $getInvitesResp->successful(),
            'body_excerpt' => substr($getInvitesResp->body(), 0, 200),
        ]);

        if ($getInvitesResp->successful()) {
            $invitesJson = $getInvitesResp->json();
            Log::debug('SunnyStamp: retrieved invites (GET /invites)', [
                'workflow_id' => $workflowId,
                'invites_response' => $invitesJson,
            ]);

            // Essayer de trouver l'URL d'invitation
            if (is_array($invitesJson)) {
                $inviteUrl = $invitesJson['url']
                    ?? $invitesJson[0]['url']
                    ?? $invitesJson['invites'][0]['url']
                    ?? null;
            }

            if (is_string($inviteUrl) && $inviteUrl !== '') {
                Log::info('SunnyStamp: invite URL récupérée via GET /invites', [
                    'workflow_id' => $workflowId,
                    'invite_url' => $inviteUrl,
                ]);
                return $inviteUrl;
            }
        }

        // Si GET /invites ne fonctionne pas, essayer POST /invites (pluriel) pour créer l'invite
        $inviteResp = $client
            ->withHeaders(['Content-Type' => 'application/json'])
            ->asJson()
            ->post("{$endpoint}/api/workflows/{$workflowId}/invites", ['recipientEmail' => $signer->email]);

        // Si POST /invites échoue, essayer POST /invite (singulier)
        if (!$inviteResp->successful()) {
            Log::debug('SunnyStamp: POST /invites failed, trying POST /invite (singular)', [
                'workflow_id' => $workflowId,
                'status' => $inviteResp->status(),
            ]);

            $inviteResp = $client
                ->withHeaders(['Content-Type' => 'application/json'])
                ->asJson()
                ->post("{$endpoint}/api/workflows/{$workflowId}/invite", ['recipientEmail' => $signer->email]);
        }

        if ($inviteResp->successful()) {
            $inviteJson = $inviteResp->json();
            $inviteUrl = $inviteJson['url'] ?? $inviteJson['inviteUrl'] ?? $inviteJson['link'] ?? null;
            Log::info('SunnyStamp: invite récupérée avant document', [
                'workflow_id' => $workflowId,
                'invite_url' => $inviteUrl,
                'method' => 'POST/GET',
            ]);
            if (is_string($inviteUrl) && $inviteUrl !== '') {
                return $inviteUrl;
            }
        } else {
            Log::warning('SunnyStamp: early invite creation/retrieval failed (will retry later)', [
                'workflow_id' => $workflowId,
                'status' => $inviteResp->status(),
                'body_excerpt' => substr($inviteResp->body(), 0, 300),
            ]);
        }

        // Étape 2c: POST /documents avec pdfSignatureFields (obligatoire pour positionner la zone).
        $partData = $uploadResp->json();
        $displayedPart = $partData['documents'][0]['displayedParts'][0]
            ?? $partData['displayedParts'][0]
            ?? $partData['parts'][0]
            ?? null;

        $docPart = [
            'filename' => $displayedPart['filename'] ?? $fileName,
            'contentType' => $displayedPart['contentType'] ?? 'application/pdf',
            'size' => (int) ($displayedPart['size'] ?? $fileSize),
            'hash' => $displayedPart['hash'] ?? $fileHash,
        ];

        $partId = $partData['id'] ?? $partData['partId'] ?? ($displayedPart['id'] ?? null);
        if (is_string($partId) && $partId !== '') {
            $docPart['id'] = $partId;
        }

        $docPayload = [
            'parts' => [$docPart],
            'pdfSignatureFields' => $this->buildPdfSignatureFields($document, $signer, $auditContext),
        ];
        if (!empty($sigProfileId)) {
            $docPayload['signatureProfileId'] = $sigProfileId;
        }

        // Essayer d'ajouter l'invite directement dans le payload du document
        $docPayload['recipients'] = [
            array_filter([
                'email' => $signer->email,
                'firstName' => $recipientFirstName,
                'lastName' => $recipientLastName,
                'phoneNumber' => $recipientPhone,
            ], fn($v) => !is_null($v) && $v !== '')
        ];

        try {
            $docResp = $client->post("{$endpoint}/api/workflows/{$workflowId}/documents", $docPayload);
            $docRespJson = $docResp->json();
            $docId = $docRespJson['id'] ?? $docRespJson['documentId'] ?? $docRespJson['document']['id'] ?? null;

            // LOG la réponse BRUTE complète du document
            Log::info('SunnyStamp: **DOCUMENT CREATION RESPONSE FULL**', [
                'workflow_id' => $workflowId,
                'status' => $docResp->status(),
                'body_raw' => $docResp->body(),
                'json_decoded' => $docRespJson,
            ]);

            if (!$docResp->successful()) {
                $this->lastPlatformError = 'create_document: ' . self::formatApiErrorDetail($docResp);
                Log::error('SunnyStamp: échec création document via /documents', [
                    'workflow_id' => $workflowId,
                    'status' => $docResp->status(),
                    'body' => $docResp->body(),
                    'doc_payload' => $docPayload,
                ]);
                return null;
            }

            // Chercher l'invite URL dans la réponse du document
            $docInviteUrl = $this->extractInviteUrl($docRespJson, $endpoint);
            if (is_string($docInviteUrl) && $docInviteUrl !== '') {
                Log::info('SunnyStamp: ✅ Invite URL trouvée dans réponse document', [
                    'workflow_id' => $workflowId,
                    'document_id' => $docId,
                    'invite_url' => $docInviteUrl,
                ]);
                return $docInviteUrl;
            }

            // APRÈS le document: faire GET /workflows/{id} pour obtenir les invites générées
            Log::debug('SunnyStamp: Document créé, récupération du workflow pour les invites', [
                'workflow_id' => $workflowId,
            ]);

            try {
                $workflowDetailsResp = $client->get("{$endpoint}/api/workflows/{$workflowId}");
                if ($workflowDetailsResp->successful()) {
                    $workflowDetailsJson = $workflowDetailsResp->json();
                    Log::info('SunnyStamp: **WORKFLOW DETAILS AFTER DOCUMENT**', [
                        'workflow_id' => $workflowId,
                        'body_raw' => $workflowDetailsResp->body(),
                    ]);

                    $detailsInviteUrl = $this->extractInviteUrl($workflowDetailsJson, $endpoint);
                    if (is_string($detailsInviteUrl) && $detailsInviteUrl !== '') {
                        Log::info('SunnyStamp: ✅ Invite URL trouvée dans détails workflow APRÈS document', [
                            'workflow_id' => $workflowId,
                            'invite_url' => $detailsInviteUrl,
                        ]);
                        return $detailsInviteUrl;
                    }

                    // Essayer GET /workflows/{id}/invites pour récupérer les invitations
                    Log::debug('SunnyStamp: Tentative GET /invites après document création', [
                        'workflow_id' => $workflowId,
                    ]);
                    try {
                        $getInvitesResp = $client->get("{$endpoint}/api/workflows/{$workflowId}/invites");
                        if ($getInvitesResp->successful()) {
                            Log::info('SunnyStamp: GET /invites response après document', [
                                'workflow_id' => $workflowId,
                                'body' => $getInvitesResp->body(),
                            ]);
                            $invitesUrl = $this->extractInviteUrl($getInvitesResp->json(), $endpoint);
                            if (is_string($invitesUrl) && $invitesUrl !== '') {
                                Log::info('SunnyStamp: ✅ Invite URL from GET /invites', [
                                    'workflow_id' => $workflowId,
                                    'invite_url' => $invitesUrl,
                                ]);
                                return $invitesUrl;
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('SunnyStamp: Exception GET /invites après document', [
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Fallback: Construire manuellement avec différentes variations
                    $stepId = $workflowDetailsJson['steps'][0]['id'] ?? null;

                    // IMPORTANT: Mettre le workflow en état "started" (PAS "active"!)
                    // Selon la collection Postman: PATCH /workflows/{id} avec {"workflowStatus": "started"}
                    Log::debug('SunnyStamp: Lancement workflow via PATCH /workflows/{id}', [
                        'workflow_id' => $workflowId,
                    ]);
                    try {
                        $patchResp = $client->patch(
                            "{$endpoint}/api/workflows/{$workflowId}",
                            ['workflowStatus' => 'started']  // Correct: workflowStatus, pas status
                        );
                        Log::info('SunnyStamp: PATCH /workflows/{id} launched workflow', [
                            'workflow_id' => $workflowId,
                            'http_status' => $patchResp->status(),
                            'body' => Str::limit($patchResp->body(), 500),
                        ]);

                        // Après lancement: essayer POST /workflows/{id}/invite (pas /sendInvite!)
                        if ($patchResp->successful() || $patchResp->status() === 200) {
                            Log::debug('SunnyStamp: Workflow lancé, tentative POST /invite', [
                                'workflow_id' => $workflowId,
                                'recipient_email' => $signer->email,
                            ]);
                            try {
                                $inviteResp = $client->post(
                                    "{$endpoint}/api/workflows/{$workflowId}/invite",
                                    ['recipientEmail' => $signer->email]
                                );
                                if ($inviteResp->successful()) {
                                    $inviteJson = $inviteResp->json();
                                    Log::info('SunnyStamp: ✅ Invite créée via POST /invite', [
                                        'workflow_id' => $workflowId,
                                        'response' => $inviteJson,
                                    ]);
                                    $inviteUrl = $this->extractInviteUrl($inviteJson, $endpoint);
                                    if (is_string($inviteUrl) && $inviteUrl !== '') {
                                        Log::info('SunnyStamp: ✅ Invite URL from POST /invite', [
                                            'workflow_id' => $workflowId,
                                            'invite_url' => $inviteUrl,
                                        ]);
                                        return $inviteUrl;
                                    }
                                }
                            } catch (\Throwable $e) {
                                Log::warning('SunnyStamp: Exception POST /invite après launch', [
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning('SunnyStamp: Exception PATCH workflow', [
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $possibleUrls = [
                        rtrim($endpoint, '/') . '/invite?workflowId=' . $workflowId,
                        rtrim($endpoint, '/') . '/invite?id=' . $workflowId,
                        rtrim($endpoint, '/') . '/workflows/' . $workflowId . '/invite',
                        $stepId ? (rtrim($endpoint, '/') . '/invite?stepId=' . $stepId) : null,
                        $stepId ? (rtrim($endpoint, '/') . '/invite?workflowId=' . $workflowId . '&stepId=' . $stepId) : null,
                    ];
                    $possibleUrls = array_filter($possibleUrls, fn($url) => $url !== null);

                    // Utiliser la première (priorité au workflowId seul)
                    $constructedInviteUrl = reset($possibleUrls);
                    Log::info('SunnyStamp: ✅ Invite URL CONSTRUITE manuellement (fallback)', [
                        'workflow_id' => $workflowId,
                        'step_id' => $stepId,
                        'invite_url' => $constructedInviteUrl,
                        'all_attempts' => $possibleUrls,
                    ]);
                    return $constructedInviteUrl;
                }
            } catch (\Throwable $e) {
                Log::warning('SunnyStamp: Exception lors du GET workflow après document', [
                    'workflow_id' => $workflowId,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->lastPlatformError = 'create_document: exception ' . $e->getMessage();
            Log::error('SunnyStamp: exception création document /documents', [
                'workflow_id' => $workflowId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        // 3. Démarrer le workflow
        // DÉSACTIVÉ: Le workflow peut démarrer automatiquement lors de la création de l'invite
        // Essayons directement de créer l'invite au lieu de démarrer manuellement

        Log::debug('SunnyStamp: skipping direct workflow start, proceeding to invite creation', [
            'workflow_id' => $workflowId,
        ]);

        // 4. Créer le lien d'invitation (directement, sans démarrage manuel du workflow)
        Log::debug('SunnyStamp: proceeding to invite creation', [
            'workflow_id' => $workflowId,
        ]);
        $recipientIdentity = array_filter([
            'id' => $recipientPlatformUserId,
            'userId' => $recipientPlatformUserId,
            'email' => $signer->email,
            'firstName' => $recipientFirstName,
            'lastName' => $recipientLastName,
            'name' => (string) $signer->name,
            'phoneNumber' => $recipientPhone,
        ], fn($v) => !is_null($v) && $v !== '');

        $inviteAttempts = [
            // Endpoints officiels SunnyStamp d'abord.
            [
                'label' => 'POST /api/workflows/{workflowId}/sendInvite recipientEmail',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/sendInvite",
                'payloadType' => 'json',
                'payload' => ['recipientEmail' => $signer->email],
            ],
            [
                'label' => 'POST /api/workflows/{workflowId}/sendInvite email',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/sendInvite",
                'payloadType' => 'json',
                'payload' => ['email' => $signer->email],
            ],
            [
                'label' => 'POST /api/workflows/{workflowId}/invite recipientEmail',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invite",
                'payloadType' => 'json',
                'payload' => ['recipientEmail' => $signer->email],
            ],
            [
                'label' => 'POST /api/workflows/{workflowId}/invite email',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invite",
                'payloadType' => 'json',
                'payload' => ['email' => $signer->email],
            ],
            [
                'label' => 'POST /api/workflows/{workflowId}/invite recipient',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invite",
                'payloadType' => 'json',
                'payload' => ['recipient' => $recipientIdentity],
            ],
            [
                'label' => 'POST /api/workflows/{workflowId}/invite recipientId',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invite",
                'payloadType' => 'json',
                'payload' => ['recipientId' => $recipientPlatformUserId],
            ],
            [
                'label' => 'POST /api/workflows/{workflowId}/invite userId',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invite",
                'payloadType' => 'json',
                'payload' => ['userId' => $recipientPlatformUserId],
            ],
            [
                'label' => 'POST /api/workflows/{workflowId}/invite/',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invite/",
                'payloadType' => 'json',
                'payload' => ['recipientEmail' => $signer->email],
            ],
            [
                'label' => 'GET /api/workflows/{workflowId}/invite recipientEmail',
                'method' => 'GET',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invite",
                'payloadType' => 'query',
                'payload' => ['recipientEmail' => $signer->email],
            ],

            // Variantes sous /api/users/{ownerUserId}/workflows/{workflowId}
            [
                'label' => 'POST /api/users/{ownerUserId}/workflows/{workflowId}/sendInvite recipientEmail',
                'method' => 'POST',
                'url' => "{$endpoint}/api/users/{$ownerUserId}/workflows/{$workflowId}/sendInvite",
                'payloadType' => 'json',
                'payload' => ['recipientEmail' => $signer->email],
            ],
            [
                'label' => 'POST /api/users/{ownerUserId}/workflows/{workflowId}/invite recipientEmail',
                'method' => 'POST',
                'url' => "{$endpoint}/api/users/{$ownerUserId}/workflows/{$workflowId}/invite",
                'payloadType' => 'json',
                'payload' => ['recipientEmail' => $signer->email],
            ],
            [
                'label' => 'POST /api/users/{ownerUserId}/workflows/{workflowId}/invite email',
                'method' => 'POST',
                'url' => "{$endpoint}/api/users/{$ownerUserId}/workflows/{$workflowId}/invite",
                'payloadType' => 'json',
                'payload' => ['email' => $signer->email],
            ],
            [
                'label' => 'GET /api/users/{ownerUserId}/workflows/{workflowId}/invite recipientEmail',
                'method' => 'GET',
                'url' => "{$endpoint}/api/users/{$ownerUserId}/workflows/{$workflowId}/invite",
                'payloadType' => 'query',
                'payload' => ['recipientEmail' => $signer->email],
            ],
            [
                'label' => 'POST /api/users/{ownerUserId}/workflows/{workflowId}/invite-link recipientEmail',
                'method' => 'POST',
                'url' => "{$endpoint}/api/users/{$ownerUserId}/workflows/{$workflowId}/invite-link",
                'payloadType' => 'json',
                'payload' => ['recipientEmail' => $signer->email],
            ],
            [
                'label' => 'POST /api/users/{ownerUserId}/workflows/{workflowId}/invite-link recipientId',
                'method' => 'POST',
                'url' => "{$endpoint}/api/users/{$ownerUserId}/workflows/{$workflowId}/invite-link",
                'payloadType' => 'json',
                'payload' => ['recipientId' => $recipientPlatformUserId],
            ],

            // Autres variantes observées sur certains tenants.
            [
                'label' => 'POST /api/workflows/{workflowId}/invites recipientEmail',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invites",
                'payloadType' => 'json',
                'payload' => ['recipientEmail' => $signer->email],
            ],
            [
                'label' => 'POST /api/workflows/{workflowId}/invites recipient',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invites",
                'payloadType' => 'json',
                'payload' => ['recipient' => $recipientIdentity],
            ],
            [
                'label' => 'POST /api/workflows/{workflowId}/invite-link recipientEmail',
                'method' => 'POST',
                'url' => "{$endpoint}/api/workflows/{$workflowId}/invite-link",
                'payloadType' => 'json',
                'payload' => ['recipientEmail' => $signer->email],
            ],
            [
                'label' => 'POST /api/users/{ownerUserId}/invite workflowId+recipientEmail',
                'method' => 'POST',
                'url' => "{$endpoint}/api/users/{$ownerUserId}/invite",
                'payloadType' => 'json',
                'payload' => [
                    'workflowId' => $workflowId,
                    'recipientEmail' => $signer->email,
                ],
            ],
            [
                'label' => 'POST /api/users/{ownerUserId}/invite workflowId+email',
                'method' => 'POST',
                'url' => "{$endpoint}/api/users/{$ownerUserId}/invite",
                'payloadType' => 'json',
                'payload' => [
                    'workflowId' => $workflowId,
                    'email' => $signer->email,
                ],
            ],
        ];

        $inviteResp = null;
        $inviteAttemptTrace = [];
        foreach ($inviteAttempts as $attempt) {
            $label = (string) ($attempt['label'] ?? 'attempt');

            try {
                $method = strtoupper((string) ($attempt['method'] ?? 'POST'));
                $url = (string) ($attempt['url'] ?? '');
                $payloadType = (string) ($attempt['payloadType'] ?? 'json');
                $payload = (array) ($attempt['payload'] ?? []);

                $maxEntityLockRetries = 4;
                $candidate = null;

                for ($retry = 1; $retry <= $maxEntityLockRetries; $retry++) {
                    if ($method === 'GET' || $payloadType === 'query') {
                        $candidate = $client->send($method, $url, ['query' => $payload]);
                    } else {
                        $candidate = $client->send($method, $url, ['json' => $payload]);
                    }

                    $candidateJson = $candidate->json();
                    $candidateApiCode = null;
                    if (is_array($candidateJson)) {
                        $candidateApiCode = $candidateJson['code']
                            ?? $candidateJson['errorCode']
                            ?? $candidateJson['error']
                            ?? null;
                    }

                    $traceLine = $label . ' => HTTP ' . $candidate->status();
                    if (is_string($candidateApiCode) && $candidateApiCode !== '') {
                        $traceLine .= ' [' . Str::limit($candidateApiCode, 60, '...') . ']';
                    }
                    if ($retry > 1) {
                        $traceLine .= ' (retry ' . $retry . '/' . $maxEntityLockRetries . ')';
                    }
                    $inviteAttemptTrace[] = $traceLine;

                    if ($candidate->successful()) {
                        break;
                    }

                    if (self::isEntityLockedResponse($candidate) && $retry < $maxEntityLockRetries) {
                        usleep(250000 * $retry);
                        continue;
                    }

                    break;
                }

                if (!$candidate) {
                    continue;
                }

                if ($candidate->successful()) {
                    $inviteResp = $candidate;
                    break;
                }

                // Si toujours verrouillé après retries, on poursuit d'autres variantes.
                if (self::isEntityLockedResponse($candidate)) {
                    $inviteResp = $candidate;
                    continue;
                }

                // Continuer sur endpoints non trouvés / méthode non supportée / payload refusé.
                if (!in_array($candidate->status(), [400, 404, 405, 415], true)) {
                    $inviteResp = $candidate;
                    break;
                }

                $inviteResp = $candidate;
            } catch (\Throwable $e) {
                $inviteAttemptTrace[] = $label . ' => EXCEPTION ' . Str::limit($e->getMessage(), 100, '...');
                Log::warning('SunnyStamp: tentative invite en exception', [
                    'workflow_id' => $workflowId,
                    'attempt_label' => $label,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $inviteAttemptSummary = implode(' | ', array_slice($inviteAttemptTrace, -12));

        if ($inviteResp && $inviteResp->successful()) {
            Log::info('SunnyStamp: réponse invite reçue', [
                'workflow_id' => $workflowId,
                'status' => $inviteResp->status(),
                'body' => $inviteResp->body(),
            ]);

            $inviteUrl = $this->extractInviteUrl($inviteResp->json(), $endpoint);

            if ((!is_string($inviteUrl) || $inviteUrl === '') && is_string($inviteResp->body())) {
                // Certaines instances renvoient une URL brute, un token brut, ou un JSON non typé.
                $inviteUrl = self::extractFirstUrlFromText((string) $inviteResp->body());

                if ((!is_string($inviteUrl) || $inviteUrl === '') && preg_match('/[A-Za-z0-9_\-]{16,}\.[A-Za-z0-9_\-]{16,}\.[A-Za-z0-9_\-]{16,}/', (string) $inviteResp->body(), $m) === 1) {
                    $inviteUrl = rtrim($endpoint, '/') . '/invite?token=' . ($m[0] ?? '');
                }
            }

            if (is_string($inviteUrl) && $inviteUrl !== '') {
                return $inviteUrl;
            }

            $this->lastPlatformError = 'invite: réponse sans URL exploitable - ' . self::formatApiErrorDetail($inviteResp)
                . ' | attempts=' . ($inviteAttemptSummary !== '' ? $inviteAttemptSummary : 'n/a');
            Log::warning('SunnyStamp: invite créé mais URL absente', [
                'status' => $inviteResp->status(),
                'body' => $inviteResp->body(),
                'attempt_summary' => $inviteAttemptSummary,
            ]);
            return null;
        }

        if (!$inviteResp) {
            $this->lastPlatformError = 'invite: aucune réponse exploitable reçue'
                . ' | attempts=' . ($inviteAttemptSummary !== '' ? $inviteAttemptSummary : 'n/a');
            Log::error('SunnyStamp: échec invite (aucune réponse)', [
                'workflow_id' => $workflowId,
                'attempt_summary' => $inviteAttemptSummary,
            ]);
            return null;
        }

        // Fallback quand les endpoints invite n'existent pas sur l'instance (404).
        if ($inviteResp->status() === 404) {
            try {
                // Logguer la réponse 404 pour diagnostic
                Log::warning('SunnyStamp: invite 404 - tentative fallback workflow details', [
                    'workflow_id' => $workflowId,
                    'last_invite_status' => $inviteResp->status(),
                    'last_invite_body' => Str::limit((string) $inviteResp->body(), 300),
                ]);

                $workflowFallbackTrace = [];

                $workflowResp = $client->get("{$endpoint}/api/workflows/{$workflowId}");
                $workflowFallbackTrace[] = 'GET /api/workflows/{workflowId} => HTTP ' . $workflowResp->status();
                if ($workflowResp->successful()) {
                    Log::info('SunnyStamp: détail workflow reçu pour extraction invite URL', [
                        'workflow_id' => $workflowId,
                        'body' => $workflowResp->body(),
                    ]);

                    $workflowUrl = $this->extractInviteUrl($workflowResp->json(), $endpoint);

                    if (is_string($workflowUrl) && $workflowUrl !== '') {
                        Log::info('SunnyStamp: URL récupérée via détail workflow', [
                            'workflow_id' => $workflowId,
                            'url' => $workflowUrl,
                        ]);
                        return $workflowUrl;
                    }
                }

                // Variante read sous espace users/{ownerId} pour instances multi-routes.
                $workflowByUserResp = $client->get("{$endpoint}/api/users/{$ownerUserId}/workflows/{$workflowId}");
                $workflowFallbackTrace[] = 'GET /api/users/{ownerUserId}/workflows/{workflowId} => HTTP ' . $workflowByUserResp->status();
                if ($workflowByUserResp->successful()) {
                    $workflowByUserUrl = $this->extractInviteUrl($workflowByUserResp->json(), $endpoint);
                    if (is_string($workflowByUserUrl) && $workflowByUserUrl !== '') {
                        Log::info('SunnyStamp: URL récupérée via détail workflow users/{id}', [
                            'workflow_id' => $workflowId,
                            'url' => $workflowByUserUrl,
                        ]);
                        return $workflowByUserUrl;
                    }
                }

                $inviteAttemptSummary .= ($inviteAttemptSummary !== '' ? ' | ' : '') . implode(' | ', $workflowFallbackTrace);
            } catch (\Throwable $e) {
                Log::warning('SunnyStamp: fallback détail workflow en exception', [
                    'workflow_id' => $workflowId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Eviter de retourner un lien approximatif qui mène à "invitation invalide".
            // On échoue explicitement pour forcer un diagnostic côté API.
            $this->lastPlatformError = 'invite: aucun lien exploitable trouvé (endpoints invite en 404 et workflow sans URL/token)'
                . ' | attempts=' . ($inviteAttemptSummary !== '' ? $inviteAttemptSummary : 'n/a');
            Log::warning('SunnyStamp: aucun lien invite exploitable trouvé après fallback', [
                'workflow_id' => $workflowId,
                'endpoint' => $endpoint,
                'attempt_summary' => $inviteAttemptSummary,
            ]);
            return null;
        }

        Log::warning('SunnyStamp: invite non créé', [
            'status' => $inviteResp->status(), 'body' => $inviteResp->body(),
            'attempt_summary' => $inviteAttemptSummary,
        ]);
        $this->lastPlatformError = 'invite: ' . self::formatApiErrorDetail($inviteResp)
            . ' | attempts=' . ($inviteAttemptSummary !== '' ? $inviteAttemptSummary : 'n/a');
        return null;
    }

    /**
     * AJAX — Obtenir le lien d'invitation vers la plateforme pour Signer / Valider.
     * POST /signatures/get-invite-url
     */
    public function getSignatureInviteUrl(Request $request): JsonResponse
    {
        $this->guardPermission('signatures.request');
        $request->validate([
            'execution_id' => 'required|string',
            'action_type'  => 'required|in:signature,validation',
        ]);

        // La plateforme de signature ne doit être appelée que pour les étapes de signature.
        if ($request->input('action_type') !== 'signature') {
            return response()->json([
                'ok' => false,
                'message' => 'Action de validation locale: aucun lien de signature requis.',
            ], 422);
        }

        $executionId = $request->input('execution_id');
        Log::info('SunnyStamp: getSignatureInviteUrl début', [
            'execution_id' => $executionId,
            'action_type' => $request->input('action_type'),
            'user_id' => Auth::id(),
        ]);

        $cfg = $this->resolveSignatureConfig();
        if (!$cfg) {
            Log::warning('SunnyStamp: aucune configuration API active pour utilisateur', [
                'user_id' => Auth::id(),
                'email' => Auth::user()?->email,
                'admin_id' => Auth::user()?->profile?->administration_id,
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Aucune configuration API Signature active pour votre administration.',
            ], 422);
        }

        $currentUser   = Auth::user();
        self::$lastLookupApiError = null;
        $platformUserId = self::resolvePlatformUserIdByEmail($cfg, $currentUser->email);
        if (!$platformUserId) {
            // Fallback: certaines plateformes n'autorisent pas la recherche utilisateur par email.
            // On utilise alors l'utilisateur owner du token API pour créer le workflow.
            $platformUserId = self::resolvePlatformOwnerUserId($cfg);
        }
        if (!$platformUserId) {
            $apiDetail = self::$lastLookupApiError
                ? ' [Erreur API: ' . self::$lastLookupApiError . ']'
                : '';
            Log::warning('SunnyStamp: compte plateforme introuvable pour utilisateur', [
                'user_id'    => $currentUser->id,
                'email'      => $currentUser->email,
                'admin_id'   => $currentUser->profile?->administration_id,
                'endpoint'   => $cfg->endpoint,
                'api_error'  => self::$lastLookupApiError,
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Impossible de trouver votre compte sur la plateforme de signature. Vérifiez que l\'adresse e-mail ' . $currentUser->email . ' est bien enregistrée sur la plateforme.' . $apiDetail,
            ], 422);
        }

        $userId    = Auth::id();
        $execution = WorkflowExecution::find($request->input('execution_id'));
        if (!$execution) {
            return response()->json(['ok' => false, 'message' => 'Exécution introuvable.'], 404);
        }

        // Vérifier que c'est le tour de l'utilisateur
        $wf     = $execution->workflow()->with('steps')->first();
        $steps  = $wf?->steps?->sortBy('order') ?? collect();
        $stepObj = $steps->firstWhere('order', (int) ($execution->current_step ?? 1));
        if ($stepObj?->assignee_id && $stepObj->assignee_id !== $userId) {
            return response()->json(['ok' => false, 'message' => 'Ce n\'est pas votre tour.'], 403);
        }

        if (!$stepObj || !(bool) ($stepObj->requires_signature ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => 'Cette étape est une validation, pas une signature.',
            ], 422);
        }

        $document = $execution->document;
        if (!$document) {
            return response()->json(['ok' => false, 'message' => 'Document introuvable.'], 404);
        }

        $inviteUrl = $this->buildPlatformInviteUrl(
            $cfg,
            $platformUserId,
            $request->input('action_type'),
            $document,
            $currentUser,
            [
                'execution_id' => $execution->id,
                'workflow_id' => $wf?->id,
                'workflow_name' => $wf?->name,
                'step_order' => (int) ($stepObj->order ?? 0),
                'step_name' => (string) ($stepObj->name ?? ''),
                'platformUserId' => $platformUserId,
            ]
        );

        if (!$inviteUrl) {
            $msg = self::toBusinessPlatformMessage($this->lastPlatformError);
            if (!empty($this->lastPlatformError)) {
                $msg .= ' Détail: ' . $this->lastPlatformError;
            }
            return response()->json([
                'ok'      => false,
                'message' => $msg,
            ], 500);
        }

        // Stocker le platform_workflow_id dans l'exécution pour le suivi de statut.
        Log::info('SunnyStamp: avant enregistrement platform_workflow_id', [
            'execution_id' => $execution->id,
            'lastPlatformWorkflowId' => $this->lastPlatformWorkflowId,
            'inviteUrl' => substr($inviteUrl ?? '', 0, 50),
            'condition' => $this->lastPlatformWorkflowId ? 'TRUE' : 'FALSE',
        ]);

        if ($this->lastPlatformWorkflowId) {
            $execution->update([
                'platform_workflow_id' => $this->lastPlatformWorkflowId,
                'platform_status'      => 'started',
            ]);
            Log::info('SunnyStamp: platform_workflow_id enregistré ✅', [
                'execution_id' => $execution->id,
                'platform_workflow_id' => $this->lastPlatformWorkflowId,
                'updated_at' => now()->toIso8601String(),
            ]);
        } else {
            Log::error('SunnyStamp: platform_workflow_id est NULL/vide ❌, pas enregistré', [
                'execution_id' => $execution->id,
                'lastPlatformWorkflowId' => $this->lastPlatformWorkflowId,
                'action_type' => $request->input('action_type'),
                'inviteUrl_exists' => is_string($inviteUrl) && $inviteUrl !== '',
            ]);
        }

        return response()->json([
            'ok'                  => true,
            'url'                 => $inviteUrl,
            'platform_workflow_id' => $this->lastPlatformWorkflowId,
        ]);
    }

    /**
     * AJAX — Récupère le statut actuel du workflow sur la plateforme de signature.
     * GET /signatures/platform-status/{execution}
     */
    public function getPlatformWorkflowStatus(string $executionId): JsonResponse
    {
        $execution = WorkflowExecution::find($executionId);
        if (!$execution) {
            return response()->json(['ok' => false, 'message' => 'Exécution introuvable.'], 404);
        }

        // Vérifier que l'utilisateur a le droit de voir cette exécution
        $wf = $execution->workflow()->with('steps')->first();
        $steps = $wf?->steps?->sortBy('order') ?? collect();
        $stepObj = $steps->firstWhere('order', (int) ($execution->current_step ?? 1));
        $userId = Auth::id();
        if ($stepObj?->assignee_id && $stepObj->assignee_id !== $userId) {
            return response()->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        // Retourner le statut local s'il n'y a pas de platform_workflow_id.
        if (empty($execution->platform_workflow_id)) {
            return response()->json([
                'ok'              => true,
                'local_status'    => $execution->status,
                'platform_status' => null,
                'phase'           => self::mapExecutionPhase($execution->status, null),
            ]);
        }

        // Interroger la plateforme pour le statut temps réel.
        $cfg = $this->resolveSignatureConfig();
        if (!$cfg) {
            return response()->json([
                'ok'              => true,
                'local_status'    => $execution->status,
                'platform_status' => $execution->platform_status,
                'phase'           => self::mapExecutionPhase($execution->status, $execution->platform_status),
            ]);
        }

        $endpoint  = rtrim((string) $cfg->endpoint, '/');
        $apiToken  = (string) ($cfg->api_key ?? $cfg->api_token ?? '');
        if ($apiToken === '') {
            Log::warning('SunnyStamp: getPlatformWorkflowStatus sans token API');
            return response()->json([
                'ok'              => true,
                'local_status'    => $execution->status,
                'platform_status' => $execution->platform_status,
                'phase'           => self::mapExecutionPhase($execution->status, $execution->platform_status),
            ]);
        }
        $client    = Http::withToken($apiToken)->timeout(10)->acceptJson();

        $platformStatus = null;
        $platformPhase  = null;
        try {
            // 1. GET /api/workflows/{wflId} → champ workflowStatus (API ARTCI/UVCI)
            $resp = $client->get("{$endpoint}/api/workflows/{$execution->platform_workflow_id}");
            Log::info('SunnyStamp: polling workflow status', [
                'execution_id' => $executionId,
                'platform_workflow_id' => $execution->platform_workflow_id,
                'http_status' => $resp->status(),
                'body_excerpt' => substr($resp->body(), 0, 300),
            ]);

            if ($resp->successful()) {
                $data = $resp->json();
                // workflowStatus est le champ principal selon la doc SunnyStamp/ARTCI.
                $platformStatus = $data['workflowStatus'] ?? $data['status'] ?? $data['state'] ?? null;
            }

            // 2. Fallback via GET /api/notifications?items.workflowId={wflId}
            //    pour détecter workflowFinished/recipientFinished même si GET /workflows ne suffit pas.
            if (!$platformStatus || !in_array(strtolower((string)$platformStatus), ['finished','completed','done','refused','rejected'], true)) {
                try {
                    $notifResp = $client->get("{$endpoint}/api/notifications", [
                        'items.workflowId' => $execution->platform_workflow_id,
                    ]);
                    if ($notifResp->successful()) {
                        $notifItems = $notifResp->json('items') ?? $notifResp->json('data') ?? ($notifResp->json() ?? []);
                        $notifItems = is_array($notifItems) ? $notifItems : [];
                        // Trier par date décroissante pour avoir l'événement le plus récent.
                        usort($notifItems, fn($a, $b) => strcmp(
                            (string)($b['createdAt'] ?? $b['date'] ?? ''),
                            (string)($a['createdAt'] ?? $a['date'] ?? '')
                        ));
                        foreach ($notifItems as $notif) {
                            $eventType = (string)($notif['eventType'] ?? $notif['type'] ?? $notif['event'] ?? '');
                            if (in_array($eventType, ['workflowFinished','workflow_finished','WORKFLOW_FINISHED'], true)) {
                                $platformStatus = 'finished';
                                Log::info('SunnyStamp: workflowFinished détecté via /notifications', [
                                    'execution_id' => $executionId,
                                    'event' => $eventType,
                                ]);
                                break;
                            }
                            if (in_array($eventType, ['recipientFinished','recipient_finished'], true)) {
                                $platformStatus = $platformStatus ?? 'signing';
                            }
                        }
                    }
                } catch (\Throwable $ne) {
                    Log::debug('SunnyStamp: /notifications non accessible', ['error' => $ne->getMessage()]);
                }
            }

            // 3. Fallback via GET /api/webhookEvents/?items.workflowId={wflId}&items.eventType=workflowFinished
            if (!$platformStatus || strtolower((string)$platformStatus) === 'started') {
                try {
                    $wbResp = $client->get("{$endpoint}/api/webhookEvents/", [
                        'items.workflowId' => $execution->platform_workflow_id,
                        'items.eventType'  => 'workflowFinished',
                    ]);
                    if ($wbResp->successful()) {
                        $wbItems = $wbResp->json('items') ?? $wbResp->json('data') ?? ($wbResp->json() ?? []);
                        if (!empty($wbItems)) {
                            $platformStatus = 'finished';
                            Log::info('SunnyStamp: workflowFinished détecté via /webhookEvents', [
                                'execution_id' => $executionId,
                            ]);
                        }
                    }
                } catch (\Throwable $we) {
                    Log::debug('SunnyStamp: /webhookEvents non accessible', ['error' => $we->getMessage()]);
                }
            }

            // 4. Appliquer les transitions locales si statut changé.
            if (is_string($platformStatus) && $platformStatus !== '' && $platformStatus !== $execution->platform_status) {
                $platformPhase = self::mapExecutionPhase($execution->status, $platformStatus);
                $updates = ['platform_status' => $platformStatus];

                $doneStatuses = ['finished', 'completed', 'done', 'FINISHED', 'COMPLETED', 'DONE'];
                $isNowDone = in_array($platformStatus, $doneStatuses, true);
                if ($isNowDone) {
                    $totalSteps = max($steps->count(), 1);
                    $currentStep = (int) ($execution->current_step ?? 1);
                    $willCompleteNow = $currentStep >= $totalSteps;

                    // Télécharger la version signée de cette étape AVANT d'avancer l'exécution.
                    $this->downloadSignedDocumentFromPlatform(
                        $execution->fresh(),
                        $endpoint,
                        $apiToken,
                        (bool) ($cfg->verify_ssl ?? true),
                        true,
                        $willCompleteNow
                    );

                    $result = $this->advanceExecutionAfterPlatformDone($execution, $platformStatus);
                    $execution->refresh();

                    Log::info('SunnyStamp: signature plateforme terminée, transition locale appliquée', [
                        'execution_id' => $executionId,
                        'platform_status' => $platformStatus,
                        'completed' => (bool) ($result['completed'] ?? false),
                        'current_step' => $execution->current_step,
                        'status' => $execution->status,
                    ]);
                } else {
                    $execution->update($updates);
                    $execution->refresh();
                }
            }

            // Vérifier aussi si déjà completed mais signed_file_path absent (rattrapage).
            if ($execution->status === 'completed' && $execution->document_id) {
                $doc = Document::find($execution->document_id);
                if ($doc && empty($doc->signed_file_path)) {
                    $this->downloadSignedDocumentFromPlatform(
                        $execution,
                        $endpoint,
                        $apiToken,
                        (bool) ($cfg->verify_ssl ?? true)
                    );
                }
            }

            $platformPhase = $platformPhase ?? self::mapExecutionPhase($execution->status, is_string($platformStatus) ? $platformStatus : null);

        } catch (\Throwable $e) {
            Log::warning('SunnyStamp: getPlatformWorkflowStatus exception', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok'                  => true,
            'local_status'        => $execution->fresh()->status,
            'platform_status'     => $platformStatus ?? $execution->platform_status,
            'platform_workflow_id' => $execution->platform_workflow_id,
            'phase'               => $platformPhase ?? self::mapExecutionPhase($execution->status, $execution->platform_status),
        ]);
    }

    /**
     * Télécharge le document signé depuis la plateforme ARTCI-Sign et le sauvegarde localement.
     * Appelé automatiquement quand le workflow passe à l'état "finished".
     * GET /api/workflows/{wflId}/downloadDocuments
     */
    private function downloadSignedDocumentFromPlatform(
        WorkflowExecution $execution,
        string $endpoint,
        string $token,
        bool $verifySSL = true,
        bool $forceRefresh = false,
        bool $markAsFinalSignature = true
    ): void
    {
        $platformWorkflowId = $execution->platform_workflow_id;
        if (!$platformWorkflowId || !$execution->document_id) {
            return;
        }

        $document = Document::find($execution->document_id);
        if (!$document) {
            return;
        }

        // Éviter de re-télécharger si déjà récupéré, sauf en mode chaînage multi-signatures.
        if (!$forceRefresh && !empty($document->signed_file_path)) {
            if ($markAsFinalSignature) {
                // En multi-étapes, le PDF signé peut déjà exister mais ne pas encore être promu dans Mes Documents.
                $this->promoteSignedPdfAsMainDocument($document, (string) $document->signed_file_path);
            }
            Log::info('SunnyStamp: document signé déjà téléchargé', [
                'execution_id' => $execution->id,
                'signed_file_path' => $document->signed_file_path,
            ]);
            return;
        }

        try {
            $downloadResp = Http::withToken($token)
                ->timeout(60)
                ->when(!$verifySSL, fn($h) => $h->withoutVerifying())
                ->get("{$endpoint}/api/workflows/{$platformWorkflowId}/downloadDocuments");

            Log::info('SunnyStamp: téléchargement document signé', [
                'execution_id'       => $execution->id,
                'platform_workflow_id' => $platformWorkflowId,
                'http_status'        => $downloadResp->status(),
                'content_type'       => $downloadResp->header('Content-Type'),
                'content_length'     => $downloadResp->header('Content-Length'),
            ]);

            if (!$downloadResp->successful()) {
                Log::error('SunnyStamp: échec téléchargement document signé', [
                    'execution_id' => $execution->id,
                    'status'       => $downloadResp->status(),
                    'body_excerpt' => substr($downloadResp->body(), 0, 300),
                ]);
                return;
            }

            $pdfContent = $downloadResp->body();
            if (empty($pdfContent) || strlen($pdfContent) < 100) {
                Log::warning('SunnyStamp: document signé vide ou trop petit', [
                    'execution_id' => $execution->id,
                    'size' => strlen($pdfContent),
                ]);
                return;
            }

            // Sauvegarder dans storage/app/public/signed_documents/
            $originalName = pathinfo($document->file_path ?? 'document.pdf', PATHINFO_FILENAME);
            $filename     = 'signed_' . $originalName . '_' . now()->format('Ymd_His') . '.pdf';
            $storagePath  = 'signed_documents/' . $filename;

            Storage::disk('public')->makeDirectory('signed_documents');
            Storage::disk('public')->put($storagePath, $pdfContent);

            // Mettre a jour le document en base.
            $documentUpdates = [
                'signed_file_path' => $storagePath,
            ];

            $document->update($documentUpdates);

            if ($markAsFinalSignature) {
                // Le PDF signe final devient la version principale visible dans Mes Documents.
                $this->promoteSignedPdfAsMainDocument($document->fresh(), $storagePath, strlen($pdfContent));
            }

            Log::info('SunnyStamp: document signé sauvegardé ✅', [
                'execution_id'     => $execution->id,
                'document_id'      => $document->id,
                'signed_file_path' => $storagePath,
                'file_size'        => strlen($pdfContent),
            ]);

        } catch (\Throwable $e) {
            Log::error('SunnyStamp: exception téléchargement document signé', [
                'execution_id' => $execution->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    private function promoteSignedPdfAsMainDocument(Document $document, string $signedPath, ?int $knownSize = null): void
    {
        $normalizedSignedPath = ltrim(str_replace('/storage/', '', $signedPath), '/');
        if ($normalizedSignedPath === '') {
            return;
        }

        $publicStoragePath = '/storage/' . $normalizedSignedPath;
        $title = (string) ($document->title ?? 'document');
        $titleWithoutExt = preg_replace('/\.(doc|docx|xls|xlsx|ppt|pptx|odt|ods|odp|pdf)$/i', '', $title) ?: $title;
        $pdfTitle = rtrim($titleWithoutExt) . '.pdf';

        $fileSize = $knownSize;
        if ($fileSize === null) {
            try {
                if (Storage::disk('public')->exists($normalizedSignedPath)) {
                    $fileSize = (int) Storage::disk('public')->size($normalizedSignedPath);
                }
            } catch (\Throwable $e) {
                Log::warning('SunnyStamp: unable to resolve signed file size during promotion', [
                    'document_id' => $document->id,
                    'signed_path' => $normalizedSignedPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $updates = [
            'title' => $pdfTitle,
            'file_path' => $publicStoragePath,
            'final_file_path' => $publicStoragePath,
            'mime_type' => 'application/pdf',
            'status' => 'signed',
            'signed_at' => now(),
        ];

        if ($fileSize !== null) {
            $updates['file_size'] = $fileSize;
        }

        $document->update($updates);
    }

    /**
     * Sert le document signé téléchargé depuis la plateforme.
     * GET /signatures/signed-document/{executionId}
     */
    public function serveSignedDocument(string $executionId): \Symfony\Component\HttpFoundation\Response
    {
        $execution = WorkflowExecution::find($executionId);
        if (!$execution) {
            abort(404, 'Exécution introuvable.');
        }

        $document = $execution->document_id ? Document::find($execution->document_id) : null;
        $signedPath = $document ? (string) ($document->signed_file_path ?: $document->final_file_path ?: '') : '';
        if (!$document || $signedPath === '') {
            abort(404, 'Document signé non disponible.');
        }

        $signedPath = ltrim(str_replace('/storage/', '', $signedPath), '/');

        if (!Storage::disk('public')->exists($signedPath)) {
            abort(404, 'Fichier introuvable sur le serveur.');
        }

        $filename = 'signed_' . Str::slug($document->title ?? 'document') . '.pdf';
        return Storage::disk('public')->download($signedPath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Webhook — Reçoit les événements de la plateforme de signature.
     * POST /api/signature/platform-webhook
     * Sans authentification session, sans CSRF, mais valider via secret HMAC si configuré.
     */
    public function platformWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('SunnyStamp: webhook reçu', [
            'payload' => $payload,
            'headers' => $request->headers->all(),
        ]);

        // Extraire l'ID du workflow plateforme depuis la payload.
        $platformWorkflowId = $payload['workflowId']
            ?? $payload['workflow_id']
            ?? ($payload['workflow']['id'] ?? null)
            ?? ($payload['data']['workflowId'] ?? null)
            ?? null;

        if (!is_string($platformWorkflowId) || $platformWorkflowId === '') {
            Log::warning('SunnyStamp: webhook sans workflowId exploitable', ['payload' => $payload]);
            return response()->json(['ok' => true, 'note' => 'no workflowId']);
        }

        $execution = WorkflowExecution::where('platform_workflow_id', $platformWorkflowId)->first();
        if (!$execution) {
            Log::warning('SunnyStamp: webhook - aucune execution locale pour platform_workflow_id', [
                'platform_workflow_id' => $platformWorkflowId,
            ]);
            return response()->json(['ok' => true, 'note' => 'execution not found']);
        }

        $event          = (string) ($payload['event'] ?? $payload['type'] ?? $payload['eventType'] ?? '');
        $platformStatus = (string) ($payload['workflowStatus']
            ?? $payload['status']
            ?? ($payload['workflow']['status'] ?? '')
            ?? ($payload['workflow']['workflowStatus'] ?? ''));

        $updates = [];
        if ($platformStatus !== '') {
            $updates['platform_status'] = $platformStatus;
        }

        // Transitions de statut locales selon l'événement plateforme.
        $doneEvents    = ['workflowFinished', 'workflow_finished', 'WORKFLOW_FINISHED'];
        $refusedEvents = ['recipientRefused', 'recipient_refused', 'RECIPIENT_REFUSED'];
        $signingEvents = ['recipientStarted', 'recipient_started', 'RECIPIENT_STARTED', 'signingStarted', 'signing_started'];
        $doneStatuses  = ['FINISHED', 'COMPLETED', 'DONE', 'finished', 'completed', 'done'];

        if (in_array($event, $doneEvents, true) || in_array($platformStatus, $doneStatuses, true)) {
            $wf = $execution->workflow()->with('steps')->first();
            $steps = $wf?->steps?->sortBy('order') ?? collect();
            $totalSteps = max($steps->count(), 1);
            $currentStep = (int) ($execution->current_step ?? 1);
            $willCompleteNow = $currentStep >= $totalSteps;

            $cfg = $this->resolveSignatureConfig();
            if ($cfg) {
                $this->downloadSignedDocumentFromPlatform(
                    $execution->fresh(),
                    rtrim((string) $cfg->endpoint, '/'),
                    (string) ($cfg->api_key ?? $cfg->api_token ?? ''),
                    (bool) ($cfg->verify_ssl ?? true),
                    true,
                    $willCompleteNow
                );
            }

            $result = $this->advanceExecutionAfterPlatformDone($execution, $platformStatus !== '' ? $platformStatus : 'finished');
            $execution->refresh();
            $updates['platform_status'] = $execution->platform_status;
            if ($execution->status === 'completed') {
                $updates['status'] = 'completed';
                $updates['completed_at'] = $execution->completed_at;
            } else {
                $updates['status'] = $execution->status;
                $updates['current_step'] = $execution->current_step;
            }
        } elseif (in_array($event, $refusedEvents, true)) {
            $updates['status'] = 'rejected';
            try {
                $wf = $execution->workflow()->with('creator')->first();
                if ($wf?->created_by) {
                    Notification::create([
                        'user_id' => $wf->created_by,
                        'type'    => 'workflow_rejected',
                        'title'   => 'Workflow de signature refusé',
                        'message' => 'Un signataire a refusé de signer le workflow « ' . ($wf->name ?? 'Sans titre') . ' ».',
                        'data'    => json_encode([
                            'execution_id'         => $execution->id,
                            'platform_workflow_id' => $platformWorkflowId,
                            'event'                => $event,
                        ]),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('SunnyStamp: webhook - notification refus échouée', ['error' => $e->getMessage()]);
            }
        } elseif (in_array($event, $signingEvents, true)) {
            // Passage à la phase de signature : mettre à jour platform_status seulement.
            if ($platformStatus === '') {
                $updates['platform_status'] = 'signing';
            }
        }

        if (!empty($updates)) {
            $execution->update($updates);
        }

        // Télécharger le document signé si le workflow vient de se terminer.
        $justCompleted = $execution->fresh()->status === 'completed';
        if ($justCompleted) {
            $cfg = $this->resolveSignatureConfig();
            if ($cfg) {
                $this->downloadSignedDocumentFromPlatform(
                    $execution->fresh(),
                    rtrim((string) $cfg->endpoint, '/'),
                    (string) ($cfg->api_key ?? $cfg->api_token ?? ''),
                    (bool) ($cfg->verify_ssl ?? true)
                );
            }
        }

        Log::info('SunnyStamp: webhook traité', [
            'platform_workflow_id' => $platformWorkflowId,
            'event'                => $event,
            'platform_status'      => $platformStatus,
            'updates'              => $updates,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Endpoint de diagnostic — check colonne platform_workflow_id dans DB
     * GET /api/signature/diag/{executionId}
     */
    public function diagExecution(string $executionId): JsonResponse
    {
        $execution = WorkflowExecution::find($executionId);
        if (!$execution) {
            return response()->json(['ok' => false, 'message' => 'Execution introuvable']);
        }

        return response()->json([
            'ok'                  => true,
            'execution_id'        => $execution->id,
            'workflow_id'         => $execution->workflow_id,
            'document_id'         => $execution->document_id,
            'current_step'        => $execution->current_step,
            'status'              => $execution->status,
            'platform_workflow_id' => $execution->platform_workflow_id,
            'platform_status'     => $execution->platform_status,
            'step_data'           => $execution->step_data,
            'started_at'          => $execution->started_at?->toIso8601String(),
            'completed_at'        => $execution->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Endpoint de diagnostic — test webhook manuellement
     */
    private static function mapExecutionPhase(string $localStatus, ?string $platformStatus): string
    {
        if ($localStatus === 'completed') {
            return 'completed';
        }
        if ($localStatus === 'rejected') {
            return 'rejected';
        }

        $ps = strtolower((string) $platformStatus);
        if (in_array($ps, ['finished', 'completed', 'done'], true)) {
            return 'completed';
        }
        if (in_array($ps, ['refused', 'rejected', 'recipient_refused'], true)) {
            return 'rejected';
        }
        if (str_contains($ps, 'sign') || $ps === 'signing') {
            return 'signing';
        }
        if (str_contains($ps, 'consent') || $ps === 'consent') {
            return 'consent';
        }
        if (in_array($ps, ['started', 'in_progress', 'inprogress'], true)) {
            return 'in_progress';
        }

        return 'pending';
    }
}
