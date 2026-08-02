<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\PersonnelEmployeesTemplateExport;
use App\Imports\PersonnelEmployeesImport;
use App\Models\AppSetting;
use App\Models\PersonnelCareerEvent;
use App\Models\PersonnelEmployeeSkill;
use App\Models\PersonnelGoal;
use App\Models\PersonnelLeaveRequest;
use App\Models\PersonnelLeaveType;
use App\Models\PersonnelJobReference;
use App\Models\PersonnelPerformanceReview;
use App\Models\PersonnelTraining;
use App\Models\PersonnelTrainingEnrollment;
use App\Models\User;
use App\Models\Document;
use App\Models\Signature;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowExecution;
use App\Models\SignatureRequest;
use App\Models\DocumentTemplate;
use App\Models\IssuingAdministration;
use App\Models\RecipientAdministration;
use App\Models\DirectionType;
use App\Models\RoutingRule;
use App\Models\AdministrationProfile;
use App\Models\SubEntity;
use App\Models\RequestedAct;
use App\Models\Instruction;
use App\Models\UserDirectionAssignment;
use App\Models\SignatureProviderConfig;
use App\Models\AdministrationSmtpSetting;
use App\Models\PersonnelEmployee;
use App\Models\PersonnelEmployeeDocument;
use App\Models\ClamAvScanLog;
use App\Services\ClamAvScanner;
use App\Services\NotificationService;
use App\Services\Templates\TemplateGenerationCoreService;
use App\Services\TemplateOfficeTextExtractor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use App\Traits\GuardsPermissions;
use Spatie\Activitylog\Models\Activity;

class AdminController extends Controller
{
    use GuardsPermissions;

    public function recipientsIndex()
    {
        return redirect()->route('admin.index', ['tab' => 'recipients']);
    }

    private function isSuperAdminProfile(?AdministrationProfile $profile): bool
    {
        if (!$profile || !is_string($profile->name)) {
            return false;
        }

        $normalized = strtoupper(trim(str_replace(['_', '-'], ' ', $profile->name)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized === 'SUPER ADMIN';
    }

    private function isAgentRhProfile(?AdministrationProfile $profile): bool
    {
        if (!$profile || !is_string($profile->name)) {
            return false;
        }

        $normalized = strtoupper(trim(str_replace(['_', '-'], ' ', $profile->name)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return str_contains($normalized, 'AGENT RH');
    }

    private function hasGlobalLeaveVisibility(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin' && !$user->profile_id) {
            return true;
        }

        $profile = $user->profile_id ? AdministrationProfile::find($user->profile_id) : null;
        return $this->isSuperAdminProfile($profile) || $this->isAgentRhProfile($profile);
    }

    private function canSearchAgentSpaceForUser(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin' && !$user->profile_id) {
            return true;
        }

        $profile = $user->profile_id ? AdministrationProfile::find($user->profile_id) : null;
        return $this->isSuperAdminProfile($profile) || $this->isAgentRhProfile($profile);
    }

    private function extractOrigin(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url(rtrim($url, '/'));
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $origin = $scheme . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    private function resolveAppPublicBaseUrl(?string $appPublicUrl, ?string $onlyofficeUrl = null): string
    {
        $basePath = rtrim(request()->getBaseUrl(), '/');
        $requestOrigin = rtrim(request()->getSchemeAndHttpHost(), '/');

        $appOrigin = $this->extractOrigin($appPublicUrl);
        $ooOrigin = $this->extractOrigin($onlyofficeUrl);

        // If app_public_url is empty or mistakenly set to the OnlyOffice server host,
        // fallback to the current application host.
        if (!$appOrigin || ($ooOrigin && strcasecmp($appOrigin, $ooOrigin) === 0)) {
            return $requestOrigin . $basePath;
        }

        $configuredPath = '';
        $appParts = parse_url(rtrim((string) $appPublicUrl, '/'));
        if (is_array($appParts) && !empty($appParts['path'])) {
            $configuredPath = rtrim((string) $appParts['path'], '/');
        }

        // Respecte le path configuré (ex: /public) si présent, sinon fallback sur le basePath de la requête.
        return $appOrigin . ($configuredPath !== '' ? $configuredPath : $basePath);
    }

    /**
     * Résout le périmètre d'administration de l'utilisateur connecté.
     *
     * Retourne ['type' => 'emitter'|'recipient', 'id' => uuid] si l'utilisateur
     * est limité à une administration, ou null si super-admin (accès total).
     */
    private function resolveAdminScope(): ?array
    {
        $user = auth()->user();
        if (!$user) return null;

        $profile = null;
        if ($user->profile_id) {
            $profile = AdministrationProfile::find($user->profile_id);
        }

        // Exception métier: profil applicatif SUPER ADMIN => accès global sans scope.
        if ($this->isSuperAdminProfile($profile)) {
            return null;
        }

        // Super-admin : rôle admin sans profil applicatif
        if ($user->role === 'admin' && !$user->profile_id) {
            return null;
        }

        // Source prioritaire : UserDirectionAssignment (contient type + id)
        $assignment = UserDirectionAssignment::where('user_id', $user->id)->first();
        if ($assignment && $assignment->direction_scope_id) {
            return [
                'type' => $assignment->direction_scope_type,
                'id'   => $assignment->direction_scope_id,
            ];
        }

        // Fallback : profil applicatif → administration associée au profil.
        if ($profile && $profile->administration_id) {
            return [
                'type' => $profile->effective_administration_type ?? 'emitter',
                'id' => $profile->administration_id,
            ];
        }

        return null;
    }

    private function normalizeAdministrationType(?string $type): string
    {
        return $type === 'recipient' ? 'recipient' : 'emitter';
    }

    private function applyPersonnelScope($query, ?array $adminScope)
    {
        if (!$adminScope) {
            return $query;
        }

        return $query->where('administration_type', $this->normalizeAdministrationType($adminScope['type'] ?? null))
            ->where('administration_id', $adminScope['id'] ?? null);
    }

    private function ensurePersonnelAdministrationExists(string $type, string $administrationId): void
    {
        $exists = $type === 'recipient'
            ? RecipientAdministration::whereKey($administrationId)->exists()
            : IssuingAdministration::whereKey($administrationId)->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'administration_id' => 'Administration sélectionnée introuvable pour ce type.',
            ]);
        }
    }

    private function abortIfPersonnelEmployeeOutsideScope(PersonnelEmployee $employee): void
    {
        $this->abortIfPersonnelScopeMismatch(
            $employee->administration_type,
            $employee->administration_id,
            'Cet employé est hors de votre périmètre d\'administration.'
        );
    }

    private function personnelScopeAllows(?string $administrationType, ?string $administrationId, ?array $adminScope): bool
    {
        if (!$adminScope) {
            return true;
        }

        return $administrationType === $this->normalizeAdministrationType($adminScope['type'] ?? null)
            && $administrationId === ($adminScope['id'] ?? null);
    }

    private function abortIfPersonnelScopeMismatch(?string $administrationType, ?string $administrationId, string $message): void
    {
        $adminScope = $this->resolveAdminScope();
        abort_unless($this->personnelScopeAllows($administrationType, $administrationId, $adminScope), 403, $message);
    }

    private function normalizeProfileName(?string $name): string
    {
        $normalized = strtoupper(trim(str_replace(['_', '-'], ' ', Str::ascii((string) $name))));
        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    private function isAnnualLeaveCode(?string $code): bool
    {
        $normalized = strtoupper(trim((string) $code));
        return in_array($normalized, ['ANNUAL', 'ANNUEL'], true);
    }

    private function isDirectorOrGeneralProfile(?string $profileName): bool
    {
        $n = $this->normalizeProfileName($profileName);
        if ($n === '') {
            return false;
        }

        if (str_contains($n, 'SOUS DIRECTEUR')) {
            return false;
        }

        return str_contains($n, 'DIRECTEUR');
    }

    private function isDrhAdministration(?string $type, ?string $administrationId): bool
    {
        if (!$type || !$administrationId) {
            return false;
        }

        $admin = $type === 'recipient'
            ? RecipientAdministration::find($administrationId)
            : IssuingAdministration::find($administrationId);

        $label = $this->normalizeProfileName($admin?->name ?? '');
        return str_contains($label, 'RESSOURCES HUMAINES') || str_contains($label, 'DRH');
    }

    private function resolveDrhScope(string $preferredType): ?array
    {
        $preferredType = $this->normalizeAdministrationType($preferredType);

        if ($preferredType === 'recipient') {
            $recipient = RecipientAdministration::query()->get()->first(function ($row) {
                $name = $this->normalizeProfileName($row->name ?? '');
                return str_contains($name, 'RESSOURCES HUMAINES') || str_contains($name, 'DRH');
            });
            if ($recipient) {
                return ['type' => 'recipient', 'id' => $recipient->id];
            }
        }

        $issuer = IssuingAdministration::query()->get()->first(function ($row) {
            $name = $this->normalizeProfileName($row->name ?? '');
            return str_contains($name, 'RESSOURCES HUMAINES') || str_contains($name, 'DRH');
        });
        if ($issuer) {
            return ['type' => 'emitter', 'id' => $issuer->id];
        }

        if ($preferredType === 'emitter') {
            $recipient = RecipientAdministration::query()->get()->first(function ($row) {
                $name = $this->normalizeProfileName($row->name ?? '');
                return str_contains($name, 'RESSOURCES HUMAINES') || str_contains($name, 'DRH');
            });
            if ($recipient) {
                return ['type' => 'recipient', 'id' => $recipient->id];
            }
        }

        return null;
    }

    private function resolveDirectorUserForScope(string $scopeType, string $scopeId): ?User
    {
        $users = UserDirectionAssignment::query()
            ->where('direction_scope_type', $scopeType)
            ->where('direction_scope_id', $scopeId)
            ->with('user.profile')
            ->get()
            ->map(fn($a) => $a->user)
            ->filter()
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return null;
        }

        $directors = $users->filter(function (User $u) {
            return $this->isDirectorOrGeneralProfile($u->profile?->name);
        })->values();

        if ($directors->isEmpty()) {
            return null;
        }

        return $directors->sortByDesc(function (User $u) {
            $p = $this->normalizeProfileName($u->profile?->name);
            return str_contains($p, 'GENERAL') ? 10 : 1;
        })->first();
    }

    private function resolveSuperiorUserId(User $user, PersonnelEmployee $referenceEmployee): ?string
    {
        if (empty($user->email)) {
            return null;
        }

        $employee = PersonnelEmployee::query()
            ->where('administration_type', $referenceEmployee->administration_type)
            ->where('administration_id', $referenceEmployee->administration_id)
            ->where('email', $user->email)
            ->first();

        if (!$employee || !$employee->user_id || $employee->user_id === $user->id) {
            return null;
        }

        return (string) $employee->user_id;
    }

    private function buildAnnualApprovalWorkflow(PersonnelEmployee $employee): array
    {
        $steps = [];
        $visited = [];
        $currentApproverId = $employee->user_id;
        $requiresDirector = true;

        for ($i = 0; $i < 8 && $currentApproverId; $i++) {
            if (isset($visited[$currentApproverId])) {
                break;
            }
            $visited[$currentApproverId] = true;

            $approver = User::with('profile')->find($currentApproverId);
            if (!$approver) {
                break;
            }

            $profileName = $approver->profile?->name;
            $steps[] = [
                'user_id' => $approver->id,
                'profile' => (string) $profileName,
                'kind' => 'hierarchy',
            ];

            if ($this->isDirectorOrGeneralProfile($profileName)) {
                if (!$this->isDrhAdministration($employee->administration_type, $employee->administration_id)) {
                    $drhScope = $this->resolveDrhScope($employee->administration_type);
                    if ($drhScope) {
                        $drhDirector = $this->resolveDirectorUserForScope($drhScope['type'], $drhScope['id']);
                        if ($drhDirector && $drhDirector->id !== $approver->id && !isset($visited[$drhDirector->id])) {
                            $steps[] = [
                                'user_id' => $drhDirector->id,
                                'profile' => (string) ($drhDirector->profile?->name ?? ''),
                                'kind' => 'drh_final',
                            ];
                        }
                    }
                }
                break;
            }

            $currentApproverId = $this->resolveSuperiorUserId($approver, $employee);
        }

        if ($requiresDirector && !empty($steps)) {
            $hasDirector = collect($steps)->contains(function (array $step) {
                return $this->isDirectorOrGeneralProfile($step['profile'] ?? null);
            });
            if (!$hasDirector) {
                return [];
            }
        }

        if (empty($steps)) {
            $drhScope = $this->resolveDrhScope($employee->administration_type);
            if ($drhScope) {
                $drhDirector = $this->resolveDirectorUserForScope($drhScope['type'], $drhScope['id']);
                if ($drhDirector) {
                    $steps[] = [
                        'user_id' => $drhDirector->id,
                        'profile' => (string) ($drhDirector->profile?->name ?? ''),
                        'kind' => 'drh_final',
                    ];
                }
            }
        }

        return $steps;
    }

    private function parseAnnualSegmentsFromRequest(Request $request): array
    {
        $raw = trim((string) $request->input('annual_segments_json', ''));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                'annual_segments_json' => 'Le format des sections de congé est invalide.',
            ]);
        }

        $segments = [];
        foreach ($decoded as $index => $segment) {
            if (!is_array($segment)) {
                continue;
            }

            $start = trim((string) ($segment['start_date'] ?? ''));
            $end = trim((string) ($segment['end_date'] ?? ''));
            if ($start === '' || $end === '') {
                continue;
            }

            try {
                $startDate = Carbon::parse($start)->startOfDay();
                $endDate = Carbon::parse($end)->startOfDay();
            } catch (\Throwable $e) {
                throw ValidationException::withMessages([
                    'annual_segments_json' => 'Une section contient une date invalide.',
                ]);
            }

            if ($endDate->lt($startDate)) {
                throw ValidationException::withMessages([
                    'annual_segments_json' => 'La date de fin d\'une section doit être postérieure à sa date de début.',
                ]);
            }

            $segments[] = [
                'index' => (int) $index,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'days' => $startDate->diffInDays($endDate) + 1,
            ];
        }

        if (count($segments) > 4) {
            throw ValidationException::withMessages([
                'annual_segments_json' => 'Le congé annuel ne peut pas dépasser 4 sections.',
            ]);
        }

        usort($segments, fn($a, $b) => strcmp($a['start_date'], $b['start_date']));
        for ($i = 1; $i < count($segments); $i++) {
            if ($segments[$i]['start_date'] <= $segments[$i - 1]['end_date']) {
                throw ValidationException::withMessages([
                    'annual_segments_json' => 'Les sections de congé se chevauchent.',
                ]);
            }
        }

        return $segments;
    }

    private function filterPersonnelActivities($activities, ?array $adminScope)
    {
        if (!$adminScope) {
            return $activities;
        }

        return $activities->filter(function (Activity $activity) use ($adminScope) {
            $subject = $activity->subject;

            if ($subject instanceof PersonnelEmployee) {
                return $this->personnelScopeAllows($subject->administration_type, $subject->administration_id, $adminScope);
            }

            if ($subject instanceof PersonnelEmployeeDocument) {
                return $subject->employee
                    ? $this->personnelScopeAllows($subject->employee->administration_type, $subject->employee->administration_id, $adminScope)
                    : false;
            }

            if ($subject instanceof PersonnelLeaveType || $subject instanceof PersonnelTraining) {
                return $this->personnelScopeAllows($subject->administration_type, $subject->administration_id, $adminScope);
            }

            if ($subject instanceof PersonnelLeaveRequest || $subject instanceof PersonnelTrainingEnrollment || $subject instanceof PersonnelEmployeeSkill || $subject instanceof PersonnelGoal || $subject instanceof PersonnelPerformanceReview || $subject instanceof PersonnelCareerEvent) {
                return $this->personnelScopeAllows($subject->administration_type, $subject->administration_id, $adminScope);
            }

            return false;
        })->values();
    }

    private function resolveProfileAdministrationSelection(array $data, ?array $adminScope): array
    {
        if ($adminScope && !empty($adminScope['id'])) {
            return [
                'administration_type' => $this->normalizeAdministrationType($adminScope['type'] ?? null),
                'administration_id' => $adminScope['id'],
            ];
        }

        $administrationId = trim((string) ($data['administration_id'] ?? ''));
        if ($administrationId === '') {
            return [
                'administration_type' => null,
                'administration_id' => null,
            ];
        }

        $administrationType = $this->normalizeAdministrationType($data['administration_type'] ?? null);
        $administrationExists = $administrationType === 'recipient'
            ? RecipientAdministration::whereKey($administrationId)->exists()
            : IssuingAdministration::whereKey($administrationId)->exists();

        if (!$administrationExists) {
            throw ValidationException::withMessages([
                'administration_id' => 'Administration sélectionnée introuvable pour ce type.',
            ]);
        }

        return [
            'administration_type' => $administrationType,
            'administration_id' => $administrationId,
        ];
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'overview');

        // Périmètre d'administration de l'utilisateur connecté (null = super-admin)
        $adminScope     = $this->resolveAdminScope();
        $connectedUser  = auth()->user();
        $agentSpaceCanSearchAll = $this->canSearchAgentSpaceForUser($connectedUser);

        // Statistiques filtrées par périmètre
        if ($adminScope) {
            $scopedStatUserIds = UserDirectionAssignment::where('direction_scope_type', $adminScope['type'])
                ->where('direction_scope_id', $adminScope['id'])
                ->pluck('user_id');
            $adminId   = $adminScope['id'];
            $isEmitter = ($adminScope['type'] === 'emitter');
            $stats = [
                'users'      => $scopedStatUserIds->count(),
                'documents'  => $isEmitter
                    ? Document::whereNull('deleted_at')->where('issuing_administration_id', $adminId)->count()
                    : Document::whereNull('deleted_at')->where('recipient_administration_id', $adminId)->count(),
                'signatures' => $isEmitter
                    ? Signature::whereHas('document', fn ($q) => $q->where('issuing_administration_id', $adminId))->count()
                    : Signature::whereHas('document', fn ($q) => $q->where('recipient_administration_id', $adminId))->count(),
                'workflows'  => Workflow::whereIn('created_by', $scopedStatUserIds)->count(),
            ];
        } else {
            $stats = [
                'users'      => User::count(),
                'documents'  => Document::whereNull('deleted_at')->count(),
                'signatures' => Signature::count(),
                'workflows'  => Workflow::count(),
            ];
        }

        $settings       = collect();
        $users          = collect();
        $templates      = collect();
        $emitters       = collect();
        $recipients     = collect();
        $directionTypes = collect();
        $subEntities    = collect();
        $requestedActs  = collect();
        $requestedActTemplates = collect();
        $routingRules   = collect();
        $profiles       = collect();
        $profilesList   = collect();
        $instructions   = collect();
        $allUsers       = collect();
        $sameAdminRoutingUsers = collect();
        $shareMap       = [];
        $onlyofficeUrl  = '';
        $onlyofficeSecret = '';
        $dirAssignments = collect();
        $sigProviders   = collect();
        $courrierArchivalDays = 0;
        $receptionArchivalDays = 0;
        $personnelEmployees = collect();
        $personnelEmployeeDirectory = collect();
        $selectedPersonnelEmployee = null;
        $personnelLeaveTypes = collect();
        $personnelLeaveRequests = collect();
        $personnelLeaveApprovers = collect();
        $personnelJobReferences = collect();
        $leaveGlobalVisibility = $this->hasGlobalLeaveVisibility(auth()->user());
        $personnelTrainings = collect();
        $personnelTrainingEnrollments = collect();
        $personnelEmployeeSkills = collect();
        $personnelGoals = collect();
        $personnelPerformanceReviews = collect();
        $personnelCareerEvents = collect();
        $personnelMutationRequests = collect();
        $personnelRecentActivity = collect();
        $scanLogs = collect();
        $personnelStats = [
            'employees' => 0,
            'documents' => 0,
            'active' => 0,
            'newThisYear' => 0,
            'leaveRequests' => 0,
            'pendingLeaveRequests' => 0,
            'trainings' => 0,
            'enrollments' => 0,
            'skills' => 0,
            'goals' => 0,
            'reviews' => 0,
            'careerEvents' => 0,
        ];

        try {
            $settings = AppSetting::all()->keyBy('key');

            // ── Journal antivirus ─────────────────────────────────────────────
            if ($tab === 'antivirus') {
                try {
                    if (Schema::hasTable('clamav_scan_logs')) {
                        $avResultFilter  = request('av_result', '');
                        $avContextFilter = request('av_context', '');
                        $avQuery = ClamAvScanLog::with('user')->orderByDesc('scanned_at');
                        if ($avResultFilter !== '') {
                            $avQuery->where('result', $avResultFilter);
                        }
                        if ($avContextFilter !== '') {
                            $avQuery->where('context', $avContextFilter);
                        }
                        $scanLogs = $avQuery->paginate(50, ['*'], 'av_page');
                    }
                } catch (\Throwable $avEx) {
                    Log::warning('Admin antivirus tab query failed', ['error' => $avEx->getMessage()]);
                }
            }

            // ── Utilisateurs ─────────────────────────────────────────────────
            $usersQuery = User::latest();
            if ($adminScope) {
                $scopedUserIds = UserDirectionAssignment::where('direction_scope_type', $adminScope['type'])
                    ->where('direction_scope_id', $adminScope['id'])
                    ->pluck('user_id');
                $usersQuery->whereIn('id', $scopedUserIds);
            }
            $users = $usersQuery->paginate(20, ['*'], 'users_page');

            // Utilisateurs de la même administration pour les cibles de routage.
            if ($adminScope) {
                $sameAdminRoutingUserIds = UserDirectionAssignment::query()
                    ->where('direction_scope_type', $adminScope['type'])
                    ->where('direction_scope_id', $adminScope['id'])
                    ->pluck('user_id');

                $sameAdminRoutingUsers = User::query()
                    ->whereIn('id', $sameAdminRoutingUserIds)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email']);
            } else {
                $sameAdminRoutingUsers = User::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'email']);
            }

            // ── Templates ────────────────────────────────────────────────────
            $tplQuery = DocumentTemplate::with(['variables', 'administration'])->latest();
            if ($adminScope && $adminScope['type'] === 'emitter') {
                $tplQuery->where('administration_id', $adminScope['id']);
            } elseif ($adminScope && $adminScope['type'] === 'recipient') {
                $tplQuery->whereNull('id'); // recipients n'ont pas de templates propres
            }
            $templates = $tplQuery->paginate(200, ['*'], 'tpl_page');

            // ── Administrations émettrices ────────────────────────────────────
            if ($adminScope && $adminScope['type'] === 'emitter') {
                $emitters = IssuingAdministration::where('id', $adminScope['id'])->get();
            } elseif ($adminScope && $adminScope['type'] === 'recipient') {
                $emitters = collect();
            } else {
                $emitters = IssuingAdministration::latest()->get();
            }

            // ── Administrations destinataires ────────────────────────────────
            if ($adminScope && $adminScope['type'] === 'recipient') {
                $recipients = RecipientAdministration::where('id', $adminScope['id'])->get();
            } else {
                $recipients = RecipientAdministration::latest()->get();
            }

            $directionTypes = DirectionType::latest()->get();

            // ── Entités sous tutelle ──────────────────────────────────────────
            $subEntitiesQuery = SubEntity::with('directionType')->latest();
            if ($adminScope) {
                $subEntitiesQuery->where('scope_type', $adminScope['type'])
                                 ->where('scope_id', $adminScope['id']);
            }
            $subEntities = $subEntitiesQuery->get();

            // Compat prod: certaines bases anciennes n'ont pas la colonne `code`.
            $emitterCodeColumn = Schema::hasColumn('issuing_administrations', 'code') ? 'code' : 'name';
            $recipientCodeColumn = Schema::hasColumn('recipient_administrations', 'code') ? 'code' : 'name';

            $emitterCodes   = IssuingAdministration::pluck($emitterCodeColumn, 'id');
            $recipientCodes = RecipientAdministration::pluck($recipientCodeColumn, 'id');
            $subEntities    = $subEntities->map(function (SubEntity $subEntity) use ($emitterCodes, $recipientCodes) {
                $adminCode = $subEntity->scope_type === 'recipient'
                    ? ($recipientCodes[$subEntity->scope_id] ?? null)
                    : ($emitterCodes[$subEntity->scope_id] ?? null);
                $subEntity->setAttribute('administration_code', $adminCode);
                return $subEntity;
            });

            // ── Actes demandés ────────────────────────────────────────────────
            $actsQuery = RequestedAct::with(['administration', 'recipientAdministration', 'autoTemplate'])->latest();
            if ($adminScope && $adminScope['type'] === 'emitter') {
                $actsQuery->where('administration_id', $adminScope['id']);
            } elseif ($adminScope && $adminScope['type'] === 'recipient') {
                $actsQuery->where('recipient_administration_id', $adminScope['id']);
            }
            $requestedActs = $actsQuery->get();

            $requestedActTemplatesQuery = DocumentTemplate::query()
                ->select('id', 'name', 'administration_id')
                ->orderBy('name');
            if ($adminScope && $adminScope['type'] === 'emitter') {
                $requestedActTemplatesQuery->where('administration_id', $adminScope['id']);
            } elseif ($adminScope && $adminScope['type'] === 'recipient') {
                $requestedActTemplatesQuery->whereRaw('1 = 0');
            }
            $requestedActTemplates = $requestedActTemplatesQuery->get();

            // ── Règles de routage ─────────────────────────────────────────────
            $routingQuery = RoutingRule::with(['template', 'recipient', 'targetUser'])->latest();
            if ($adminScope && $adminScope['type'] === 'emitter') {
                $scopedTplIds = DocumentTemplate::where('administration_id', $adminScope['id'])->pluck('id');
                $routingQuery->whereIn('template_id', $scopedTplIds);
            } elseif ($adminScope && $adminScope['type'] === 'recipient') {
                $routingQuery->where(function ($query) use ($adminScope) {
                    $query->where('recipient_id', $adminScope['id'])
                        ->orWhereHas('targetUser.directionAssignments', function ($assignmentQuery) use ($adminScope) {
                            $assignmentQuery->where('direction_scope_type', $adminScope['type'])
                                ->where('direction_scope_id', $adminScope['id']);
                        });
                });
            }
            $routingRules = $routingQuery->paginate(15, ['*'], 'routing_page');

            // ── Profils ───────────────────────────────────────────────────────
            $profilesQuery = AdministrationProfile::with(['emitterAdministration', 'recipientAdministration'])->latest();
            $profilesListQuery = AdministrationProfile::select('id', 'name', 'administration_id', 'administration_type')->orderBy('name');
            if ($adminScope && $adminScope['type'] === 'emitter') {
                $profilesQuery->where('administration_id', $adminScope['id'])
                    ->where(function ($query) {
                        $query->whereNull('administration_type')
                            ->orWhere('administration_type', 'emitter');
                    });
                $profilesListQuery->where('administration_id', $adminScope['id'])
                    ->where(function ($query) {
                        $query->whereNull('administration_type')
                            ->orWhere('administration_type', 'emitter');
                    });
            } elseif ($adminScope && $adminScope['type'] === 'recipient') {
                $profilesQuery->where('administration_type', 'recipient')
                    ->where('administration_id', $adminScope['id']);
                $profilesListQuery->where('administration_type', 'recipient')
                    ->where('administration_id', $adminScope['id']);
            }
            $profiles     = $profilesQuery->paginate(15, ['*'], 'profiles_page');
            $profilesList = $profilesListQuery->get();

            $instructions   = Instruction::latest()->get();
            $allUsers       = User::select('id','name','email')->latest()->get();
            $shareMapRaw    = AppSetting::where('key', 'template_share_map')->value('value');
            $shareMap       = json_decode($shareMapRaw ?: '{}', true) ?: [];
            $onlyofficeUrl  = AppSetting::where('key', 'onlyoffice_server_url')->value('value') ?: '';
            $onlyofficeSecret = AppSetting::where('key', 'onlyoffice_secret')->value('value') ?: '';

            // ── Assignments de direction ──────────────────────────────────────
            $dirAssignQuery = UserDirectionAssignment::query();
            if ($adminScope) {
                $dirAssignQuery->where('direction_scope_type', $adminScope['type'])
                               ->where('direction_scope_id', $adminScope['id']);
            }
            $dirAssignments = $dirAssignQuery->get()->keyBy('user_id');

            // ── Fournisseurs de signature ─────────────────────────────────────
            $sigQuery = SignatureProviderConfig::query();
            if ($adminScope && $adminScope['type'] === 'emitter') {
                $sigQuery->where('administration_id', $adminScope['id']);
            } elseif ($adminScope && $adminScope['type'] === 'recipient') {
                $sigQuery->whereNull('id');
            }
            $sigProviders = $sigQuery->get()->keyBy('administration_id');

            $courrierArchivalDays = (int) AppSetting::where('key', 'courrier_archival_days')->value('value');
            $receptionArchivalDays = (int) AppSetting::where('key', 'reception_archival_days')->value('value');

            // ── Module Gestion du personnel ──────────────────────────────────
            if (Schema::hasTable('personnel_employees')) {
                $personnelQuery = PersonnelEmployee::with(['user', 'subEntity', 'documents'])->latest();
                $this->applyPersonnelScope($personnelQuery, $adminScope);
                $personnelEmployees = $personnelQuery->paginate(15, ['*'], 'personnel_page');
                $personnelEmployeeDirectory = $this->applyPersonnelScope(
                    PersonnelEmployee::query()->orderBy('last_name')->orderBy('first_name'),
                    $adminScope
                )->get();

                $connectedPersonnelEmployee = $this->applyPersonnelScope(
                    PersonnelEmployee::with(['documents', 'user', 'linkedUser', 'subEntity'])
                        ->where('linked_user_id', $connectedUser?->id),
                    $adminScope
                )->first();

                if ($agentSpaceCanSearchAll) {
                    $selectedPersonnelEmployee = request('selected_employee')
                        ? $this->applyPersonnelScope(
                            PersonnelEmployee::with(['documents', 'user', 'linkedUser', 'subEntity'])->whereKey(request('selected_employee')),
                            $adminScope
                        )->first()
                        : null;
                } else {
                    // Hors SUPER ADMIN / AGENT RH: l'espace agent est strictement personnel.
                    $selectedPersonnelEmployee = $connectedPersonnelEmployee;
                    if ($connectedPersonnelEmployee) {
                        $personnelEmployeeDirectory = collect([$connectedPersonnelEmployee]);
                    } else {
                        $personnelEmployeeDirectory = collect();
                    }
                }

                // Par défaut, ouvrir l'espace agent sur le dossier de l'utilisateur connecté
                // (même pour les profils pouvant rechercher un autre agent).
                if (!$selectedPersonnelEmployee && $connectedPersonnelEmployee) {
                    $selectedPersonnelEmployee = $connectedPersonnelEmployee;
                }

                $personnelStats['employees'] = (clone $this->applyPersonnelScope(PersonnelEmployee::query(), $adminScope))->count();
                $personnelStats['documents'] = PersonnelEmployeeDocument::whereIn(
                    'employee_id',
                    $this->applyPersonnelScope(PersonnelEmployee::query(), $adminScope)->select('id')
                )->count();
                $personnelStats['active'] = (clone $this->applyPersonnelScope(PersonnelEmployee::query(), $adminScope))
                    ->where('employment_status', 'active')
                    ->count();
                $personnelStats['newThisYear'] = (clone $this->applyPersonnelScope(PersonnelEmployee::query(), $adminScope))
                    ->whereYear('hire_date', now()->year)
                    ->count();
            }

            if (Schema::hasTable('personnel_leave_types')) {
                $personnelLeaveTypes = $this->applyPersonnelScope(
                    PersonnelLeaveType::query()->orderByDesc('is_active')->orderBy('name'),
                    $adminScope
                )->get();
            }

            if (Schema::hasTable('personnel_leave_requests')) {
                $leaveRequestQuery = PersonnelLeaveRequest::with(['employee', 'leaveType', 'approvedBy'])->latest();
                if (!$leaveGlobalVisibility) {
                    $this->applyPersonnelScope($leaveRequestQuery, $adminScope);
                }
                $personnelLeaveRequests = $leaveRequestQuery->limit(12)->get();

                $currentApproverIds = $personnelLeaveRequests
                    ->map(fn($r) => data_get($r->metadata, 'approval_workflow.current_approver_user_id'))
                    ->filter()
                    ->unique()
                    ->values();

                if ($currentApproverIds->isNotEmpty()) {
                    $personnelLeaveApprovers = User::query()
                        ->with('profile')
                        ->whereIn('id', $currentApproverIds)
                        ->get()
                        ->keyBy('id');
                }

                if ($leaveGlobalVisibility) {
                    $personnelStats['leaveRequests'] = PersonnelLeaveRequest::query()->count();
                    $personnelStats['pendingLeaveRequests'] = PersonnelLeaveRequest::query()->where('status', 'pending')->count();
                } else {
                    $personnelStats['leaveRequests'] = (clone $this->applyPersonnelScope(PersonnelLeaveRequest::query(), $adminScope))->count();
                    $personnelStats['pendingLeaveRequests'] = (clone $this->applyPersonnelScope(PersonnelLeaveRequest::query(), $adminScope))
                        ->where('status', 'pending')
                        ->count();
                }
            }

            if (Schema::hasTable('personnel_job_references')) {
                $jobReferenceQuery = PersonnelJobReference::query()->where('is_active', true)->orderBy('reference_type')->orderBy('label');
                $this->applyPersonnelScope($jobReferenceQuery, $adminScope);
                $personnelJobReferences = $jobReferenceQuery->get();
            }

            if (Schema::hasTable('personnel_trainings')) {
                $personnelTrainings = $this->applyPersonnelScope(
                    PersonnelTraining::withCount('enrollments')->orderByDesc('is_active')->orderBy('title'),
                    $adminScope
                )->get();
                $personnelStats['trainings'] = (clone $this->applyPersonnelScope(PersonnelTraining::query(), $adminScope))->count();
            }

            if (Schema::hasTable('personnel_training_enrollments')) {
                $trainingEnrollmentQuery = PersonnelTrainingEnrollment::with(['employee', 'training'])->latest();
                $this->applyPersonnelScope($trainingEnrollmentQuery, $adminScope);
                $currentUserId = (string) (auth()->id() ?? '');
                if ($currentUserId !== '') {
                    $trainingEnrollmentQuery->orWhere(function ($query) use ($currentUserId) {
                        $query->where('status', 'pending')
                            ->whereRaw(
                                "BINARY JSON_UNQUOTE(JSON_EXTRACT(`metadata`, '$.\"approval_workflow\".\"current_approver_user_id\"')) = BINARY ?",
                                [$currentUserId]
                            );
                    });
                }
                $personnelTrainingEnrollments = $trainingEnrollmentQuery->limit(50)->get();
                $personnelStats['enrollments'] = (clone $this->applyPersonnelScope(PersonnelTrainingEnrollment::query(), $adminScope))->count();
            }

            if (Schema::hasTable('personnel_employee_skills')) {
                $skillQuery = PersonnelEmployeeSkill::with('employee')->latest();
                $this->applyPersonnelScope($skillQuery, $adminScope);
                $personnelEmployeeSkills = $skillQuery->limit(12)->get();
                $personnelStats['skills'] = (clone $this->applyPersonnelScope(PersonnelEmployeeSkill::query(), $adminScope))->count();
            }

            if (Schema::hasTable('personnel_goals')) {
                $goalQuery = PersonnelGoal::with(['employee', 'manager'])->latest();
                $this->applyPersonnelScope($goalQuery, $adminScope);
                $personnelGoals = $goalQuery->limit(12)->get();
                $personnelStats['goals'] = (clone $this->applyPersonnelScope(PersonnelGoal::query(), $adminScope))->count();
            }

            if (Schema::hasTable('personnel_performance_reviews')) {
                $reviewQuery = PersonnelPerformanceReview::with(['employee', 'reviewer'])->latest();
                $this->applyPersonnelScope($reviewQuery, $adminScope);
                $personnelPerformanceReviews = $reviewQuery->limit(12)->get();
                $personnelStats['reviews'] = (clone $this->applyPersonnelScope(PersonnelPerformanceReview::query(), $adminScope))->count();
            }

            if (Schema::hasTable('personnel_career_events')) {
                $careerEventQuery = PersonnelCareerEvent::with(['employee', 'recordedBy'])->latest();
                $this->applyPersonnelScope($careerEventQuery, $adminScope);
                $personnelCareerEvents = $careerEventQuery->limit(12)->get();

                $mutationRequestQuery = PersonnelCareerEvent::with(['employee', 'recordedBy'])
                    ->where('event_type', 'mutation_request')
                    ->latest();
                $this->applyPersonnelScope($mutationRequestQuery, $adminScope);
                $currentUserId = (string) (auth()->id() ?? '');
                if ($currentUserId !== '') {
                    $mutationRequestQuery->orWhere(function ($query) use ($currentUserId) {
                        $query->where('event_type', 'mutation_request')
                            ->where('status', 'pending')
                            ->whereRaw(
                                "BINARY JSON_UNQUOTE(JSON_EXTRACT(`metadata`, '$.\"approval_workflow\".\"current_approver_user_id\"')) = BINARY ?",
                                [$currentUserId]
                            );
                    });
                }
                $personnelMutationRequests = $mutationRequestQuery->limit(50)->get();

                $personnelStats['careerEvents'] = (clone $this->applyPersonnelScope(PersonnelCareerEvent::query(), $adminScope))->count();
            }

            if (Schema::hasTable('activity_log')) {
                $personnelRecentActivity = $this->filterPersonnelActivities(
                    Activity::with(['subject', 'causer'])
                        ->where('log_name', 'personnel')
                        ->latest()
                        ->limit(100)
                        ->get(),
                    $adminScope
                )->take(12);
            }
        } catch (\Throwable $e) {
            if (!in_array($tab, ['emitters', 'recipients'], true)) {
                throw $e;
            }

            Log::warning('Admin tab fallback due to missing dependency', [
                'tab' => $tab,
                'message' => $e->getMessage(),
            ]);

            try {
                $settings = AppSetting::all()->keyBy('key');
            } catch (\Throwable $ignored) {
                $settings = collect();
            }

            if ($tab === 'emitters') {
                try {
                    $emitters = IssuingAdministration::latest()->get();
                } catch (\Throwable $ignored) {
                    $emitters = collect();
                }
            }

            if ($tab === 'recipients') {
                try {
                    $recipients = RecipientAdministration::latest()->get();
                } catch (\Throwable $ignored) {
                    $recipients = collect();
                }
            }
        }

        // Génération du JWT OnlyOffice (HS256) sans dépendance externe
        $onlyofficeJwt = '';
        $appPublicUrl  = AppSetting::where('key', 'app_public_url')->value('value') ?: '';
        if ($onlyofficeSecret) {
            $base   = $this->resolveAppPublicBaseUrl($appPublicUrl, $onlyofficeUrl ?? '');
            $docUrl = $base . '/oo-blank/docx';
            $payload = [
                'document' => [
                    'fileType' => 'docx',
                    'key'      => 'tpl-editor-' . time(),
                    'title'    => 'Nouveau modele',
                    'url'      => $docUrl,
                    'permissions' => ['edit' => true, 'download' => false, 'print' => false],
                ],
                'documentType' => 'word',
                'editorConfig' => [
                    'mode' => 'edit',
                    'lang' => 'fr',
                    'callbackUrl' => '',
                    'user' => ['id' => 'admin-' . auth()->id(), 'name' => auth()->user()->name ?? 'Admin'],
                    'customization' => [
                        'autosave' => false,
                        'compactHeader' => true,
                        'hideRightMenu' => true,
                        'forcesave' => false,
                    ],
                ],
            ];
            $header    = rtrim(strtr(base64_encode(json_encode(['alg'=>'HS256','typ'=>'JWT'])), '+/', '-_'), '=');
            $body      = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
            $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", $onlyofficeSecret, true)), '+/', '-_'), '=');
            $onlyofficeJwt = "$header.$body.$signature";
        }

        return view('admin.index', compact(
            'tab', 'stats', 'settings', 'users',
            'templates', 'emitters', 'recipients',
            'directionTypes', 'subEntities', 'requestedActs', 'requestedActTemplates', 'routingRules', 'profiles', 'profilesList',
            'instructions',
            'allUsers', 'shareMap', 'onlyofficeUrl', 'onlyofficeJwt', 'appPublicUrl', 'dirAssignments',
            'sameAdminRoutingUsers',
            'sigProviders', 'courrierArchivalDays', 'receptionArchivalDays', 'adminScope',
            'personnelEmployees', 'personnelEmployeeDirectory', 'selectedPersonnelEmployee', 'personnelStats',
            'personnelLeaveTypes', 'personnelLeaveRequests', 'personnelLeaveApprovers', 'personnelJobReferences', 'leaveGlobalVisibility', 'personnelTrainings', 'personnelTrainingEnrollments',
            'personnelEmployeeSkills', 'personnelGoals', 'personnelPerformanceReviews', 'personnelCareerEvents', 'personnelMutationRequests', 'personnelRecentActivity',
            'agentSpaceCanSearchAll',
            'scanLogs'
        ));
    }

    private function classifyHierarchyProfile(?string $profileName): string
    {
        $normalized = $this->normalizeProfileName($profileName);
        if ($normalized === '') {
            return 'other';
        }

        if (str_contains($normalized, 'CHEF DE SERVICE')) {
            return 'chef_service';
        }
        if (str_contains($normalized, 'SOUS DIRECTEUR')) {
            return 'sous_directeur';
        }
        if (str_contains($normalized, 'DIRECTEUR DE CABINET') || $this->isDirectorOrGeneralProfile($normalized)) {
            return 'directeur';
        }

        return 'other';
    }

    private function resolveSubEntityResponsibleUserId(PersonnelEmployee $employee, string $subEntityCode): ?string
    {
        $code = strtoupper(trim($subEntityCode));
        if ($code === '') {
            return null;
        }

        $assignmentUsers = UserDirectionAssignment::query()
            ->where('direction_scope_type', $employee->administration_type)
            ->where('direction_scope_id', $employee->administration_id)
            ->whereRaw('UPPER(COALESCE(sub_entity_code, \'\')) = ?', [$code])
            ->with('user.profile')
            ->get()
            ->map(fn ($assignment) => $assignment->user)
            ->filter()
            ->unique('id')
            ->values();

        if ($assignmentUsers->isNotEmpty()) {
            $director = $assignmentUsers->first(fn (User $user) => $this->isDirectorOrGeneralProfile($user->profile?->name));
            if ($director) {
                return (string) $director->id;
            }

            return (string) $assignmentUsers->first()->id;
        }

        $subEntity = SubEntity::query()
            ->where('scope_type', $employee->administration_type)
            ->where('scope_id', $employee->administration_id)
            ->whereRaw('UPPER(COALESCE(code, \'\')) = ?', [$code])
            ->first();

        if (!$subEntity || empty($subEntity->manager_email)) {
            return null;
        }

        $managerUser = User::query()->where('email', trim((string) $subEntity->manager_email))->first();

        return $managerUser ? (string) $managerUser->id : null;
    }

    private function resolveDrhApproverUserId(PersonnelEmployee $employee): ?string
    {
        $drhSubEntity = SubEntity::query()
            ->where('scope_type', $employee->administration_type)
            ->where('scope_id', $employee->administration_id)
            ->where(function ($query) {
                $query->whereRaw('UPPER(name) LIKE ?', ['%RESSOURCES HUMAINES%'])
                    ->orWhereRaw('UPPER(name) LIKE ?', ['%DRH%']);
            })
            ->orderBy('name')
            ->first();

        if ($drhSubEntity && !empty($drhSubEntity->code)) {
            $drhUserId = $this->resolveSubEntityResponsibleUserId($employee, (string) $drhSubEntity->code);
            if ($drhUserId) {
                return $drhUserId;
            }
        }

        $drhScope = $this->resolveDrhScope($employee->administration_type);
        if (!$drhScope) {
            return null;
        }

        $drhDirector = $this->resolveDirectorUserForScope($drhScope['type'], $drhScope['id']);

        return $drhDirector ? (string) $drhDirector->id : null;
    }

    private function buildMutationApprovalWorkflow(PersonnelEmployee $employee, string $targetSubEntityCode): array
    {
        $steps = [];
        $visited = [];

        $targetManagerId = $this->resolveSubEntityResponsibleUserId($employee, $targetSubEntityCode);
        if ($targetManagerId) {
            $targetManager = User::with('profile')->find($targetManagerId);
            if ($targetManager) {
                $steps[] = [
                    'user_id' => (string) $targetManager->id,
                    'profile' => (string) ($targetManager->profile?->name ?? ''),
                    'kind' => 'target_entity_manager',
                ];
                $visited[(string) $targetManager->id] = true;
            }
        }

        $requesterUser = $employee->user_id ? User::with('profile')->find($employee->user_id) : null;
        $current = $requesterUser ? User::with('profile')->find($this->resolveSuperiorUserId($requesterUser, $employee)) : null;

        for ($i = 0; $i < 6 && $current; $i++) {
            $currentId = (string) $current->id;
            if (isset($visited[$currentId])) {
                break;
            }

            $class = $this->classifyHierarchyProfile($current->profile?->name);
            if (!in_array($class, ['chef_service', 'sous_directeur', 'directeur'], true)) {
                break;
            }

            $steps[] = [
                'user_id' => $currentId,
                'profile' => (string) ($current->profile?->name ?? ''),
                'kind' => $class,
            ];
            $visited[$currentId] = true;

            if ($class === 'directeur') {
                break;
            }

            $nextSuperiorId = $this->resolveSuperiorUserId($current, $employee);
            if (!$nextSuperiorId || isset($visited[(string) $nextSuperiorId])) {
                break;
            }

            $current = User::with('profile')->find($nextSuperiorId);
        }

        $drhApproverId = $this->resolveDrhApproverUserId($employee);
        if ($drhApproverId && !isset($visited[(string) $drhApproverId])) {
            $drhUser = User::with('profile')->find($drhApproverId);
            if ($drhUser) {
                $steps[] = [
                    'user_id' => (string) $drhUser->id,
                    'profile' => (string) ($drhUser->profile?->name ?? ''),
                    'kind' => 'drh_final',
                ];
            }
        }

        return $steps;
    }

    private function buildTrainingApprovalWorkflow(PersonnelEmployee $employee): array
    {
        $steps = [];
        $visited = [];

        $requesterUser = $employee->user_id ? User::with('profile')->find($employee->user_id) : null;
        $current = $requesterUser ? User::with('profile')->find($this->resolveSuperiorUserId($requesterUser, $employee)) : null;

        for ($i = 0; $i < 6 && $current; $i++) {
            $currentId = (string) $current->id;
            if (isset($visited[$currentId])) {
                break;
            }

            $class = $this->classifyHierarchyProfile($current->profile?->name);
            if (!in_array($class, ['chef_service', 'sous_directeur', 'directeur'], true)) {
                break;
            }

            $steps[] = [
                'user_id' => $currentId,
                'profile' => (string) ($current->profile?->name ?? ''),
                'kind' => $class,
            ];
            $visited[$currentId] = true;

            if ($class === 'directeur') {
                break;
            }

            $nextSuperiorId = $this->resolveSuperiorUserId($current, $employee);
            if (!$nextSuperiorId || isset($visited[(string) $nextSuperiorId])) {
                break;
            }

            $current = User::with('profile')->find($nextSuperiorId);
        }

        $drhApproverId = $this->resolveDrhApproverUserId($employee);
        if ($drhApproverId && !isset($visited[(string) $drhApproverId])) {
            $drhUser = User::with('profile')->find($drhApproverId);
            if ($drhUser) {
                $steps[] = [
                    'user_id' => (string) $drhUser->id,
                    'profile' => (string) ($drhUser->profile?->name ?? ''),
                    'kind' => 'drh_final',
                ];
            }
        }

        return $steps;
    }

    public function storePersonnelMutationRequest(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'agent_space_tab' => ['nullable', 'string'],
            'employee_id' => ['required', 'string'],
            'target_sub_entity_code' => ['required', 'string', 'max:100'],
            'summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,jpg,jpeg,png,zip'],
        ]);

        $employee = PersonnelEmployee::with('subEntity')->findOrFail($validated['employee_id']);
        $this->abortIfPersonnelEmployeeOutsideScope($employee);

        $actor = auth()->user();
        if (!$this->canSearchAgentSpaceForUser($actor) && (string) ($employee->linked_user_id ?? '') !== (string) ($actor?->id ?? '')) {
            throw ValidationException::withMessages([
                'employee_id' => 'Vous ne pouvez soumettre que vos propres demandes de mutation.',
            ]);
        }

        $targetSubEntity = SubEntity::query()
            ->where('scope_type', $employee->administration_type)
            ->where('scope_id', $employee->administration_id)
            ->where('code', $validated['target_sub_entity_code'])
            ->first();

        if (!$targetSubEntity) {
            throw ValidationException::withMessages([
                'target_sub_entity_code' => 'Entité de destination introuvable dans votre administration.',
            ]);
        }

        if ((string) ($employee->subEntity?->code ?? '') === (string) $targetSubEntity->code) {
            throw ValidationException::withMessages([
                'target_sub_entity_code' => 'L\'entité de destination doit être différente de l\'entité actuelle.',
            ]);
        }

        $workflowSteps = $this->buildMutationApprovalWorkflow($employee, (string) $targetSubEntity->code);
        if (empty($workflowSteps)) {
            throw ValidationException::withMessages([
                'target_sub_entity_code' => 'Circuit de validation introuvable. Vérifiez les responsables hiérarchiques.',
            ]);
        }

        $sourceSubEntity = $employee->subEntity;
        $metadata = [
            'mutation_request' => [
                'source_sub_entity_code' => (string) ($sourceSubEntity?->code ?? ''),
                'source_sub_entity_name' => (string) ($sourceSubEntity?->name ?? '-'),
                'target_sub_entity_code' => (string) $targetSubEntity->code,
                'target_sub_entity_name' => (string) $targetSubEntity->name,
                'attachments' => [],
            ],
            'approval_workflow' => [
                'type' => 'mutation_hierarchical',
                'steps' => $workflowSteps,
                'current_step_index' => 0,
                'current_approver_user_id' => $workflowSteps[0]['user_id'] ?? null,
                'history' => [],
            ],
        ];

        if ($request->hasFile('attachments')) {
            foreach ((array) $request->file('attachments') as $file) {
                if ($file && $file->isValid()) {
                    ClamAvScanner::scan($file, 'rh-mutations');
                    $path = $file->store('personnel/mutation-attachments', 'public');

                    $metadata['mutation_request']['attachments'][] = [
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ];
                }
            }
        }

        $employee->careerEvents()->create([
            'recorded_by_user_id' => auth()->id(),
            'administration_type' => $employee->administration_type,
            'administration_id' => $employee->administration_id,
            'event_type' => 'mutation_request',
            'effective_date' => null,
            'title' => 'Demande de mutation vers ' . $targetSubEntity->name,
            'previous_job_title' => (string) ($sourceSubEntity?->name ?? $employee->job_title),
            'new_job_title' => (string) $targetSubEntity->name,
            'status' => 'pending',
            'summary' => $validated['summary'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'metadata' => $metadata,
        ]);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $validated['personnel_tab'] ?? 'agent-space',
            'agent_space_tab' => $validated['agent_space_tab'] ?? 'mutation',
            'selected_employee' => $employee->id,
        ])->with('success', 'Demande de mutation envoyée pour validation.');
    }

    public function downloadPersonnelMutationRequestAttachment(PersonnelCareerEvent $event, int $attachmentIndex)
    {
        if ($event->event_type !== 'mutation_request') {
            abort(404);
        }

        $attachments = data_get($event->metadata, 'mutation_request.attachments', []);
        if (!is_array($attachments) || !isset($attachments[$attachmentIndex])) {
            abort(404);
        }

        $attachment = $attachments[$attachmentIndex];
        $relativePath = ltrim((string) ($attachment['path'] ?? ''), '/');
        $fileName = (string) ($attachment['original_name'] ?? basename($relativePath));

        abort_unless(Storage::disk('public')->exists($relativePath), 404);

        return Storage::disk('public')->download($relativePath, $fileName);
    }

    public function updatePersonnelMutationRequestStatus(Request $request, PersonnelCareerEvent $event)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'leave_subtab' => ['nullable', 'string'],
            'career_subtab' => ['nullable', 'string'],
            'status' => ['required', 'in:approved,rejected'],
            'comment' => ['nullable', 'string'],
        ]);

        if ($event->event_type !== 'mutation_request') {
            abort(404);
        }

        $event->loadMissing('employee.subEntity');
        $this->abortIfPersonnelEmployeeOutsideScope($event->employee);

        $metadata = is_array($event->metadata) ? $event->metadata : [];
        $workflow = is_array($metadata['approval_workflow'] ?? null) ? $metadata['approval_workflow'] : null;
        if (!$workflow || empty($workflow['steps'])) {
            throw ValidationException::withMessages([
                'status' => 'Circuit de validation manquant pour cette demande.',
            ]);
        }

        $steps = collect($workflow['steps'])->filter(fn ($step) => !empty($step['user_id']))->values()->all();
        $currentIndex = (int) ($workflow['current_step_index'] ?? 0);
        $currentApproverId = (string) ($workflow['current_approver_user_id'] ?? ($steps[$currentIndex]['user_id'] ?? ''));

        $actor = auth()->user();
        $actorProfile = $actor?->profile_id ? AdministrationProfile::find($actor->profile_id) : null;
        $isSuperAdmin = $this->isSuperAdminProfile($actorProfile)
            || ($actor && $actor->role === 'admin' && !$actor->profile_id);
        if ($isSuperAdmin || $this->isAgentRhProfile($actorProfile)) {
            throw ValidationException::withMessages([
                'status' => 'Profil de suivi uniquement: vous ne pouvez pas valider ni rejeter cette demande de mutation.',
            ]);
        }

        if ($currentApproverId !== '' && (string) ($actor?->id ?? '') !== $currentApproverId) {
            throw ValidationException::withMessages([
                'status' => 'Seul le valideur courant peut traiter cette demande de mutation.',
            ]);
        }

        if ($validated['status'] === 'rejected' && trim((string) ($validated['comment'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'comment' => 'Le motif du rejet est obligatoire.',
            ]);
        }

        $history = is_array($workflow['history'] ?? null) ? $workflow['history'] : [];
        $history[] = [
            'acted_by_user_id' => $actor?->id,
            'acted_at' => now()->toDateTimeString(),
            'status' => $validated['status'],
            'comment' => $validated['comment'] ?? null,
            'step_index' => $currentIndex,
            'step_profile' => $steps[$currentIndex]['profile'] ?? null,
            'step_kind' => $steps[$currentIndex]['kind'] ?? null,
        ];
        $workflow['history'] = $history;

        if ($validated['status'] === 'approved') {
            if ($currentIndex < count($steps) - 1) {
                $nextIndex = $currentIndex + 1;
                $workflow['current_step_index'] = $nextIndex;
                $workflow['current_approver_user_id'] = $steps[$nextIndex]['user_id'];
                $metadata['approval_workflow'] = $workflow;
                $event->metadata = $metadata;
                $event->status = 'pending';
                $event->save();

                return redirect()->route('admin.index', [
                    'tab' => 'personnel',
                    'personnel_tab' => $validated['personnel_tab'] ?? 'career',
                    'leave_subtab' => $validated['leave_subtab'] ?? null,
                    'career_subtab' => $validated['career_subtab'] ?? null,
                ])->with('success', 'Validation enregistrée et demande transmise au niveau suivant.');
            }

            $targetCode = (string) data_get($metadata, 'mutation_request.target_sub_entity_code', '');
            if ($targetCode !== '') {
                $targetSubEntity = SubEntity::query()
                    ->where('scope_type', $event->employee->administration_type)
                    ->where('scope_id', $event->employee->administration_id)
                    ->where('code', $targetCode)
                    ->first();
                if ($targetSubEntity) {
                    $event->employee->update(['sub_entity_id' => $targetSubEntity->id]);
                }
            }

            $workflow['current_step_index'] = count($steps) - 1;
            $workflow['current_approver_user_id'] = null;
            $metadata['approval_workflow'] = $workflow;
            $event->metadata = $metadata;
            $event->status = 'validated';
            $event->effective_date = now()->toDateString();
            $event->save();

            return redirect()->route('admin.index', [
                'tab' => 'personnel',
                'personnel_tab' => $validated['personnel_tab'] ?? 'career',
                'leave_subtab' => $validated['leave_subtab'] ?? null,
                'career_subtab' => $validated['career_subtab'] ?? null,
            ])->with('success', 'Demande de mutation validée définitivement.');
        }

        $workflow['current_approver_user_id'] = null;
        $metadata['approval_workflow'] = $workflow;
        $metadata['rejection_reason'] = $validated['comment'] ?? null;
        $event->metadata = $metadata;
        $event->status = 'rejected';
        $event->save();

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $validated['personnel_tab'] ?? 'career',
            'leave_subtab' => $validated['leave_subtab'] ?? null,
            'career_subtab' => $validated['career_subtab'] ?? null,
        ])->with('success', 'Demande de mutation rejetée.');
    }

    public function storePersonnelEmployee(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'administration_type' => ['required', 'in:emitter,recipient'],
            'administration_id' => ['required', 'string'],
            'sub_entity_id' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string', 'exists:users,id'],
            'linked_user_id' => ['nullable', 'string', 'exists:users,id'],
            'employee_number' => ['nullable', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:150'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:191'],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'employee_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->ensurePersonnelAdministrationExists($validated['administration_type'], $validated['administration_id']);

        $metadata = array_filter([
            'children_count' => $request->input('meta_children_count'),
            'nni' => $request->input('meta_nni'),
            'direction_generale' => $request->input('meta_direction_generale'),
            'direction_centrale' => $request->input('meta_direction_centrale'),
            'sous_direction' => $request->input('meta_sous_direction'),
            'service' => $request->input('meta_service'),
            'categorie' => $request->input('meta_categorie'),
            'grade' => $request->input('meta_grade'),
            'lieu_travail' => $request->input('meta_lieu_travail'),
        ], fn($v) => $v !== null && $v !== '');

        if ($request->hasFile('employee_photo')) {
            ClamAvScanner::scan($request->file('employee_photo'), 'rh-employes');
            $photoPath = $request->file('employee_photo')->store('personnel-photos', 'public');
            $metadata['photo_path'] = $photoPath;
        }

        if (!empty($metadata)) {
            $validated['metadata'] = $metadata;
        }

        PersonnelEmployee::create($validated);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'employees'),
        ])->with('success', 'Employé enregistré avec succès.');
    }

    public function updatePersonnelEmployee(Request $request, PersonnelEmployee $employee)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'administration_type' => ['required', 'in:emitter,recipient'],
            'administration_id' => ['required', 'string'],
            'sub_entity_id' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string', 'exists:users,id'],
            'linked_user_id' => ['nullable', 'string', 'exists:users,id'],
            'employee_number' => ['nullable', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:150'],
            'marital_status' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:191'],
            'hire_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'employee_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->abortIfPersonnelEmployeeOutsideScope($employee);
        $this->ensurePersonnelAdministrationExists($validated['administration_type'], $validated['administration_id']);

        $metadata = array_filter([
            'children_count' => $request->input('meta_children_count'),
            'nni' => $request->input('meta_nni'),
            'direction_generale' => $request->input('meta_direction_generale'),
            'direction_centrale' => $request->input('meta_direction_centrale'),
            'sous_direction' => $request->input('meta_sous_direction'),
            'service' => $request->input('meta_service'),
            'categorie' => $request->input('meta_categorie'),
            'grade' => $request->input('meta_grade'),
            'lieu_travail' => $request->input('meta_lieu_travail'),
        ], fn($v) => $v !== null && $v !== '');

        if ($request->hasFile('employee_photo')) {
            ClamAvScanner::scan($request->file('employee_photo'), 'rh-employes');
            $oldPhotoPath = data_get($employee->metadata, 'photo_path');
            if ($oldPhotoPath && Storage::disk('public')->exists($oldPhotoPath)) {
                Storage::disk('public')->delete($oldPhotoPath);
            }
            $photoPath = $request->file('employee_photo')->store('personnel-photos', 'public');
            $metadata['photo_path'] = $photoPath;
        }

        $validated['metadata'] = array_merge($employee->metadata ?? [], $metadata);

        $employee->update($validated);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'employees'),
            'selected_employee' => $employee->id,
        ])->with('success', 'Fiche employé mise à jour.');
    }

    public function transmitVirtualCardForSignature(Request $request, PersonnelEmployee $employee)
    {
        $this->abortIfPersonnelEmployeeOutsideScope($employee);

        $validated = $request->validate([
            'card_html' => ['required', 'string'],
            'signature_zone_page' => ['nullable', 'integer', 'min:1'],
            'signature_zone_x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'signature_zone_y' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'signature_zone_width' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'signature_zone_height' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'personnel_tab' => ['nullable', 'string'],
        ]);

        $actor = auth()->user();
        $actorProfile = $actor?->profile_id ? AdministrationProfile::find($actor->profile_id) : null;
        $isSuperAdmin = $actor?->role === 'admin';

        if (!$isSuperAdmin && !$this->isAgentRhProfile($actorProfile)) {
            abort(403, 'Seul un AGENT RH ou un SUPER ADMIN peut transmettre une carte pour signature.');
        }

        $superiorUserId = $actor ? $this->resolveSuperiorUserId($actor, $employee) : null;
        if (!$superiorUserId) {
            return redirect()->route('admin.index', [
                'tab' => 'personnel',
                'personnel_tab' => $validated['personnel_tab'] ?? 'employees',
                'selected_employee' => $employee->id,
            ])->with('error', 'Impossible de trouver le supérieur hiérarchique de l\'AGENT RH.');
        }

        $cleanCardHtml = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', (string) $validated['card_html']) ?? '';
        $documentHtml = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Carte virtuelle - ' . e($employee->full_name) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#fff;margin:0;padding:20px;display:flex;justify-content:center;}#virtual-agent-card-preview{width:100%;max-width:700px;}</style>'
            . '</head><body>' . $cleanCardHtml . '</body></html>';

        $cardFileName = 'virtual-card-' . Str::slug($employee->full_name ?: 'agent') . '-' . now()->format('YmdHis') . '.html';
        $cardFilePath = 'workflow-cards/' . $cardFileName;
        Storage::disk('public')->put($cardFilePath, $documentHtml);

        $user = auth()->user();
        $service = new TemplateGenerationCoreService();
        $docNumberData = $service->generateDocumentNumber($user);

        $document = Document::create([
            'id' => (string) Str::uuid(),
            'title' => 'Carte virtuelle - ' . ($employee->full_name ?: 'Agent'),
            'description' => 'Carte virtuelle transmise pour signature.',
            'file_path' => '/storage/' . $cardFilePath,
            'final_file_path' => '/storage/' . $cardFilePath,
            'file_size' => strlen($documentHtml),
            'mime_type' => 'text/html',
            'status' => 'draft',
            'owner_id' => (string) auth()->id(),
            'created_by' => (string) auth()->id(),
            'document_number' => $docNumberData['document_number'],
            'sub_entity_code' => $docNumberData['sub_entity_code'],
            'issuing_administration_id' => $docNumberData['issuing_administration_id'],
        ]);

        $workflow = Workflow::create([
            'id' => (string) Str::uuid(),
            'name' => 'Carte pour signature',
            'description' => 'Workflow automatique de signature de la carte virtuelle de ' . ($employee->full_name ?: 'l\'agent') . '.',
            'status' => 'active',
            'created_by' => (string) auth()->id(),
            'docs_to_sign' => [(string) $document->id],
        ]);

        WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => (string) $workflow->id,
            'order' => 1,
            'name' => 'Préparation RH',
            'type' => 'review',
            'assignee_id' => (string) auth()->id(),
            'description' => 'Préparation de la carte virtuelle',
            'requires_signature' => false,
        ]);
        WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => (string) $workflow->id,
            'order' => 2,
            'name' => 'Contrôle RH',
            'type' => 'review',
            'assignee_id' => (string) auth()->id(),
            'description' => 'Contrôle avant signature',
            'requires_signature' => false,
        ]);
        WorkflowStep::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => (string) $workflow->id,
            'order' => 3,
            'name' => 'Signature supérieur hiérarchique',
            'type' => 'sign',
            'assignee_id' => (string) $superiorUserId,
            'description' => 'Signature de la carte virtuelle',
            'requires_signature' => true,
        ]);

        $zonePage = (int) ($validated['signature_zone_page'] ?? 1);
        $zoneX = (float) ($validated['signature_zone_x'] ?? 70);
        $zoneY = (float) ($validated['signature_zone_y'] ?? 84);
        $zoneWidth = (float) ($validated['signature_zone_width'] ?? 26);
        $zoneHeight = (float) ($validated['signature_zone_height'] ?? 10);

        $docZones = [
            (string) $document->id => [
                'page' => $zonePage,
                'x' => $zoneX,
                'y' => $zoneY,
                'width' => $zoneWidth,
                'height' => $zoneHeight,
                'label' => 'Signé par',
            ],
        ];

        $execution = WorkflowExecution::create([
            'id' => (string) Str::uuid(),
            'workflow_id' => (string) $workflow->id,
            'document_id' => (string) $document->id,
            'current_step' => 3,
            'status' => 'in_progress',
            'step_data' => [
                'doc_zones' => $docZones,
                'workflow_type' => 'virtual_card_signature',
                'employee_id' => (string) $employee->id,
            ],
            'started_at' => now(),
        ]);

        SignatureRequest::create([
            'id' => (string) Str::uuid(),
            'document_id' => (string) $document->id,
            'requested_by' => (string) auth()->id(),
            'requested_to' => (string) $superiorUserId,
            'message' => 'Workflow: Carte pour signature — Étape 3: Signature supérieur hiérarchique',
            'status' => 'pending',
            'zone_page' => $zonePage,
            'zone_x' => $zoneX,
            'zone_y' => $zoneY,
            'zone_width' => $zoneWidth,
            'zone_height' => $zoneHeight,
            'zone_label' => 'Signé par',
        ]);

        NotificationService::notify(
            recipientId: (string) $superiorUserId,
            type: 'workflow',
            title: 'Carte à signer',
            message: 'Une carte virtuelle vous a été transmise pour signature.',
            actionUrl: route('signatures.index'),
            workflowId: (string) $workflow->id,
            executionId: (string) $execution->id
        );

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $validated['personnel_tab'] ?? 'employees',
            'selected_employee' => $employee->id,
        ])->with('success', 'Carte virtuelle transmise pour signature avec succès.');
    }

    public function createUserFromPersonnelEmployee(Request $request, PersonnelEmployee $employee)
    {
        $this->abortIfPersonnelEmployeeOutsideScope($employee);

        if ($employee->linked_user_id) {
            return redirect()->route('admin.index', [
                'tab' => 'personnel',
                'personnel_tab' => $request->input('personnel_tab', 'employees'),
                'selected_employee' => $employee->id,
            ])->with('error', 'Cette fiche employé est déjà liée à un compte utilisateur.');
        }

        $email = trim((string) ($employee->email ?? ''));
        if ($email === '') {
            return redirect()->route('admin.index', [
                'tab' => 'personnel',
                'personnel_tab' => $request->input('personnel_tab', 'employees'),
                'selected_employee' => $employee->id,
            ])->with('error', 'Impossible de créer le compte utilisateur sans adresse e-mail sur la fiche employé.');
        }

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser) {
            $employee->update(['linked_user_id' => $existingUser->id]);

            return redirect()->route('admin.index', [
                'tab' => 'personnel',
                'personnel_tab' => $request->input('personnel_tab', 'employees'),
                'selected_employee' => $employee->id,
            ])->with('success', 'Un compte utilisateur existant a été retrouvé via l\'e-mail et lié à la fiche employé.');
        }

        $profilesForScope = AdministrationProfile::query()
            ->where('administration_id', $employee->administration_id)
            ->where('administration_type', $employee->administration_type)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedProfile = $profilesForScope->first(function ($profile) {
            return $this->normalizeProfileName($profile->name) === 'AGENT';
        });

        if (!$selectedProfile) {
            $selectedProfile = $profilesForScope->first(function ($profile) {
                return str_contains($this->normalizeProfileName($profile->name), 'AGENT');
            });
        }

        $selectedProfileId = $selectedProfile?->id ?? $profilesForScope->first()?->id;

        $temporaryPassword = Str::random(12);
        $userPayload = [
            'name' => $employee->full_name !== '' ? $employee->full_name : trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
            'full_name' => $employee->full_name !== '' ? $employee->full_name : trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
            'email' => $email,
            'password' => Hash::make($temporaryPassword),
            'role' => 'user',
            'profile_id' => $selectedProfileId,
            'status' => 'active',
            'quota' => null,
            'locale' => 'fr',
        ];

        try {
            $user = User::create($userPayload);
        } catch (QueryException $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'unknown column') && str_contains($msg, 'locale')) {
                unset($userPayload['locale']);
                $user = User::create($userPayload);
            } else {
                throw $e;
            }
        }

        $subEntity = $employee->sub_entity_id ? SubEntity::find($employee->sub_entity_id) : null;
        $admin = $employee->administration_type === 'emitter'
            ? IssuingAdministration::find($employee->administration_id)
            : RecipientAdministration::find($employee->administration_id);

        UserDirectionAssignment::create([
            'user_id' => $user->id,
            'direction_scope_type' => $employee->administration_type,
            'direction_scope_id' => $employee->administration_id,
            'sub_entity_code' => $subEntity?->code ?? null,
            'direction_label' => $subEntity?->name ?? $admin?->name ?? '',
        ]);

        $employee->update(['linked_user_id' => $user->id]);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'employees'),
            'selected_employee' => $employee->id,
        ])->with('success', 'Compte utilisateur créé et lié à la fiche employé. Mot de passe temporaire : ' . $temporaryPassword);
    }

    public function downloadPersonnelEmployeesTemplate()
    {
        return Excel::download(new PersonnelEmployeesTemplateExport(), 'modele_import_employes.xlsx');
    }

    public function importPersonnelEmployees(Request $request)
    {
        $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'employees_file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $import = new PersonnelEmployeesImport();
        Excel::import($import, $request->file('employees_file'));

        $adminScope = $this->resolveAdminScope();
        $created = 0;
        $skipped = [];

        foreach ($import->rows as $index => $row) {
            $line = $index + 2;
            $data = collect($row)->map(function ($value) {
                return is_string($value) ? trim($value) : $value;
            });

            $firstName = (string) ($data->get('first_name') ?? '');
            $lastName = (string) ($data->get('last_name') ?? '');
            $employeeNumber = (string) ($data->get('employee_number') ?? '');

            if ($firstName === '' && $lastName === '' && $employeeNumber === '') {
                continue;
            }

            $payload = [
                'administration_type' => (string) ($data->get('administration_type') ?? ''),
                'administration_id' => (string) ($data->get('administration_id') ?? ''),
                'sub_entity_id' => (string) ($data->get('sub_entity_id') ?? ''),
                'employee_number' => $employeeNumber !== '' ? $employeeNumber : null,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'gender' => (string) ($data->get('gender') ?? ''),
                'birth_date' => (string) ($data->get('birth_date') ?? ''),
                'birth_place' => (string) ($data->get('birth_place') ?? ''),
                'marital_status' => (string) ($data->get('marital_status') ?? ''),
                'phone' => (string) ($data->get('phone') ?? ''),
                'secondary_phone' => (string) ($data->get('secondary_phone') ?? ''),
                'email' => (string) ($data->get('email') ?? ''),
                'address' => (string) ($data->get('address') ?? ''),
                'emergency_contact_name' => (string) ($data->get('emergency_contact_name') ?? ''),
                'emergency_contact_phone' => (string) ($data->get('emergency_contact_phone') ?? ''),
                'job_title' => (string) ($data->get('job_title') ?? ''),
                'hire_date' => (string) ($data->get('hire_date') ?? ''),
                'employment_status' => (string) ($data->get('employment_status') ?? 'active'),
                'notes' => (string) ($data->get('notes') ?? ''),
            ];

            foreach (['gender', 'birth_date', 'birth_place', 'marital_status', 'phone', 'secondary_phone', 'email', 'address', 'emergency_contact_name', 'emergency_contact_phone', 'job_title', 'hire_date', 'notes'] as $nullableKey) {
                if (($payload[$nullableKey] ?? '') === '') {
                    $payload[$nullableKey] = null;
                }
            }

            if (($payload['sub_entity_id'] ?? '') === '') {
                $payload['sub_entity_id'] = null;
            }

            if ($adminScope) {
                $payload['administration_type'] = $this->normalizeAdministrationType($adminScope['type'] ?? null);
                $payload['administration_id'] = $adminScope['id'];
            }

            $validator = Validator::make($payload, [
                'administration_type' => ['required', 'in:emitter,recipient'],
                'administration_id' => ['required', 'string'],
                'sub_entity_id' => ['nullable', 'exists:sub_entities,id'],
                'employee_number' => ['nullable', 'string', 'max:100'],
                'first_name' => ['required', 'string', 'max:150'],
                'last_name' => ['required', 'string', 'max:150'],
                'gender' => ['nullable', 'string', 'max:20'],
                'birth_date' => ['nullable', 'date'],
                'birth_place' => ['nullable', 'string', 'max:150'],
                'marital_status' => ['nullable', 'string', 'max:50'],
                'phone' => ['nullable', 'string', 'max:50'],
                'secondary_phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string'],
                'emergency_contact_name' => ['nullable', 'string', 'max:191'],
                'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
                'job_title' => ['nullable', 'string', 'max:191'],
                'hire_date' => ['nullable', 'date'],
                'employment_status' => ['nullable', 'in:active,probation,suspended,inactive'],
                'notes' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                $skipped[] = 'Ligne ' . $line . ': ' . $validator->errors()->first();
                continue;
            }

            try {
                $this->ensurePersonnelAdministrationExists($payload['administration_type'], $payload['administration_id']);
            } catch (ValidationException $e) {
                $skipped[] = 'Ligne ' . $line . ': administration introuvable.';
                continue;
            }

            $managerEmail = trim((string) ($data->get('superieur_hierarchique_email') ?? $data->get('manager_email') ?? ''));
            if ($managerEmail !== '') {
                $managerUser = User::query()->where('email', $managerEmail)->first();
                if (!$managerUser) {
                    $skipped[] = 'Ligne ' . $line . ': supérieur hiérarchique introuvable (' . $managerEmail . ').';
                    continue;
                }
                $payload['user_id'] = $managerUser->id;
            } else {
                $payload['user_id'] = null;
            }

            if (!empty($payload['employee_number'])) {
                $exists = PersonnelEmployee::query()
                    ->where('administration_type', $payload['administration_type'])
                    ->where('employee_number', $payload['employee_number'])
                    ->exists();

                if ($exists) {
                    $skipped[] = 'Ligne ' . $line . ': matricule déjà existant pour cette administration.';
                    continue;
                }
            }

            try {
                PersonnelEmployee::create($payload);
                $created++;
            } catch (QueryException $e) {
                $skipped[] = 'Ligne ' . $line . ': insertion impossible.';
            }
        }

        $message = $created . ' employé(s) importé(s) avec succès.';
        if (!empty($skipped)) {
            $message .= ' ' . count($skipped) . ' ligne(s) ignorée(s).';
            $errorPreview = implode(' | ', array_slice($skipped, 0, 5));

            return redirect()->route('admin.index', [
                'tab' => 'personnel',
                'personnel_tab' => $request->input('personnel_tab', 'employees'),
            ])->with('success', $message)->with('error', $errorPreview);
        }

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'employees'),
        ])->with('success', $message);
    }

    public function uploadPersonnelEmployeeDocument(Request $request, PersonnelEmployee $employee)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:191'],
            'document' => ['required', 'file', 'max:10240'],
        ]);

        $this->abortIfPersonnelEmployeeOutsideScope($employee);

        $actor = auth()->user();
        $actorProfile = $actor?->profile_id ? AdministrationProfile::find($actor->profile_id) : null;
        $isAgentRh = $this->isAgentRhProfile($actorProfile);
        $isSelfAgent = (string) ($employee->linked_user_id ?? '') === (string) ($actor?->id ?? '');

        if (!$isAgentRh && !$isSelfAgent) {
            throw ValidationException::withMessages([
                'employee_id' => 'Vous ne pouvez ajouter des documents que sur votre propre fiche depuis l\'espace agent.',
            ]);
        }

        if (($validated['personnel_tab'] ?? '') === 'agent-space' && !$isAgentRh) {
            $category = strtolower(trim((string) ($validated['category'] ?? '')));
            if ($category !== 'cv') {
                throw ValidationException::withMessages([
                    'category' => 'Depuis l\'espace agent, seul le document CV est autorisé.',
                ]);
            }
        }

        $file = $request->file('document');
        ClamAvScanner::scan($file, 'rh-documents');
        $storedName = Str::uuid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('personnel-documents/' . $employee->id, $storedName, 'local');

        $employee->documents()->create([
            'category' => $validated['category'],
            'label' => $validated['label'],
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'employees'),
            'selected_employee' => $employee->id,
        ])->with('success', 'Document ajouté à l’espace agent.');
    }

    public function downloadPersonnelEmployeeDocument(PersonnelEmployeeDocument $document)
    {
        $this->abortIfPersonnelEmployeeOutsideScope($document->employee);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function storePersonnelLeaveType(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'leave_subtab' => ['nullable', 'string'],
            'administration_type' => ['required', 'in:emitter,recipient'],
            'administration_id' => ['required', 'string'],
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'in:day,hour'],
            'default_days' => ['nullable', 'numeric', 'min:0'],
            'carry_over_days' => ['nullable', 'numeric', 'min:0'],
            'requires_attachment' => ['nullable', 'boolean'],
            'is_paid' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'justification_zip' => ['nullable', 'file', 'mimes:zip', 'max:20480'],
        ]);

        $adminScope = $this->resolveAdminScope();
        if ($adminScope) {
            $validated['administration_type'] = $this->normalizeAdministrationType($adminScope['type'] ?? null);
            $validated['administration_id'] = $adminScope['id'];
        }

        $this->ensurePersonnelAdministrationExists($validated['administration_type'], $validated['administration_id']);

        if (!empty($validated['code'])) {
            $duplicate = PersonnelLeaveType::query()
                ->where('administration_type', $validated['administration_type'])
                ->where('administration_id', $validated['administration_id'])
                ->where('code', $validated['code'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'code' => 'Un type de congé existe déjà avec ce code pour cette administration.',
                ]);
            }
        }

        $payload = [
            'administration_type' => $validated['administration_type'],
            'administration_id' => $validated['administration_id'],
            'code' => $validated['code'] ?: null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'],
            'default_days' => $validated['default_days'] ?? null,
            'carry_over_days' => $validated['carry_over_days'] ?? 0,
            'requires_attachment' => $request->boolean('requires_attachment'),
            'is_paid' => $request->boolean('is_paid', true),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->hasFile('justification_zip')) {
            $file = $request->file('justification_zip');
            ClamAvScanner::scan($file, 'rh-conges');
            $storedName = Str::uuid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('personnel-leave-type-zips/' . $validated['administration_type'] . '/' . $validated['administration_id'], $storedName, 'local');
            $payload['justification_zip_disk'] = 'local';
            $payload['justification_zip_path'] = $path;
            $payload['justification_zip_name'] = $file->getClientOriginalName();
            $payload['justification_zip_size'] = $file->getSize();
        }

        PersonnelLeaveType::create($payload);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'leave'),
            'leave_subtab' => $request->input('leave_subtab', 'parameters'),
        ])->with('success', 'Type de congé enregistré.');
    }

    public function updatePersonnelLeaveType(Request $request, PersonnelLeaveType $leaveType)
    {
        $this->abortIfPersonnelScopeMismatch(
            $leaveType->administration_type,
            $leaveType->administration_id,
            'Ce type de congé est hors de votre périmètre d\'administration.'
        );

        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'leave_subtab' => ['nullable', 'string'],
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', 'in:day,hour'],
            'default_days' => ['nullable', 'numeric', 'min:0'],
            'carry_over_days' => ['nullable', 'numeric', 'min:0'],
            'requires_attachment' => ['nullable', 'boolean'],
            'is_paid' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'justification_zip' => ['nullable', 'file', 'mimes:zip', 'max:20480'],
        ]);

        if (!empty($validated['code'])) {
            $duplicate = PersonnelLeaveType::query()
                ->where('administration_type', $leaveType->administration_type)
                ->where('administration_id', $leaveType->administration_id)
                ->where('code', $validated['code'])
                ->where('id', '!=', $leaveType->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'code' => 'Un type de congé existe déjà avec ce code pour cette administration.',
                ]);
            }
        }

        $payload = [
            'code' => $validated['code'] ?: null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'],
            'default_days' => $validated['default_days'] ?? null,
            'carry_over_days' => $validated['carry_over_days'] ?? 0,
            'requires_attachment' => $request->boolean('requires_attachment'),
            'is_paid' => $request->boolean('is_paid'),
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('justification_zip')) {
            if ($leaveType->justification_zip_disk && $leaveType->justification_zip_path && Storage::disk($leaveType->justification_zip_disk)->exists($leaveType->justification_zip_path)) {
                Storage::disk($leaveType->justification_zip_disk)->delete($leaveType->justification_zip_path);
            }

            $file = $request->file('justification_zip');
            ClamAvScanner::scan($file, 'rh-conges');
            $storedName = Str::uuid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('personnel-leave-type-zips/' . $leaveType->administration_type . '/' . $leaveType->administration_id, $storedName, 'local');
            $payload['justification_zip_disk'] = 'local';
            $payload['justification_zip_path'] = $path;
            $payload['justification_zip_name'] = $file->getClientOriginalName();
            $payload['justification_zip_size'] = $file->getSize();
        }

        $leaveType->update($payload);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'leave'),
            'leave_subtab' => $request->input('leave_subtab', 'parameters'),
        ])->with('success', 'Type de congé modifié.');
    }

    public function destroyPersonnelLeaveType(Request $request, PersonnelLeaveType $leaveType)
    {
        $this->abortIfPersonnelScopeMismatch(
            $leaveType->administration_type,
            $leaveType->administration_id,
            'Ce type de congé est hors de votre périmètre d\'administration.'
        );

        $hasRequests = PersonnelLeaveRequest::query()
            ->where('leave_type_id', $leaveType->id)
            ->exists();

        if ($hasRequests) {
            return redirect()->route('admin.index', [
                'tab' => 'personnel',
                'personnel_tab' => $request->input('personnel_tab', 'leave'),
                'leave_subtab' => $request->input('leave_subtab', 'parameters'),
            ])->with('error', 'Suppression impossible: ce type de congé est déjà utilisé dans des demandes.');
        }

        if ($leaveType->justification_zip_disk && $leaveType->justification_zip_path && Storage::disk($leaveType->justification_zip_disk)->exists($leaveType->justification_zip_path)) {
            Storage::disk($leaveType->justification_zip_disk)->delete($leaveType->justification_zip_path);
        }

        $leaveType->delete();

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'leave'),
            'leave_subtab' => $request->input('leave_subtab', 'parameters'),
        ])->with('success', 'Type de congé supprimé.');
    }

    public function storePersonnelJobReference(Request $request)
    {
        if (!Schema::hasTable('personnel_job_references')) {
            return redirect()->route('admin.index', [
                'tab' => 'personnel',
                'personnel_tab' => $request->input('personnel_tab', 'leave'),
                'leave_subtab' => $request->input('leave_subtab', 'parameters'),
            ])->with('error', 'Le référentiel métier n\'est pas encore initialisé (table personnel_job_references absente). Veuillez exécuter les migrations.');
        }

        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'leave_subtab' => ['nullable', 'string'],
            'administration_type' => ['required', 'in:emitter,recipient'],
            'administration_id' => ['required', 'string'],
            'reference_type' => ['required', 'in:grade,employment,function'],
            'label' => ['required', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $adminScope = $this->resolveAdminScope();
        if ($adminScope) {
            $validated['administration_type'] = $this->normalizeAdministrationType($adminScope['type'] ?? null);
            $validated['administration_id'] = $adminScope['id'];
        }

        $this->ensurePersonnelAdministrationExists($validated['administration_type'], $validated['administration_id']);

        PersonnelJobReference::query()->updateOrCreate(
            [
                'administration_type' => $validated['administration_type'],
                'administration_id' => $validated['administration_id'],
                'reference_type' => $validated['reference_type'],
                'label' => trim($validated['label']),
            ],
            [
                'is_active' => $request->boolean('is_active', true),
            ]
        );

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'leave'),
            'leave_subtab' => $request->input('leave_subtab', 'parameters'),
        ])->with('success', 'Référence métier enregistrée.');
    }

    public function downloadPersonnelLeaveTypeJustificationZip(PersonnelLeaveType $leaveType)
    {
        $this->abortIfPersonnelScopeMismatch(
            $leaveType->administration_type,
            $leaveType->administration_id,
            'Ce type de congé est hors de votre périmètre d\'administration.'
        );

        abort_unless($leaveType->justification_zip_disk && $leaveType->justification_zip_path, 404);
        abort_unless(Storage::disk($leaveType->justification_zip_disk)->exists($leaveType->justification_zip_path), 404);

        return Storage::disk($leaveType->justification_zip_disk)->download(
            $leaveType->justification_zip_path,
            $leaveType->justification_zip_name ?: basename($leaveType->justification_zip_path)
        );
    }

    public function downloadPersonnelLeaveRequestAttachment(PersonnelLeaveRequest $leaveRequest)
    {
        $leaveRequest->loadMissing('employee');

        if (!$this->hasGlobalLeaveVisibility(auth()->user())) {
            $this->abortIfPersonnelScopeMismatch(
                $leaveRequest->administration_type,
                $leaveRequest->administration_id,
                'Cette demande est hors de votre périmètre d\'administration.'
            );
        }

        abort_unless($leaveRequest->attachment_disk && $leaveRequest->attachment_path, 404);
        abort_unless(Storage::disk($leaveRequest->attachment_disk)->exists($leaveRequest->attachment_path), 404);

        return Storage::disk($leaveRequest->attachment_disk)->download(
            $leaveRequest->attachment_path,
            $leaveRequest->attachment_original_name ?: basename($leaveRequest->attachment_path)
        );
    }

    public function storePersonnelLeaveRequest(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'employee_id' => ['required', 'string'],
            'leave_type_id' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:end_date'],
            'requested_days' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string'],
            'unexpected_absence' => ['nullable', 'boolean'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $employee = PersonnelEmployee::findOrFail($validated['employee_id']);
        $leaveType = PersonnelLeaveType::findOrFail($validated['leave_type_id']);

        if (!$this->canSearchAgentSpaceForUser(auth()->user()) && (string) $employee->linked_user_id !== (string) auth()->id()) {
            throw ValidationException::withMessages([
                'employee_id' => 'Vous ne pouvez soumettre que vos propres demandes depuis l\'espace agent.',
            ]);
        }

        $this->abortIfPersonnelEmployeeOutsideScope($employee);
        $this->abortIfPersonnelScopeMismatch(
            $leaveType->administration_type,
            $leaveType->administration_id,
            'Ce type de congé est hors de votre périmètre d\'administration.'
        );

        if (
            $employee->administration_type !== $leaveType->administration_type
            || $employee->administration_id !== $leaveType->administration_id
        ) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'Le type de congé ne correspond pas à l’administration de l’employé.',
            ]);
        }

        if ($leaveType->requires_attachment && !$request->hasFile('attachment')) {
            throw ValidationException::withMessages([
                'attachment' => 'Une pièce justificative est obligatoire pour ce type de congé.',
            ]);
        }

        $requestedDays = $validated['requested_days'] ?? (Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) + 1);
        $metadata = [];

        $isAnnualLeave = $this->isAnnualLeaveCode($leaveType->code);
        if ($isAnnualLeave) {
            $segments = $this->parseAnnualSegmentsFromRequest($request);
            if (!empty($segments)) {
                $validated['start_date'] = $segments[0]['start_date'];
                $validated['end_date'] = $segments[count($segments) - 1]['end_date'];
                $requestedDays = collect($segments)->sum('days');
                $metadata['annual_segments'] = $segments;
                $metadata['annual_segment_count'] = count($segments);
            }

            // Validation quota annuel : ≤ 30 jours/an et ≤ solde restant
            $maxAnnualDays = 30;
            $alreadyUsedDays = (float) PersonnelLeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->whereIn('status', ['approved', 'pending'])
                ->whereYear('start_date', now()->year)
                ->sum('requested_days');
            $remainingDays = max(0, $maxAnnualDays - $alreadyUsedDays);

            if ((float) $requestedDays > $maxAnnualDays) {
                throw ValidationException::withMessages([
                    'requested_days' => "Le congé annuel ne peut pas dépasser {$maxAnnualDays} jours par an.",
                ]);
            }
            if ((float) $requestedDays > $remainingDays) {
                throw ValidationException::withMessages([
                    'requested_days' => "Solde insuffisant : il vous reste {$remainingDays} jour(s) de congé annuel disponible(s) pour cette année.",
                ]);
            }

            $workflowSteps = $this->buildAnnualApprovalWorkflow($employee);
            if (empty($workflowSteps)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Aucun valideur hiérarchique trouvé pour ce congé annuel. Vérifiez le supérieur hiérarchique.',
                ]);
            }

            $metadata['approval_workflow'] = [
                'type' => 'annual_hierarchical',
                'steps' => $workflowSteps,
                'current_step_index' => 0,
                'current_approver_user_id' => $workflowSteps[0]['user_id'] ?? null,
                'history' => [],
                'certificate_status' => 'pending_validation',
            ];
        }

        $payload = [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'requested_by_user_id' => auth()->id(),
            'administration_type' => $employee->administration_type,
            'administration_id' => $employee->administration_id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'return_date' => $validated['return_date'] ?? null,
            'requested_days' => $requestedDays,
            'status' => 'pending',
            'reason' => $validated['reason'] ?? null,
            'unexpected_absence' => $request->boolean('unexpected_absence'),
            'metadata' => !empty($metadata) ? $metadata : null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            ClamAvScanner::scan($file, 'rh-conges');
            $storedName = Str::uuid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('personnel-leaves/' . $employee->id, $storedName, 'local');
            $payload['attachment_disk'] = 'local';
            $payload['attachment_path'] = $path;
            $payload['attachment_original_name'] = $file->getClientOriginalName();
            $payload['attachment_mime_type'] = $file->getClientMimeType();
            $payload['attachment_size'] = $file->getSize();
        }

        PersonnelLeaveRequest::create($payload);

        $redirectParams = [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'agent-space'),
            'agent_space_tab' => $request->input('agent_space_tab', 'leave'),
            'leave_subtab' => $request->input('leave_subtab', 'validation'),
        ];
        if ($request->input('selected_employee')) {
            $redirectParams['selected_employee'] = $request->input('selected_employee');
        }

        return redirect()->route('admin.index', $redirectParams)->with('success', 'Demande de congé enregistrée.');
    }

    public function updatePersonnelLeaveRequestStatus(Request $request, PersonnelLeaveRequest $leaveRequest)
    {
        $this->guardPermission('personnel.leave.validation');
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'leave_subtab' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,approved,rejected,cancelled'],
            'approved_days' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string'],
        ]);

        $this->abortIfPersonnelScopeMismatch(
            $leaveRequest->administration_type,
            $leaveRequest->administration_id,
            'Cette demande est hors de votre périmètre d\'administration.'
        );

        $leaveRequest->loadMissing(['leaveType', 'employee']);
        $isAnnualLeave = $this->isAnnualLeaveCode($leaveRequest->leaveType?->code);

        if ($isAnnualLeave) {
            $metadata = is_array($leaveRequest->metadata) ? $leaveRequest->metadata : [];
            $workflow = is_array($metadata['approval_workflow'] ?? null) ? $metadata['approval_workflow'] : null;

            if ($workflow && !empty($workflow['steps']) && in_array($validated['status'], ['approved', 'rejected', 'pending', 'cancelled'], true)) {
                $steps = collect($workflow['steps'])->filter(fn($s) => !empty($s['user_id']))->values()->all();
                $currentIndex = (int) ($workflow['current_step_index'] ?? 0);
                $currentApproverId = (string) ($workflow['current_approver_user_id'] ?? ($steps[$currentIndex]['user_id'] ?? ''));
                $actor = auth()->user();
                $canBypass = $actor && $actor->role === 'admin' && !$actor->profile_id;

                if (!$canBypass && $currentApproverId !== '' && (string) $actor?->id !== $currentApproverId) {
                    throw ValidationException::withMessages([
                        'status' => 'Seul le valideur courant peut traiter cette demande.',
                    ]);
                }

                $history = is_array($workflow['history'] ?? null) ? $workflow['history'] : [];
                $history[] = [
                    'acted_by_user_id' => $actor?->id,
                    'acted_at' => now()->toDateTimeString(),
                    'status' => $validated['status'],
                    'comment' => $validated['comment'] ?? null,
                    'step_index' => $currentIndex,
                    'step_profile' => $steps[$currentIndex]['profile'] ?? null,
                ];

                $workflow['history'] = $history;

                if ($validated['status'] === 'approved') {
                    if ($currentIndex < count($steps) - 1) {
                        $nextIndex = $currentIndex + 1;
                        $workflow['current_step_index'] = $nextIndex;
                        $workflow['current_approver_user_id'] = $steps[$nextIndex]['user_id'];
                        $metadata['approval_workflow'] = $workflow;
                        $leaveRequest->metadata = $metadata;
                        $leaveRequest->status = 'pending';
                        $leaveRequest->approved_at = null;
                        $leaveRequest->rejected_at = null;
                        $leaveRequest->approved_by_user_id = null;
                        $leaveRequest->approved_days = null;
                        $leaveRequest->manager_comments = $validated['comment'] ?? $leaveRequest->manager_comments;
                        $leaveRequest->save();

                        return redirect()->route('admin.index', [
                            'tab' => 'personnel',
                            'personnel_tab' => $request->input('personnel_tab', 'leave'),
                            'leave_subtab' => $request->input('leave_subtab', 'validation'),
                        ])->with('success', 'Validation enregistrée et demande transmise au niveau suivant.');
                    }

                    $workflow['certificate_status'] = 'pending_generation';
                    $metadata['approval_workflow'] = $workflow;
                    $leaveRequest->metadata = $metadata;
                } else {
                    $workflow['certificate_status'] = 'not_applicable';
                    $metadata['approval_workflow'] = $workflow;
                    $leaveRequest->metadata = $metadata;
                }
            }
        }

        $leaveRequest->status = $validated['status'];
        $leaveRequest->hr_comments = $validated['comment'] ?? $leaveRequest->hr_comments;

        if ($validated['status'] === 'approved') {
            $leaveRequest->approved_by_user_id = auth()->id();
            $leaveRequest->approved_at = now();
            $leaveRequest->rejected_at = null;
            $leaveRequest->approved_days = $validated['approved_days'] ?? $leaveRequest->requested_days;
        } elseif ($validated['status'] === 'rejected') {
            $leaveRequest->approved_by_user_id = auth()->id();
            $leaveRequest->rejected_at = now();
            $leaveRequest->approved_at = null;
            $leaveRequest->approved_days = null;
        } else {
            $leaveRequest->approved_at = null;
            $leaveRequest->rejected_at = null;
            $leaveRequest->approved_days = null;
            if ($validated['status'] !== 'pending') {
                $leaveRequest->approved_by_user_id = auth()->id();
            }
        }

        $leaveRequest->save();

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'leave'),
            'leave_subtab' => $request->input('leave_subtab', 'validation'),
        ])->with('success', 'Statut de la demande mis à jour.');
    }

    public function storePersonnelTraining(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'administration_type' => ['required', 'in:emitter,recipient'],
            'administration_id' => ['required', 'string'],
            'code' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:100'],
            'provider_name' => ['nullable', 'string', 'max:191'],
            'delivery_mode' => ['required', 'in:internal,external,elearning,hybrid'],
            'duration_hours' => ['nullable', 'numeric', 'min:0'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'validity_months' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'objectives' => ['nullable', 'string'],
            'skills' => ['nullable', 'string'],
            'is_mandatory' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $adminScope = $this->resolveAdminScope();
        if ($adminScope) {
            $validated['administration_type'] = $this->normalizeAdministrationType($adminScope['type'] ?? null);
            $validated['administration_id'] = $adminScope['id'];
        }

        $this->ensurePersonnelAdministrationExists($validated['administration_type'], $validated['administration_id']);

        if (!empty($validated['code'])) {
            $duplicate = PersonnelTraining::query()
                ->where('administration_type', $validated['administration_type'])
                ->where('administration_id', $validated['administration_id'])
                ->where('code', $validated['code'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'code' => 'Une formation existe déjà avec ce code pour cette administration.',
                ]);
            }
        }

        $skills = collect(explode(',', (string) ($validated['skills'] ?? '')))
            ->map(fn (string $skill) => trim($skill))
            ->filter()
            ->values()
            ->all();

        PersonnelTraining::create([
            'administration_type' => $validated['administration_type'],
            'administration_id' => $validated['administration_id'],
            'code' => $validated['code'] ?: null,
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'provider_name' => $validated['provider_name'] ?? null,
            'delivery_mode' => $validated['delivery_mode'],
            'duration_hours' => $validated['duration_hours'] ?? null,
            'budget_amount' => $validated['budget_amount'] ?? null,
            'validity_months' => $validated['validity_months'] ?? null,
            'description' => $validated['description'] ?? null,
            'objectives' => $validated['objectives'] ?? null,
            'skills' => $skills,
            'is_mandatory' => $request->boolean('is_mandatory'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'training'),
        ])->with('success', 'Formation enregistrée.');
    }

    public function storePersonnelTrainingEnrollment(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'employee_id' => ['required', 'string'],
            'training_id' => ['required', 'string'],
            'status' => ['required', 'in:planned,in_progress,completed,cancelled'],
            'planned_start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'attendance_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'satisfaction_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'certificate' => ['nullable', 'file', 'max:10240'],
        ]);

        $employee = PersonnelEmployee::findOrFail($validated['employee_id']);
        $training = PersonnelTraining::findOrFail($validated['training_id']);

        $this->abortIfPersonnelEmployeeOutsideScope($employee);
        $this->abortIfPersonnelScopeMismatch(
            $training->administration_type,
            $training->administration_id,
            'Cette formation est hors de votre périmètre d\'administration.'
        );

        if (
            $employee->administration_type !== $training->administration_type
            || $employee->administration_id !== $training->administration_id
        ) {
            throw ValidationException::withMessages([
                'training_id' => 'La formation ne correspond pas à l’administration de l’employé.',
            ]);
        }

        $exists = PersonnelTrainingEnrollment::query()
            ->where('employee_id', $employee->id)
            ->where('training_id', $training->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'training_id' => 'Cette formation est déjà affectée à cet employé.',
            ]);
        }

        $payload = [
            'employee_id' => $employee->id,
            'training_id' => $training->id,
            'assigned_by_user_id' => auth()->id(),
            'administration_type' => $employee->administration_type,
            'administration_id' => $employee->administration_id,
            'status' => $validated['status'],
            'planned_start_date' => $validated['planned_start_date'] ?? null,
            'planned_end_date' => $validated['planned_end_date'] ?? null,
            'started_at' => $validated['status'] === 'in_progress' ? now() : null,
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
            'attendance_rate' => $validated['attendance_rate'] ?? null,
            'score' => $validated['score'] ?? null,
            'satisfaction_score' => $validated['satisfaction_score'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $isAgentRequest = ($request->input('personnel_tab') === 'agent-space');
        if ($isAgentRequest) {
            $workflowSteps = $this->buildTrainingApprovalWorkflow($employee);
            if (empty($workflowSteps)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Circuit de validation introuvable pour cette demande de formation.',
                ]);
            }

            $payload['status'] = 'pending';
            $payload['metadata'] = [
                'approval_workflow' => [
                    'type' => 'training_hierarchical',
                    'steps' => $workflowSteps,
                    'current_step_index' => 0,
                    'current_approver_user_id' => $workflowSteps[0]['user_id'] ?? null,
                    'history' => [],
                ],
            ];
        }

        if ($request->hasFile('certificate')) {
            $file = $request->file('certificate');
            ClamAvScanner::scan($file, 'rh-formations');
            $storedName = Str::uuid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $path = $file->storeAs('personnel-training/' . $employee->id, $storedName, 'local');
            $payload['certificate_disk'] = 'local';
            $payload['certificate_path'] = $path;
            $payload['certificate_original_name'] = $file->getClientOriginalName();
            $payload['certificate_mime_type'] = $file->getClientMimeType();
            $payload['certificate_size'] = $file->getSize();
        }

        PersonnelTrainingEnrollment::create($payload);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'training'),
        ])->with('success', $isAgentRequest ? 'Demande de formation transmise pour validation.' : 'Formation affectée à l’employé.');
    }


    public function updatePersonnelTrainingEnrollmentStatus(Request $request, string $enrollmentId)
    {
        $this->guardPermission('personnel.training');
        $enrollment = PersonnelTrainingEnrollment::with('employee')->findOrFail($enrollmentId);
        $this->abortIfPersonnelEmployeeOutsideScope($enrollment->employee);

        $metadata = is_array($enrollment->metadata) ? $enrollment->metadata : [];
        $workflow = is_array($metadata['approval_workflow'] ?? null) ? $metadata['approval_workflow'] : null;

        if ($workflow && ($workflow['type'] ?? '') === 'training_hierarchical') {
            $validated = $request->validate([
                'status' => ['required', 'in:approved,rejected'],
                'comment' => ['nullable', 'string'],
                'personnel_tab' => ['nullable', 'string'],
                'leave_subtab' => ['nullable', 'string'],
                'training_subtab' => ['nullable', 'string'],
            ]);

            $steps = collect($workflow['steps'] ?? [])->filter(fn ($step) => !empty($step['user_id']))->values()->all();
            if (empty($steps)) {
                throw ValidationException::withMessages([
                    'status' => 'Circuit de validation manquant pour cette demande.',
                ]);
            }

            $currentIndex = (int) ($workflow['current_step_index'] ?? 0);
            $currentApproverId = (string) ($workflow['current_approver_user_id'] ?? ($steps[$currentIndex]['user_id'] ?? ''));
            $actor = auth()->user();
            if ($currentApproverId === '' || (string) ($actor?->id ?? '') !== $currentApproverId) {
                throw ValidationException::withMessages([
                    'status' => 'Seul le valideur courant peut traiter cette demande de formation.',
                ]);
            }

            if ($validated['status'] === 'rejected' && trim((string) ($validated['comment'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'comment' => 'Le motif du rejet est obligatoire.',
                ]);
            }

            $history = is_array($workflow['history'] ?? null) ? $workflow['history'] : [];
            $history[] = [
                'acted_by_user_id' => $actor?->id,
                'acted_at' => now()->toDateTimeString(),
                'status' => $validated['status'],
                'comment' => $validated['comment'] ?? null,
                'step_index' => $currentIndex,
                'step_profile' => $steps[$currentIndex]['profile'] ?? null,
                'step_kind' => $steps[$currentIndex]['kind'] ?? null,
            ];
            $workflow['history'] = $history;

            if ($validated['status'] === 'approved') {
                if ($currentIndex < count($steps) - 1) {
                    $nextIndex = $currentIndex + 1;
                    $workflow['current_step_index'] = $nextIndex;
                    $workflow['current_approver_user_id'] = $steps[$nextIndex]['user_id'];
                    $metadata['approval_workflow'] = $workflow;
                    $enrollment->metadata = $metadata;
                    $enrollment->status = 'pending';
                    $enrollment->save();

                    return redirect()->route('admin.index', [
                        'tab' => 'personnel',
                        'personnel_tab' => $validated['personnel_tab'] ?? 'training',
                        'leave_subtab' => $validated['leave_subtab'] ?? null,
                        'training_subtab' => $validated['training_subtab'] ?? null,
                    ])->with('success', 'Validation enregistrée et demande transmise au niveau suivant.');
                }

                $workflow['current_step_index'] = count($steps) - 1;
                $workflow['current_approver_user_id'] = null;
                $metadata['approval_workflow'] = $workflow;
                $enrollment->metadata = $metadata;
                $enrollment->status = 'planned';
                $enrollment->save();

                return redirect()->route('admin.index', [
                    'tab' => 'personnel',
                    'personnel_tab' => $validated['personnel_tab'] ?? 'training',
                    'leave_subtab' => $validated['leave_subtab'] ?? null,
                    'training_subtab' => $validated['training_subtab'] ?? null,
                ])->with('success', 'Demande de formation validée définitivement.');
            }

            $workflow['current_approver_user_id'] = null;
            $metadata['approval_workflow'] = $workflow;
            $metadata['rejection_reason'] = $validated['comment'] ?? null;
            $enrollment->metadata = $metadata;
            $enrollment->status = 'rejected';
            $enrollment->save();

            return redirect()->route('admin.index', [
                'tab' => 'personnel',
                'personnel_tab' => $validated['personnel_tab'] ?? 'training',
                'leave_subtab' => $validated['leave_subtab'] ?? null,
                'training_subtab' => $validated['training_subtab'] ?? null,
            ])->with('success', 'Demande de formation rejetée.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:planned,in_progress,completed,cancelled'],
            'personnel_tab' => ['nullable', 'string'],
        ]);

        // Seul l'AGENT RH ou le SUPER ADMIN peut changer le statut hors workflow
        $actor = auth()->user();
        $actorProfile = $actor?->profile_id ? AdministrationProfile::find($actor->profile_id) : null;
        $isSuperAdmin = $actor?->role === 'admin';
        if (!$this->isAgentRhProfile($actorProfile) && !$isSuperAdmin) {
            abort(403, 'Seul un AGENT RH ou un SUPER ADMIN peut modifier le statut d\'une demande de formation.');
        }

        $enrollment->update(['status' => $validated['status']]);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $validated['personnel_tab'] ?? 'training',
        ])->with('success', 'Statut de la demande de formation mis a jour.');
    }
    public function storePersonnelEmployeeSkill(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'employee_id' => ['required', 'string'],
            'skill_name' => ['required', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:100'],
            'current_level' => ['required', 'integer', 'min:1', 'max:5'],
            'target_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'assessment_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $employee = PersonnelEmployee::findOrFail($validated['employee_id']);

        $this->abortIfPersonnelEmployeeOutsideScope($employee);

        $duplicate = $employee->skills()
            ->whereRaw('LOWER(skill_name) = ?', [mb_strtolower($validated['skill_name'])])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'skill_name' => 'Cette compétence existe déjà pour cet employé.',
            ]);
        }

        $employee->skills()->create([
            'administration_type' => $employee->administration_type,
            'administration_id' => $employee->administration_id,
            'skill_name' => $validated['skill_name'],
            'category' => $validated['category'] ?? null,
            'current_level' => $validated['current_level'],
            'target_level' => $validated['target_level'] ?? null,
            'assessment_date' => $validated['assessment_date'] ?? null,
            'source' => $validated['source'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'training'),
            'selected_employee' => $employee->id,
        ])->with('success', 'Compétence ajoutée à la fiche agent.');
    }

    public function storePersonnelGoal(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'employee_id' => ['required', 'string'],
            'manager_user_id' => ['nullable', 'string'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'goal_type' => ['required', 'in:individual,team,strategic'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'target_value' => ['nullable', 'numeric'],
            'current_value' => ['nullable', 'numeric'],
            'progress_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:draft,active,completed,on_hold,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        $employee = PersonnelEmployee::findOrFail($validated['employee_id']);
        $this->abortIfPersonnelEmployeeOutsideScope($employee);

        $employee->goals()->create([
            'manager_user_id' => $validated['manager_user_id'] ?: auth()->id(),
            'administration_type' => $employee->administration_type,
            'administration_id' => $employee->administration_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'goal_type' => $validated['goal_type'],
            'weight' => $validated['weight'] ?? null,
            'target_value' => $validated['target_value'] ?? null,
            'current_value' => $validated['current_value'] ?? null,
            'progress_percent' => $validated['progress_percent'] ?? 0,
            'start_date' => $validated['start_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'career'),
            'selected_employee' => $employee->id,
        ])->with('success', 'Objectif enregistré.');
    }

    public function storePersonnelPerformanceReview(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'employee_id' => ['required', 'string'],
            'reviewer_user_id' => ['nullable', 'string'],
            'review_type' => ['required', 'in:annual,midyear,probation,360,continuous'],
            'title' => ['required', 'string', 'max:191'],
            'period_label' => ['nullable', 'string', 'max:100'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['required', 'in:scheduled,in_progress,completed,cancelled'],
            'overall_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'strengths' => ['nullable', 'string'],
            'improvements' => ['nullable', 'string'],
            'manager_comments' => ['nullable', 'string'],
            'employee_comments' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
        ]);

        $employee = PersonnelEmployee::findOrFail($validated['employee_id']);
        $this->abortIfPersonnelEmployeeOutsideScope($employee);

        $employee->performanceReviews()->create([
            'reviewer_user_id' => $validated['reviewer_user_id'] ?: auth()->id(),
            'administration_type' => $employee->administration_type,
            'administration_id' => $employee->administration_id,
            'review_type' => $validated['review_type'],
            'title' => $validated['title'],
            'period_label' => $validated['period_label'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
            'status' => $validated['status'],
            'overall_score' => $validated['overall_score'] ?? null,
            'strengths' => $validated['strengths'] ?? null,
            'improvements' => $validated['improvements'] ?? null,
            'manager_comments' => $validated['manager_comments'] ?? null,
            'employee_comments' => $validated['employee_comments'] ?? null,
            'recommendations' => $validated['recommendations'] ?? null,
        ]);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'career'),
            'selected_employee' => $employee->id,
        ])->with('success', 'Évaluation enregistrée.');
    }

    public function storePersonnelCareerEvent(Request $request)
    {
        $validated = $request->validate([
            'personnel_tab' => ['nullable', 'string'],
            'employee_id' => ['required', 'string'],
            'event_type' => ['required', 'in:promotion,mobility,succession,job_change,interview'],
            'effective_date' => ['nullable', 'date'],
            'title' => ['required', 'string', 'max:191'],
            'previous_job_title' => ['nullable', 'string', 'max:191'],
            'new_job_title' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'in:planned,validated,completed,cancelled'],
            'summary' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $employee = PersonnelEmployee::findOrFail($validated['employee_id']);
        $this->abortIfPersonnelEmployeeOutsideScope($employee);

        $employee->careerEvents()->create([
            'recorded_by_user_id' => auth()->id(),
            'administration_type' => $employee->administration_type,
            'administration_id' => $employee->administration_id,
            'event_type' => $validated['event_type'],
            'effective_date' => $validated['effective_date'] ?? null,
            'title' => $validated['title'],
            'previous_job_title' => $validated['previous_job_title'] ?? $employee->job_title,
            'new_job_title' => $validated['new_job_title'] ?? null,
            'status' => $validated['status'],
            'summary' => $validated['summary'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('admin.index', [
            'tab' => 'personnel',
            'personnel_tab' => $request->input('personnel_tab', 'career'),
            'selected_employee' => $employee->id,
        ])->with('success', 'Événement de carrière enregistré.');
    }

    // ── Token OnlyOffice (API, génère un JWT frais pour un document) ──────────
    public function onlyofficeToken(Request $request)
    {
        $onlyofficeSecret = AppSetting::where('key', 'onlyoffice_secret')->value('value') ?: '';
        if (!$onlyofficeSecret) {
            return response()->json(['token' => '', 'docUrl' => ''], 200);
        }

        $appPublicUrl = AppSetting::where('key', 'app_public_url')->value('value') ?: '';
        $onlyofficeUrl = AppSetting::where('key', 'onlyoffice_server_url')->value('value') ?: '';
        $docKey       = $request->input('key', 'doc-' . time());
        $docUrl       = $request->input('url', '');
        $docType      = $request->input('fileType', 'docx');
        $docTitle     = $request->input('title', 'Document');

        // Si aucune URL fournie, utiliser la route publique /oo-blank/docx
        if (!$docUrl) {
            $base   = $this->resolveAppPublicBaseUrl($appPublicUrl, $onlyofficeUrl);
            $docUrl = $base . '/oo-blank/' . $docType;
        }

        $payload = [
            'document' => [
                'fileType' => $docType,
                'key'      => $docKey,
                'title'    => $docTitle,
                'url'      => $docUrl,
                'permissions' => ['edit' => true, 'download' => false, 'print' => false],
            ],
            'documentType' => $docType === 'xlsx' ? 'cell' : ($docType === 'pptx' ? 'slide' : 'word'),
            'editorConfig' => [
                'mode'        => 'edit',
                'lang'        => 'fr',
                'callbackUrl' => $request->input('callbackUrl', ''),
                'user'        => ['id' => 'u-' . auth()->id(), 'name' => auth()->user()->name ?? 'Utilisateur'],
                'customization' => [
                    'autosave'      => true,
                    'compactHeader' => true,
                    'hideRightMenu' => true,
                    'forcesave'     => false,
                ],
            ],
        ];

        $header    = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $body      = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", $onlyofficeSecret, true)), '+/', '-_'), '=');

        return response()->json(['token' => "$header.$body.$signature", 'docUrl' => $docUrl]);
    }

    // ── Upload d'un fichier template ──────────────────────────────────────────
    public function uploadTemplateFile(Request $request)
    {
        $request->validate([
            'file' => [
                'required', 'file', 'max:20480',
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, ['pdf', 'docx', 'xlsx', 'pptx'])) {
                        $fail('Le fichier doit être de type PDF, DOCX, XLSX ou PPTX.');
                    }
                },
            ],
            'name'              => 'required|string|max:255',
            'administration_id' => 'nullable|string',
            'template_id'       => 'nullable|string',
        ]);

        try {

        $file      = $request->file('file');
        ClamAvScanner::scan($file, 'templates');
        $ext       = strtolower($file->getClientOriginalExtension());
        $origName  = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeSlug  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $origName);
        $fileName  = $safeSlug . '_' . time() . '.' . $ext;

        // Stocker dans storage/app/public/templates/
        $storedPath = $file->storeAs('templates', $fileName, 'public');

        // ── Auto-extraction des variables {{}} depuis le fichier Office ────────
        $extractedVars = [];
        $fallbackContent = '';
        if (in_array($ext, ['docx', 'xlsx', 'pptx'])) {
            $absPath = \Illuminate\Support\Facades\Storage::disk('public')->path($storedPath);
            $extractedVars = $this->extractVarsFromUploadedFile($absPath);
            $fallbackContent = app(TemplateOfficeTextExtractor::class)->extract($absPath);

            // Un modèle Office sans variable n'est pas utile: on refuse sa création.
            if (count($extractedVars) === 0) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($storedPath);
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune variable détectée dans le fichier. Ajoutez des balises (ex: {{ nom_client }}) puis réessayez.',
                ], 422);
            }
        }

        $targetTemplateId = (string) ($request->input('template_id') ?: '');
        $template = null;
        if ($targetTemplateId !== '') {
            $template = DocumentTemplate::find($targetTemplateId);
        }

        if ($template) {
            $template->name = (string) $request->input('name');
            $template->file_name = (string) $file->getClientOriginalName();
            $template->file_type = (string) $ext;
            $template->storage_path = (string) $storedPath;
            if ($fallbackContent !== '') {
                $template->content = $fallbackContent;
            }
            $template->administration_id = $request->input('administration_id') ?: null;
            $template->save();
        } else {
            // Créer le template en base
            $template = DocumentTemplate::create([
                'name'              => $request->input('name'),
                'file_name'         => $file->getClientOriginalName(),
                'file_type'         => $ext,
                'storage_path'      => $storedPath,
                'content'           => $fallbackContent,
                'administration_id' => $request->input('administration_id') ?: null,
                'created_by'        => auth()->id(),
            ]);
        }

        $savedVars = 0;
        $aiMeta = [
            'applied' => false,
            'available' => false,
            'source' => 'none',
            'count' => 0,
            'message' => '',
        ];

        if (!empty($extractedVars)) {
            $savedVars = $this->saveDetectedTemplateVars($template, $extractedVars);
            $aiMeta = $this->autoEnrichTemplateVariables($template, $extractedVars);
        }

        $formFields = $template->variables()
            ->get(['key', 'label', 'field_type', 'required', 'placeholder'])
            ->map(fn ($v) => [
                'key' => (string) $v->key,
                'label' => (string) $v->label,
                'field_type' => (string) ($v->field_type ?: 'text'),
                'required' => (bool) $v->required,
                'placeholder' => (string) ($v->placeholder ?? ''),
            ])
            ->values()
            ->toArray();

        // Générer la config OnlyOffice pour ce fichier
        $onlyofficeSecret = AppSetting::where('key', 'onlyoffice_secret')->value('value') ?: '';
        $onlyofficeUrl    = preg_replace('/\s+/', '', (string) (AppSetting::where('key', 'onlyoffice_server_url')->value('value') ?: ''));
        $appPublicUrl     = preg_replace('/\s+/', '', (string) (AppSetting::where('key', 'app_public_url')->value('value') ?: ''));

        $docKey   = 'tpl-up-' . $template->id . '-' . time();
        $docType  = $ext === 'xlsx' ? 'cell' : ($ext === 'pptx' ? 'slide' : ($ext === 'pdf' ? 'pdf' : 'word'));

        // Compute paths BEFORE building docUrl so basePath is always included
        $storagePubPath = \Illuminate\Support\Facades\Storage::url($storedPath); // ex: /storage/templates/file.pdf
        $basePath       = rtrim($request->getBaseUrl(), '/');                     // ex: /e-administration_laravel/public
        $localDocUrl    = rtrim($request->getSchemeAndHttpHost(), '/') . $basePath . $storagePubPath;

        $baseForDocUrl = $this->resolveAppPublicBaseUrl($appPublicUrl, $onlyofficeUrl);
        $docUrl = $baseForDocUrl . $storagePubPath;

        $token = '';
        if ($onlyofficeSecret) {
            $payload = [
                'document' => [
                    'fileType' => $ext,
                    'key'      => $docKey,
                    'title'    => $template->name,
                    'url'      => $docUrl,
                    'permissions' => ['edit' => true, 'download' => false, 'print' => false],
                ],
                'documentType' => $docType,
                'editorConfig' => [
                    'mode'        => 'edit',
                    'lang'        => 'fr',
                    'callbackUrl' => '',
                    'user'        => ['id' => 'admin-' . auth()->id(), 'name' => auth()->user()->name ?? 'Admin'],
                    'customization' => ['autosave' => true, 'compactHeader' => true, 'hideRightMenu' => true],
                ],
            ];
            $header    = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
            $body      = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
            $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", $onlyofficeSecret, true)), '+/', '-_'), '=');
            $token = "$header.$body.$signature";
        }

        return response()->json([
            'success'        => true,
            'template_id'    => $template->id,
            'template_name'  => $template->name,
            'ooUrl'          => $onlyofficeUrl,
            'token'          => $token,
            'docUrl'         => $docUrl,
            'localDocUrl'    => $localDocUrl,
            'storagePubPath' => $storagePubPath,
            'docKey'         => $docKey,
            'documentType'   => $docType,
            'fileType'       => $ext,
            'variables'      => $extractedVars,
            'variables_count'=> count($extractedVars),
            'variables_saved'=> $savedVars,
            'form_fields'    => $formFields,
            'form_fields_count' => count($formFields),
            'ai'             => $aiMeta,
        ]);
        } catch (\Throwable $e) {
            Log::error('Template upload failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            $msg = 'Erreur interne lors de l\'upload du template.';
            $raw = strtolower($e->getMessage());
            if (str_contains($raw, 'permission') || str_contains($raw, 'denied') || str_contains($raw, 'unabletowritefile')) {
                $msg = 'Impossible d\'enregistrer le fichier sur le serveur. Verifiez les droits d\'ecriture de storage/app/public/templates.';
            } elseif (str_contains($raw, 'ziparchive')) {
                $msg = 'Le serveur ne peut pas lire ce fichier Office (extension ZIP manquante ou fichier corrompu).';
            }

            return response()->json([
                'success' => false,
                'message' => $msg,
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 200);
        }
    }

    private function autoEnrichTemplateVariables(DocumentTemplate $template, array $rawVars = []): array
    {
        try {
            $rawArray = !empty($rawVars)
                ? array_map(fn ($v) => [
                    'key' => (string) ($v['key'] ?? ''),
                    'label' => (string) ($v['label'] ?? ($v['key'] ?? '')),
                ], $rawVars)
                : $template->variables()->get()->map(fn ($v) => [
                    'key' => (string) $v->key,
                    'label' => (string) ($v->label ?: $v->key),
                ])->toArray();

            $rawArray = array_values(array_filter($rawArray, fn ($v) => !empty($v['key'])));
            if (empty($rawArray)) {
                return [
                    'applied' => false,
                    'available' => false,
                    'source' => 'none',
                    'count' => 0,
                    'message' => 'Aucune variable à enrichir.',
                ];
            }

            $ollama = new \App\Services\OllamaService();
            $available = $ollama->isAvailable();
            $enriched = $available ? $ollama->enrichVariables($rawArray) : $ollama->fallbackEnrich($rawArray);
            $source = $available ? 'ollama' : 'fallback';

            $validTypes = ['text', 'date', 'number', 'select', 'textarea'];
            foreach ($enriched as $item) {
                $key = (string) ($item['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $type = in_array($item['field_type'] ?? '', $validTypes, true) ? $item['field_type'] : 'text';
                $template->variables()->where('key', $key)->update([
                    'label' => (string) ($item['label'] ?? $key),
                    'field_type' => $type,
                    'required' => (bool) ($item['required'] ?? false),
                    'placeholder' => (string) ($item['placeholder'] ?? ''),
                ]);
            }

            return [
                'applied' => true,
                'available' => $available,
                'source' => $source,
                'count' => count($enriched),
                'message' => $available
                    ? 'Variables enrichies par IA (Ollama).'
                    : 'Ollama indisponible: enrichissement heuristique appliqué.',
            ];
        } catch (\Throwable $e) {
            Log::warning('autoEnrichTemplateVariables failed: ' . $e->getMessage(), [
                'template_id' => (string) $template->id,
            ]);

            return [
                'applied' => false,
                'available' => false,
                'source' => 'error',
                'count' => 0,
                'message' => 'Échec enrichissement IA: ' . $e->getMessage(),
            ];
        }
    }

    // ── Paramètres ────────────────────────────────────────────────────────────

    /** Liste blanche des clés de paramètre autorisées via le formulaire. */
    private const ALLOWED_SETTING_KEYS = [
        'app_name', 'app_public_url', 'app_logo',
        'onlyoffice_server_url', 'onlyoffice_secret', 'onlyoffice_doc_viewer',
        'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption',
        'mail_from_address', 'mail_from_name',
        'qr_image_page', 'qr_image_x', 'qr_image_y', 'qr_image_width', 'qr_image_height',
        'signature_qr_position',
        'theme_primary_color', 'theme_secondary_color', 'theme_logo',
        'courrier_archival_days',
        'reception_archival_days',
        'email_notifications_enabled', 'email_notifications_from',
        'otp_channel', 'whatsapp_api_token', 'whatsapp_phone_number_id',
    ];

    public function updateSettings(Request $request)
    {
        $this->guardPermission('administration');
        // Seules les clés de la liste blanche sont acceptées
        $data = array_filter(
            $request->except('_token', '_method', 'tab'),
            fn($key) => in_array($key, self::ALLOWED_SETTING_KEYS, true),
            ARRAY_FILTER_USE_KEY
        );

        foreach (['app_public_url', 'onlyoffice_server_url'] as $urlKey) {
            if (array_key_exists($urlKey, $data) && is_string($data[$urlKey])) {
                $data[$urlKey] = preg_replace('/\s+/', '', trim($data[$urlKey]));
            }
        }

        // Ne pas écraser le token WhatsApp s'il est laissé vide
        if (array_key_exists('whatsapp_api_token', $data) && trim((string) $data['whatsapp_api_token']) === '') {
            unset($data['whatsapp_api_token']);
        }

        // Canal OTP : stocké par administration (otp_channel:{type}:{id}) ou global (otp_channel)
        if ($request->filled('otp_channel')) {
            unset($data['otp_channel']);
            $channel = $request->input('otp_channel') === 'whatsapp' ? 'whatsapp' : 'email';

            $scopeInput = (string) $request->input('otp_admin_scope', '');
            $actorScope = $this->resolveAdminScope();
            if ($actorScope) {
                // Admin d'administration : périmètre forcé sur sa propre administration
                $scopeInput = $actorScope['type'] . ':' . $actorScope['id'];
            } elseif ($scopeInput !== '' && !preg_match('/^(emitter|recipient):[0-9a-fA-F-]{36}$/', $scopeInput)) {
                $scopeInput = '';
            }

            $key = $scopeInput !== '' ? 'otp_channel:' . $scopeInput : 'otp_channel';
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $channel, 'description' => 'Canal d\'envoi du code OTP de connexion']
            );
        }

        // Archivage courrier/réception : stocké par administration (xxx:{type}:{id}) ou global.
        if ($request->has('courrier_archival_days') || $request->has('reception_archival_days')) {
            unset($data['courrier_archival_days'], $data['reception_archival_days']);

            $scopeInput = (string) $request->input('archival_admin_scope', '');
            $actorScope = $this->resolveAdminScope();
            if ($actorScope) {
                // Admin d'administration : périmètre forcé sur sa propre administration
                $scopeInput = $actorScope['type'] . ':' . $actorScope['id'];
            } elseif ($scopeInput !== '' && !preg_match('/^(emitter|recipient):[0-9a-fA-F-]{36}$/', $scopeInput)) {
                $scopeInput = '';
            }

            foreach (['courrier_archival_days', 'reception_archival_days'] as $settingBaseKey) {
                if (!$request->has($settingBaseKey)) {
                    continue;
                }

                $raw = trim((string) $request->input($settingBaseKey, ''));
                $normalizedDays = $raw === '' ? 0 : max(0, (int) $raw);
                $key = $scopeInput !== '' ? $settingBaseKey . ':' . $scopeInput : $settingBaseKey;

                AppSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $normalizedDays]
                );
            }
        }

        foreach ($data as $key => $value) {
            AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Keep signature QR position in sync when edited from OnlyOffice settings.
        if (
            $request->has('qr_image_page') ||
            $request->has('qr_image_x') ||
            $request->has('qr_image_y') ||
            $request->has('qr_image_width') ||
            $request->has('qr_image_height')
        ) {
            $existing = AppSetting::where('key', 'signature_qr_position')->value('value');
            $existingDecoded = json_decode((string) $existing, true);
            if (!is_array($existingDecoded)) {
                $existingDecoded = [];
            }

            $qrPosition = json_encode([
                'imagePage' => (int) $request->input('qr_image_page', $existingDecoded['imagePage'] ?? -1),
                'imageX' => (int) $request->input('qr_image_x', $existingDecoded['imageX'] ?? 390),
                'imageY' => (int) $request->input('qr_image_y', $existingDecoded['imageY'] ?? 710),
                'imageWidth' => (int) $request->input('qr_image_width', $existingDecoded['imageWidth'] ?? 150),
                'imageHeight' => (int) $request->input('qr_image_height', $existingDecoded['imageHeight'] ?? 80),
            ]);

            AppSetting::updateOrCreate(
                ['key' => 'signature_qr_position'],
                ['value' => $qrPosition, 'description' => 'Position QR sur document PDF']
            );
        }

        return back()->with('success', 'Paramètres enregistrés.')->withInput(['tab' => $request->input('tab', 'settings')]);
    }

    // ── SMTP par administration ────────────────────────────────────────────────

    /** GET /admin/smtp-settings/{type}/{id} — charge les réglages SMTP d'une administration. */
    public function getAdminSmtp(string $type, string $id)
    {
        abort_if(!auth()->check() || auth()->user()->role !== 'admin', 403);

        $smtp = AdministrationSmtpSetting::forAdministration($id, $type);

        return response()->json($smtp ? [
            'mail_host'         => $smtp->mail_host,
            'mail_port'         => $smtp->mail_port,
            'mail_username'     => $smtp->mail_username,
            'mail_password'     => '', // never expose stored password
            'mail_encryption'   => $smtp->mail_encryption,
            'mail_from_address' => $smtp->mail_from_address,
            'mail_from_name'    => $smtp->mail_from_name,
        ] : []);
    }

    /** POST /admin/smtp-settings — enregistre les réglages SMTP d'une administration. */
    public function saveAdminSmtp(Request $request)
    {
        $this->guardPermission('administration.email-notifications');
        abort_if(!auth()->check() || auth()->user()->role !== 'admin', 403);

        $validated = $request->validate([
            'administration_id'   => ['required', 'string'],
            'administration_type' => ['required', 'in:emitter,recipient'],
            'mail_host'           => ['nullable', 'string', 'max:255'],
            'mail_port'           => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username'       => ['nullable', 'string', 'max:255'],
            'mail_password'       => ['nullable', 'string'],
            'mail_encryption'     => ['nullable', 'in:tls,ssl,'],
            'mail_from_address'   => ['nullable', 'email', 'max:255'],
            'mail_from_name'      => ['nullable', 'string', 'max:255'],
        ]);

        $adminId   = (string) $validated['administration_id'];
        $adminType = (string) ($validated['administration_type'] ?? 'emitter');

        if (!$adminId) {
            return response()->json(['success' => false, 'message' => 'Administration non sélectionnée.'], 422);
        }

        $data = $request->only([
            'mail_host', 'mail_port', 'mail_username',
            'mail_encryption', 'mail_from_address', 'mail_from_name',
        ]);

        // Normalize empty values and guarantee a numeric SMTP port.
        $data['mail_port'] = (int) ($data['mail_port'] ?: 587);
        foreach (['mail_host', 'mail_username', 'mail_encryption', 'mail_from_address', 'mail_from_name'] as $k) {
            if (array_key_exists($k, $data) && is_string($data[$k])) {
                $data[$k] = trim($data[$k]);
            }
        }

        // Only update password if a new value was provided
        $newPassword = $request->input('mail_password');
        if ($newPassword !== null && $newPassword !== '') {
            $data['mail_password'] = $newPassword;
        }

        $smtp = AdministrationSmtpSetting::where('administration_id', $adminId)
            ->where('administration_type', $adminType)
            ->first();

        if ($smtp) {
            // Fill manually to trigger mutator only when password changes
            foreach ($data as $key => $value) {
                $smtp->$key = $value;
            }
            $smtp->save();
        } else {
            $data['administration_id']   = $adminId;
            $data['administration_type'] = $adminType;
            $smtp = AdministrationSmtpSetting::create($data);
        }

        return response()->json(['success' => true, 'message' => 'Configuration SMTP enregistrée.']);
    }

    /** POST /admin/smtp-test — envoie un e-mail de test avec les réglages d'une administration. */
    public function testSmtp(Request $request)
    {
        abort_if(!auth()->check() || auth()->user()->role !== 'admin', 403);

        $adminId   = $request->input('administration_id');
        $adminType = $request->input('administration_type', 'emitter');

        if (!$adminId) {
            return response()->json(['success' => false, 'message' => 'Administration non sélectionnée.'], 422);
        }

        $smtp = AdministrationSmtpSetting::forAdministration($adminId, $adminType);

        if (!$smtp || !$smtp->mail_host || !$smtp->mail_from_address) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration SMTP incomplète pour cette administration (hôte ou adresse expéditeur manquant).',
            ], 422);
        }

        config([
            'mail.default'                 => 'smtp',
            'mail.mailers.smtp.host'       => $smtp->mail_host,
            'mail.mailers.smtp.port'       => $smtp->mail_port ?? 587,
            'mail.mailers.smtp.username'   => $smtp->mail_username,
            'mail.mailers.smtp.password'   => $smtp->mail_password,
            'mail.mailers.smtp.encryption' => $smtp->mail_encryption ?: null,
            'mail.mailers.smtp.timeout'    => 10,
            'mail.from.address'            => $smtp->mail_from_address,
            'mail.from.name'               => $smtp->mail_from_name ?? config('app.name'),
        ]);

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Ceci est un e-mail de test envoyé depuis " . config('app.name', 'E-Parapheur') . ".\n\nSi vous recevez ce message, la configuration SMTP est correcte.",
                function ($message) use ($smtp) {
                    $message->to($smtp->mail_from_address, $smtp->mail_from_name)
                            ->subject('Test SMTP — ' . config('app.name', 'E-Parapheur'));
                }
            );

            return response()->json([
                'success' => true,
                'message' => "E-mail de test envoyé avec succès à {$smtp->mail_from_address}.",
            ]);
        } catch (\Exception $e) {
            Log::error('SMTP test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Échec de l\'envoi : ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── API Signature ─────────────────────────────────────────────────────────
    public function saveSignatureProvider(Request $request)
    {
        $this->guardPermission('administration.signature-provider');
        $administrationId = $request->input('sig_admin_id');
        $adminType        = $request->input('sig_admin_type', 'emitter');

        if (!$administrationId) {
            return back()->withErrors(['sig_admin_id' => 'Sélectionnez une administration.'])
                         ->withInput()->with('tab', 'signature-provider');
        }

        SignatureProviderConfig::updateOrCreate(
            ['administration_id' => $administrationId, 'administration_type' => $adminType],
            [
                'is_active'                => (bool) $request->input('is_active', false),
                'endpoint'                 => rtrim(trim($request->input('endpoint', '')), '/'),
                'sign_path'                => $request->input('sign_path', '/v1/sign'),
                'api_key'                  => $request->input('api_key', ''),
                'tenant_id'                => trim($request->input('tenant_id', '')),
                'consent_page_id'          => trim($request->input('consent_page_id', '')),
                'consent_page_id_approval' => trim($request->input('consent_page_id_approval', '')),
                'signature_profile_id'     => trim($request->input('signature_profile_id', '')),
                'provider_owner_user_id'   => trim($request->input('provider_owner_user_id', '')),
                'verify_ssl'               => (bool) $request->input('verify_ssl', true),
                'timeout_ms'               => (int) $request->input('timeout_ms', 30000),
            ]
        );

        // Backward compatibility: if QR fields are posted from this form, persist them.
        if (
            $request->has('qr_page') ||
            $request->has('qr_x') ||
            $request->has('qr_y') ||
            $request->has('qr_width') ||
            $request->has('qr_height')
        ) {
            $qrPosition = json_encode([
                'imagePage'   => (int) $request->input('qr_page', -1),
                'imageX'      => (int) $request->input('qr_x', 390),
                'imageY'      => (int) $request->input('qr_y', 710),
                'imageWidth'  => (int) $request->input('qr_width', 150),
                'imageHeight' => (int) $request->input('qr_height', 80),
            ]);
            AppSetting::updateOrCreate(
                ['key' => 'signature_qr_position'],
                ['value' => $qrPosition, 'description' => 'Position QR sur document PDF']
            );
        }

        return redirect()->route('admin.index', [
                'tab'           => 'signature-provider',
                'sig_admin_type'=> $adminType,
                'sig_admin_id'  => $administrationId,
            ])
            ->with('sig_success', 'Configuration API Signature enregistrée avec succès.');
    }

    // ── Test connexion API Signature ──────────────────────────────────────────
    public function testSignatureConnection(Request $request): \Illuminate\Http\JsonResponse
    {
        $endpoint = rtrim(trim($request->input('endpoint', '')), '/');
        $apiKey   = trim($request->input('api_key', ''));
        $email    = trim($request->input('email', ''));

        if ($endpoint === '' || $apiKey === '') {
            return response()->json(['ok' => false, 'message' => 'Endpoint et API Key requis.'], 422);
        }

        try {
            // 1. Vérifier la connexion via /api/users/me (clé API)
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->get($endpoint . '/api/users/me');

            if (!$response->successful()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Erreur API (' . $response->status() . '): ' . ($response->json('message') ?? $response->body()),
                ], 422);
            }

            $data     = $response->json();
            $tenantId = $data['tenantId'] ?? null;
            $result   = [
                'ok'        => true,
                'message'   => 'Connexion réussie.',
                'tenant_id' => $tenantId,
            ];

            // 2. Si un email est fourni, chercher l'utilisateur par email
            if ($email !== '') {
                $cfg = new \App\Models\SignatureProviderConfig([
                    'endpoint'   => $endpoint,
                    'api_key'    => $apiKey,
                    'verify_ssl' => true,
                    'timeout_ms' => 10000,
                ]);

                // Invalider le cache pour cet email afin de forcer une recherche fraîche
                $cacheKey = 'sunnystamp_uid_' . md5($endpoint . '|' . $email);
                \Illuminate\Support\Facades\Cache::forget($cacheKey);

                $platformUserId = \App\Http\Controllers\SignatureController::resolvePlatformUserIdByEmail($cfg, $email);

                $result['platform_user_id'] = $platformUserId;
                $result['email']            = $email;
                if ($platformUserId) {
                    $result['message'] = 'Connexion réussie. Utilisateur trouvé sur la plateforme.';
                } else {
                    $result['message'] = 'Connexion réussie, mais aucun utilisateur trouvé pour l\'e-mail "' . $email . '" sur la plateforme.';
                }
            }

            return response()->json($result);

        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Impossible de joindre le serveur: ' . $e->getMessage()], 422);
        }
    }

    // ── Apparence (theming) ───────────────────────────────────────────────────
    public function saveTheming(Request $request)
    {
        $this->guardPermission('administration.theming');
        $tType = $request->input('t_type', 'emitter');
        $tId   = $request->input('t_id', '');

        if (!$tId) {
            return back()->withErrors(['t_id' => 'Sélectionnez une administration.'])
                         ->withInput()->with('tab', 'theming');
        }

        $prefix = "theme_{$tType}_{$tId}_";

        $textFields = [
            'app_name'            => $request->input('app_name', ''),
            'web_url'             => $request->input('web_url', ''),
            'slogan'              => $request->input('slogan', ''),
            'menu_color'          => $request->input('menu_color', '#173b9f'),
            'bg_color'            => $request->input('bg_color', '#495F55'),
            'legal_notice_url'    => $request->input('legal_notice_url', ''),
            'privacy_policy_url'  => $request->input('privacy_policy_url', ''),
            'disable_user_theming'=> $request->input('disable_user_theming', 'false'),
        ];

        foreach ($textFields as $suffix => $value) {
            AppSetting::updateOrCreate(
                ['key' => $prefix . $suffix],
                ['value' => $value, 'description' => 'Theming ' . $tType . ' ' . $tId]
            );
        }

        // Synchronise la couleur de menu globale
        AppSetting::updateOrCreate(
            ['key' => 'theme_menu_color'],
            ['value' => $request->input('menu_color', '#173b9f'), 'description' => 'Couleur globale du menu']
        );

        $fileFields = [
            'logo_file'        => 'logo',
            'bg_image_file'    => 'login_background_image',
            'header_logo_file' => 'header_logo',
            'favicon_file'     => 'favicon',
        ];

        foreach ($fileFields as $inputName => $suffix) {
            if ($request->hasFile($inputName) && $request->file($inputName)->isValid()) {
                ClamAvScanner::scan($request->file($inputName), 'apparence');
                $path = $request->file($inputName)->store("theming/{$tType}/{$tId}", 'public');
                AppSetting::updateOrCreate(
                    ['key' => $prefix . $suffix],
                    ['value' => $path, 'description' => 'Theming ' . $tType . ' ' . $tId]
                );
                if ($suffix === 'login_background_image') {
                    AppSetting::updateOrCreate(
                        ['key' => 'theme_login_background_image'],
                        ['value' => $path, 'description' => 'Image globale de fond de connexion']
                    );
                }
            }
        }

        return redirect()->route('admin.index', ['tab' => 'theming', 't_type' => $tType, 't_id' => $tId])
                         ->with('theming_success', 'Les paramètres d\'apparence ont été enregistrés avec succès.');
    }

    // ── Émetteurs ─────────────────────────────────────────────────────────────
    private function extractEmitterMetadata(Request $request): array
    {
        return [
            'adminType'         => $request->input('admin_type', ''),
            'sector'            => $request->input('sector', ''),
            'description'       => $request->input('description', ''),
            'contactEmail'      => $request->input('contact_email', ''),
            'techEmail'         => $request->input('tech_email', ''),
            'contactPhone'      => $request->input('contact_phone', ''),
            'referentMetier'    => $request->input('referent_metier', ''),
            'postalAddress'     => $request->input('postal_address', ''),
            'transmissionMethod'=> $request->input('transmission_method', 'api'),
            'endpointUrl'       => $request->input('endpoint_url', ''),
            'dataFormat'        => $request->input('data_format', 'json'),
            'authMethod'        => $request->input('auth_method', 'api_key'),
            'apiKey'            => $request->input('api_key', ''),
            'timeout'           => (int)$request->input('timeout', 30),
            'requireTls'        => $request->boolean('require_tls', true),
            'enableRetry'       => $request->boolean('enable_retry', true),
            'docTypes'          => (array)$request->input('doc_types', ['pdf']),
            'defaultWorkflow'   => $request->input('default_workflow', ''),
            'dossierPrefix'     => $request->input('dossier_prefix', ''),
            'autoConvertPdf'    => $request->boolean('auto_convert_pdf', true),
            'requiredMetadata'  => $request->input('required_metadata', ''),
            'signatureLevel'    => $request->input('signature_level', 'qualifiee'),
            'logRetention'      => (int)$request->input('log_retention', 365),
            'businessHours'     => $request->input('business_hours', ''),
            'slaResponse'       => $request->input('sla_response', '24h'),
            'timezone'          => $request->input('timezone', 'Europe/Paris'),
            'duplicateHandling' => $request->input('duplicate_handling', 'update'),
            'gdprCompliant'     => $request->boolean('gdpr_compliant', true),
            'enableAudit'       => $request->boolean('enable_audit', true),
            'fileEncryption'    => $request->boolean('file_encryption', false),
            'ipWhitelist'       => $request->input('ip_whitelist', ''),
            'externalRefField'  => $request->input('external_ref_field', ''),
            'trackingUrl'       => $request->input('tracking_url', ''),
            'webhookUrl'        => $request->input('webhook_url', ''),
            'webhookSecret'     => $request->input('webhook_secret', ''),
            'tags'              => $request->input('tags', ''),
        ];
    }

    private function issuingAdministrationHasSubEntityCode(): bool
    {
        try {
            return Schema::hasColumn('issuing_administrations', 'sub_entity_code');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function storeEmitter(Request $request)
    {
        $this->guardPermission('administration.emitters');
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:issuing_administrations,code',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo_file')) {
            $file = $request->file('logo_file');
            ClamAvScanner::scan($file, 'emetteurs');
            $filename = uniqid('logo_') . '.' . $file->getClientOriginalExtension();
            $logoDir = public_path('images/logos');
            if (!is_dir($logoDir)) {
                mkdir($logoDir, 0755, true);
            }
            $file->move(public_path('images/logos'), $filename);
            $logoPath = 'images/logos/' . $filename;
        }

        $payload = [
            'id'              => Str::uuid(),
            'name'            => $request->input('name'),
            'code'            => strtoupper($request->input('code')),
            'is_active'       => $request->boolean('is_active', true),
            'logo'            => $logoPath,
            'metadata'        => $this->extractEmitterMetadata($request),
        ];

        if ($this->issuingAdministrationHasSubEntityCode()) {
            $payload['sub_entity_code'] = strtoupper((string) $request->input('sub_entity_code', ''));
        }

        IssuingAdministration::create($payload);
        return redirect()->route('admin.index', ['tab' => 'emitters'])
            ->with('success', 'Administration émettrice créée.');
    }

    public function updateEmitter(Request $request, IssuingAdministration $emitter)
    {
        $this->guardPermission('administration.emitters');
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:issuing_administrations,code,' . $emitter->id,
        ]);

        $logoPath = $emitter->logo;
        if ($request->hasFile('logo_file')) {
            $file = $request->file('logo_file');
            ClamAvScanner::scan($file, 'emetteurs');
            $filename = uniqid('logo_') . '.' . $file->getClientOriginalExtension();
            $logoDir = public_path('images/logos');
            if (!is_dir($logoDir)) {
                mkdir($logoDir, 0755, true);
            }
            $file->move(public_path('images/logos'), $filename);
            $logoPath = 'images/logos/' . $filename;
        }

        $payload = [
            'name'            => $request->input('name'),
            'code'            => strtoupper($request->input('code')),
            'is_active'       => $request->boolean('is_active', true),
            'logo'            => $logoPath,
            'metadata'        => $this->extractEmitterMetadata($request),
        ];

        if ($this->issuingAdministrationHasSubEntityCode()) {
            $payload['sub_entity_code'] = strtoupper((string) $request->input('sub_entity_code', ''));
        }

        $emitter->update($payload);
        return redirect()->route('admin.index', ['tab' => 'emitters'])
            ->with('success', 'Administration émettrice mise à jour.');
    }

    public function destroyEmitter(IssuingAdministration $emitter)
    {
        $this->guardPermission('administration.emitters');
        $emitter->delete();
        return back()->with('success', 'Émetteur supprimé.')->withInput(['tab' => 'emitters']);
    }

    // ── Destinataires ─────────────────────────────────────────────────────────
    private function extractRecipientMetadata(Request $request): array
    {
        return [
            'adminType'          => $request->input('admin_type', ''),
            'sector'             => $request->input('sector', ''),
            'description'        => $request->input('description', ''),
            'contactEmail'       => $request->input('contact_email', ''),
            'techEmail'          => $request->input('tech_email', ''),
            'contactPhone'       => $request->input('contact_phone', ''),
            'contactFax'         => $request->input('contact_fax', ''),
            'referentMetier'     => $request->input('referent_metier', ''),
            'referentTechnique'  => $request->input('referent_technique', ''),
            'postalAddress'      => $request->input('postal_address', ''),
            // Méthode de réception
            'apiEndpoint'        => $request->input('api_endpoint_meta', ''),
            'apiMethod'          => $request->input('api_method', 'POST'),
            'apiFormat'          => $request->input('api_format', 'multipart'),
            'apiAuth'            => $request->input('api_auth', 'api_key'),
            'apiTimeout'         => (int)$request->input('api_timeout', 30),
            'emailAddress'       => $request->input('email_address_meta', ''),
            'emailSubject'       => $request->input('email_subject', ''),
            'emailBody'          => $request->input('email_body', ''),
            'lerProvider'        => $request->input('ler_provider', 'laposte'),
            'lerAccountId'       => $request->input('ler_account_id', ''),
            // Documents acceptés
            'docTypes'           => (array)$request->input('doc_types', ['pdf']),
            'maxFileSize'        => (int)$request->input('max_file_size', 50),
            'maxFiles'           => (int)$request->input('max_files', 10),
            'enableRetry'        => $request->boolean('enable_retry', true),
            'enableNotification' => $request->boolean('enable_notification', true),
            'compressFiles'      => $request->boolean('compress_files', false),
            'encryptFiles'       => $request->boolean('encrypt_files', false),
            // Accusé de réception
            'receiptMethod'      => $request->input('receipt_method', 'automatic'),
            'receiptTimeout'     => (int)$request->input('receipt_timeout', 24),
            'receiptWebhookUrl'  => $request->input('receipt_webhook_url', ''),
            'activateImmediately'=> $request->boolean('activate_immediately', true),
        ];
    }

    public function storeRecipient(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'channel' => 'required|in:api,email,ler,application',
        ]);

        $logoPath = null;
        if ($request->hasFile('logo_file')) {
            $file = $request->file('logo_file');
            ClamAvScanner::scan($file, 'destinataires');
            $filename = uniqid('logo_') . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/logos'), $filename);
            $logoPath = 'images/logos/' . $filename;
        }

        $metadata = $this->extractRecipientMetadata($request);
        $metadata['logoPath'] = $logoPath;

        $payload = [
            'id'            => Str::uuid(),
            'name'          => $request->input('name'),
            'channel'       => $request->input('channel'),
            'email_address' => $request->input('email_address_meta'),
            'api_endpoint'  => $request->input('api_endpoint_meta'),
            'is_active'     => $request->boolean('is_active', true),
            'metadata'      => $metadata,
        ];

        if (Schema::hasColumn('recipient_administrations', 'code')) {
            $payload['code'] = strtoupper((string) $request->input('code', ''));
        }

        if (Schema::hasColumn('recipient_administrations', 'logo')) {
            $payload['logo'] = $logoPath;
        }

        RecipientAdministration::create($payload);
        return redirect()->route('admin.index', ['tab' => 'recipients'])
            ->with('success', 'Administration destinataire créée.');
    }

    public function updateRecipient(Request $request, RecipientAdministration $recipient)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'channel' => 'required|in:api,email,ler,application',
        ]);

        $logoPath = $recipient->logo;
        if ($request->hasFile('logo_file')) {
            $file = $request->file('logo_file');
            ClamAvScanner::scan($file, 'destinataires');
            $filename = uniqid('logo_') . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/logos'), $filename);
            $logoPath = 'images/logos/' . $filename;
        }

        $metadata = $this->extractRecipientMetadata($request);
        $metadata['logoPath'] = $logoPath;

        $payload = [
            'name'          => $request->input('name'),
            'channel'       => $request->input('channel'),
            'email_address' => $request->input('email_address_meta'),
            'api_endpoint'  => $request->input('api_endpoint_meta'),
            'is_active'     => $request->boolean('is_active', true),
            'metadata'      => $metadata,
        ];

        if (Schema::hasColumn('recipient_administrations', 'code')) {
            $payload['code'] = strtoupper((string) $request->input('code', ''));
        }

        if (Schema::hasColumn('recipient_administrations', 'logo')) {
            $payload['logo'] = $logoPath;
        }

        $recipient->update($payload);
        return redirect()->route('admin.index', ['tab' => 'recipients'])
            ->with('success', 'Administration destinataire mise à jour.');
    }

    public function destroyRecipient(RecipientAdministration $recipient)
    {
        $recipient->delete();
        return back()->with('success', 'Destinataire supprimé.')->withInput(['tab' => 'recipients']);
    }

    // ── Types de direction ────────────────────────────────────────────────────
    public function storeDirectionType(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string']);
        DirectionType::create(['id' => Str::uuid()] + $data);
        return back()->with('success', 'Type de direction créé.')->withInput(['tab' => 'direction-types']);
    }

    public function updateDirectionType(Request $request, DirectionType $directionType)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string']);
        $directionType->update($data);
        return back()->with('success', 'Type de direction mis à jour.')->withInput(['tab' => 'direction-types']);
    }

    public function destroyDirectionType(DirectionType $directionType)
    {
        $directionType->delete();
        return back()->with('success', 'Type de direction supprimé.')->withInput(['tab' => 'direction-types']);
    }

    // ── Templates ─────────────────────────────────────────────────────────────
    // ── Config OnlyOffice pour un template existant ───────────────────────────
    public function getTemplateOoConfig(DocumentTemplate $template)
    {
        $onlyofficeSecret = AppSetting::where('key', 'onlyoffice_secret')->value('value') ?: '';
        $onlyofficeUrl    = AppSetting::where('key', 'onlyoffice_server_url')->value('value') ?: '';
        $appPublicUrl     = AppSetting::where('key', 'app_public_url')->value('value') ?: '';

        $ext = $template->file_type ?: 'docx';
        $ext = strtolower(ltrim($ext, '.'));

        if ($ext !== 'pdf' && !$onlyofficeUrl) {
            return response()->json([
                'success' => false,
                'error' => 'Le serveur OnlyOffice n\'est pas configuré. Renseignez l\'adresse du ONLYOFFICE Docs dans l\'onglet OnlyOffice.',
            ], 422);
        }

        if ($ext !== 'pdf' && !$onlyofficeSecret) {
            return response()->json([
                'success' => false,
                'error' => 'Secret JWT OnlyOffice manquant. Configurez onlyoffice_secret (même valeur que DocumentServer) pour éviter les erreurs 403 /downloadfile.',
            ], 422);
        }

        // Construire l'URL du document
        if ($template->storage_path) {
            // Résoudre selon l'emplacement réel du fichier
            if (str_starts_with($template->storage_path, 'images/')) {
                $absCheck = public_path($template->storage_path);
                $exists   = file_exists($absCheck);
                $storagePubPath = '/' . ltrim($template->storage_path, '/');
            } else {
                $exists = \Illuminate\Support\Facades\Storage::disk('public')->exists($template->storage_path);
                $storagePubPath = \Illuminate\Support\Facades\Storage::url($template->storage_path);
            }
        } else {
            $exists = false;
            $storagePubPath = null;
        }

        // Si un storage_path est défini mais le fichier est introuvable, ne pas
        // basculer silencieusement vers un template vierge: remonter une erreur claire.
        if (!$exists && !empty($template->storage_path)) {
            \Log::warning('OO getTemplateOoConfig file missing for existing storage_path', [
                'template_id' => (string) $template->id,
                'storage_path' => (string) $template->storage_path,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Le fichier du modèle est introuvable sur le serveur. Réimportez le modèle avant ouverture dans OnlyOffice.',
            ], 422);
        }

        // Fallback robuste: créer un fichier bootstrap local seulement si le template Office
        // n'a vraiment aucun fichier associé.
        if (!$exists && empty($template->storage_path) && in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
            $this->ensureTemplateBootstrapFile($template->fresh() ?: $template);
            $template = $template->fresh() ?: $template;
            if ($template->storage_path) {
                if (str_starts_with($template->storage_path, 'images/')) {
                    $absCheck = public_path($template->storage_path);
                    $exists = file_exists($absCheck);
                    $storagePubPath = '/' . ltrim($template->storage_path, '/');
                } else {
                    $exists = \Illuminate\Support\Facades\Storage::disk('public')->exists($template->storage_path);
                    $storagePubPath = \Illuminate\Support\Facades\Storage::url($template->storage_path);
                }
            }
        }

        // Compute path prefix from the current request (e.g. /e-administration_laravel/public)
        $reqBasePath = rtrim(request()->getBaseUrl(), '/');
        $localDocUrl = $storagePubPath ? rtrim(request()->getSchemeAndHttpHost(), '/') . $reqBasePath . $storagePubPath : null;

        $base = $this->resolveAppPublicBaseUrl($appPublicUrl, $onlyofficeUrl);

        $buildDocUrl = function (string $baseUrl) use ($exists, $ext, $storagePubPath, $template): string {
            if (!$exists) {
                $blankMap = ['docx' => 'docx', 'xlsx' => 'xlsx', 'pptx' => 'pptx'];
                $blankType = $blankMap[$ext] ?? 'docx';
                return $baseUrl . '/oo-blank/' . $blankType;
            }

            if ($storagePubPath && str_starts_with((string) $template->storage_path, 'images/')) {
                return $baseUrl . $storagePubPath;
            }

            $expires = time() + 900;
            $access  = hash_hmac('sha256', 'tplfile|' . $template->id . '|' . $expires, (string) config('app.key'));
            return $baseUrl . '/api/oo-file/template/' . $template->id . '?expires=' . $expires . '&access=' . $access;
        };

        $docUrl = $buildDocUrl($base);

        $docUrl = preg_replace('/\s+/', '', (string) $docUrl);

        \Log::info('OO getTemplateOoConfig docUrl=' . $docUrl . ' template=' . $template->id);

        $docUrlAccessError = $this->validateOnlyofficeAccessibleUrl($docUrl);
        $docUrlAccessWarning = null;

        // Fallback automatique: si l'URL configurée contient /public et répond 404,
        // réessayer avec la même URL sans /public.
        if (
            $docUrlAccessError
            && str_contains($docUrlAccessError, ' répond 404 ')
            && str_ends_with($base, '/public')
        ) {
            $fallbackBase = substr($base, 0, -7);
            if ($fallbackBase !== '') {
                $fallbackDocUrl = preg_replace('/\s+/', '', (string) $buildDocUrl($fallbackBase));
                $fallbackError = $this->validateOnlyofficeAccessibleUrl($fallbackDocUrl);
                if (!$fallbackError) {
                    $oldBase = $base;
                    $base = $fallbackBase;
                    $docUrl = $fallbackDocUrl;
                    $docUrlAccessError = null;
                    \Log::warning('OO getTemplateOoConfig fallback base sans /public utilisé', [
                        'template' => $template->id,
                        'old_base' => $oldBase,
                        'new_base' => $fallbackBase,
                    ]);
                    \Log::info('OO getTemplateOoConfig docUrl fallback=' . $docUrl . ' template=' . $template->id);
                }
            }
        }

        if ($docUrlAccessError) {
            // Ne bloque pas l'éditeur sur timeout réseau (ngrok / tunnel instable).
            // On laisse OnlyOffice tenter le chargement réel du document.
            if (str_contains($docUrlAccessError, 'cURL error 28') || str_contains($docUrlAccessError, 'Operation timed out')) {
                $docUrlAccessWarning = $docUrlAccessError;
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $docUrlAccessError,
                    'docUrl' => $docUrl,
                ], 422);
            }
        }

        $docKey  = 'tpl-' . $template->id . '-' . time();
        $docType = $ext === 'xlsx' ? 'cell' : ($ext === 'pptx' ? 'slide' : ($ext === 'pdf' ? 'pdf' : 'word'));

        $tplHmac     = hash_hmac('sha256', 'cb|tpl|' . $template->id, (string) config('app.key'));
        $callbackUrl = $base . '/api/oo-callback/template/' . $template->id . '?access=' . $tplHmac;

        $userId   = 'admin-' . auth()->id();
        $userName = auth()->user()->name ?? 'Admin';

        // Config EXACTE qui sera passée à DocsAPI — le JWT signe exactement ce payload
        $ooConfigPayload = [
            'document' => [
                'fileType'    => $ext,
                'key'         => $docKey,
                'title'       => $template->name,
                'url'         => $docUrl,
                'permissions' => ['download' => false, 'edit' => true, 'print' => false],
            ],
            'documentType' => $docType,
            'editorConfig' => [
                'callbackUrl'   => $callbackUrl,
                'lang'          => 'fr',
                'mode'          => 'edit',
                'user'          => ['id' => $userId, 'name' => $userName],
                'customization' => [
                    'autosave'       => true,
                    'compactHeader'  => true,
                    'forcesave'      => true,
                    'hideRightMenu'  => true,
                ],
            ],
        ];

        $token = '';
        if ($onlyofficeSecret) {
            $header    = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
            $body      = rtrim(strtr(base64_encode(json_encode($ooConfigPayload)), '+/', '-_'), '=');
            $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", $onlyofficeSecret, true)), '+/', '-_'), '=');
            $token = "$header.$body.$signature";
        }

        return response()->json([
            'success'       => true,
            'template_id'   => $template->id,
            'template_name' => $template->name,
            'fileType'      => $ext,
            'has_file'      => $exists,
            'docUrl'        => $docUrl,
            'localDocUrl'   => $localDocUrl,
            'storagePubPath'=> $storagePubPath,
            'ooUrl'         => $onlyofficeUrl,
            'token'         => $token,
            'ooConfig'      => $ooConfigPayload,  // config complète prête pour DocsAPI
            'warning'       => $docUrlAccessWarning,
        ]);
    }

    private function validateOnlyofficeAccessibleUrl(string $url): ?string
    {
        $disableCert = filter_var(
            AppSetting::where('key', 'onlyoffice_disable_cert')->value('value') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        try {
            $response = $this->probeOnlyofficeUrl($url, $disableCert);

            if ($response->successful() || $response->redirect()) {
                // Vérification complémentaire SANS header ngrok-skip-browser-warning.
                // Si l'URL publique est un tunnel ngrok gratuit, OnlyOffice peut recevoir
                // la page d'avertissement (ERR_NGROK_6024) et ouvrir un document "blanc".
                $rawResponse = $this->probeOnlyofficeUrlRaw($url, $disableCert);
                $rawBody = (string) $rawResponse->body();
                if (str_contains($rawBody, 'ERR_NGROK_6024')) {
                    return 'URL publique bloquée par l\'interstitiel ngrok (ERR_NGROK_6024). OnlyOffice reçoit une page HTML au lieu du DOCX, ce qui provoque un éditeur vide. Utilisez une URL publique sans interstitiel (ngrok avec bypass côté client serveur, tunnel cloudflared, ou domaine public direct).';
                }

                return null;
            }

            return 'OnlyOffice ne peut pas télécharger le fichier car l\'URL publique configurée répond ' . $response->status() . ' : ' . $url . '. Vérifiez la valeur "URL publique de cette application" dans l\'onglet OnlyOffice.';
        } catch (\Throwable $e) {
            if (!$disableCert && str_contains($e->getMessage(), 'cURL error 60')) {
                try {
                    $response = $this->probeOnlyofficeUrl($url, true);
                    if ($response->successful() || $response->redirect()) {
                        $rawResponse = $this->probeOnlyofficeUrlRaw($url, true);
                        $rawBody = (string) $rawResponse->body();
                        if (str_contains($rawBody, 'ERR_NGROK_6024')) {
                            return 'URL publique bloquée par l\'interstitiel ngrok (ERR_NGROK_6024). OnlyOffice reçoit une page HTML au lieu du DOCX, ce qui provoque un éditeur vide. Utilisez une URL publique sans interstitiel (ngrok avec bypass côté client serveur, tunnel cloudflared, ou domaine public direct).';
                        }

                        return null;
                    }

                    return 'OnlyOffice ne peut pas télécharger le fichier car l\'URL publique configurée répond ' . $response->status() . ' : ' . $url . '. Vérifiez la valeur "URL publique de cette application" dans l\'onglet OnlyOffice.';
                } catch (\Throwable $retryException) {
                    return 'OnlyOffice ne peut pas joindre l\'URL publique du document : ' . $url . '. Détail : ' . $retryException->getMessage();
                }
            }

            return 'OnlyOffice ne peut pas joindre l\'URL publique du document : ' . $url . '. Détail : ' . $e->getMessage();
        }
    }

    private function probeOnlyofficeUrl(string $url, bool $disableCert = false)
    {
        $client = Http::timeout(10)
            ->withHeaders([
                'ngrok-skip-browser-warning' => '1',
                'Accept' => '*/*',
            ])
            ->withUserAgent('OnlyOfficeUrlProbe/1.0');

        if ($disableCert) {
            $client = $client->withoutVerifying();
        }

        $response = $client->head($url);
        if ($response->status() === 405) {
            $response = $client->get($url);
        }

        return $response;
    }

    private function probeOnlyofficeUrlRaw(string $url, bool $disableCert = false)
    {
        $client = Http::timeout(10)
            ->withHeaders([
                'Accept' => '*/*',
            ])
            ->withUserAgent('OnlyOfficeRawProbe/1.0');

        if ($disableCert) {
            $client = $client->withoutVerifying();
        }

        return $client->get($url);
    }

    public function ooTemplateCallback(Request $request, string $templateId)
    {
        // ── Vérification HMAC (même principe que document callback) ──────────
        $access   = (string) $request->query('access', '');
        $expected = hash_hmac('sha256', 'cb|tpl|' . $templateId, (string) config('app.key'));
        if (!hash_equals($expected, $access)) {
            \Log::warning('OO callback HMAC mismatch template=' . $templateId . ' received=' . substr($access, 0, 8) . '... expected=' . substr($expected, 0, 8) . '...');
            // Retourner 0 pour ne pas bloquer l'éditeur OO ("impossible d'enregistrer")
            return response()->json(['error' => 0]);
        }

        $data   = $request->json()->all();
        $status = (int) ($data['status'] ?? 0);

        \Log::info('OO callback template=' . $templateId . ' status=' . $status . ' has_url=' . (!empty($data['url']) ? '1' : '0'));

        // status 2 = session terminée avec changements | status 6 = forcesave/autosave
        // Certaines versions/paramétrages OO envoient status 4 avec URL de fichier à persister.
        if (in_array($status, [2, 4, 6], true) && !empty($data['url'])) {
            // ── Validation SSRF : l'URL doit provenir du serveur OO configuré ──
            $ooServerUrl = (string) AppSetting::where('key', 'onlyoffice_server_url')->value('value');
            $fileUrl     = (string) $data['url'];

            if ($ooServerUrl !== '') {
                $ooHost   = parse_url(rtrim($ooServerUrl, '/'), PHP_URL_HOST);
                $fileHost = parse_url($fileUrl, PHP_URL_HOST);
                if (!$ooHost || !$fileHost || strtolower($ooHost) !== strtolower($fileHost)) {
                    \Log::warning('OO template callback: URL rejetée (hôte non autorisé)', [
                        'template_id'  => $templateId,
                        'allowed_host' => $ooHost,
                        'received_host'=> $fileHost,
                    ]);
                    return response()->json(['error' => 0]);
                }
            }

            $template = \App\Models\DocumentTemplate::find($templateId);
            if ($template) {
                try {
                    $disableCert = filter_var(
                        AppSetting::where('key', 'onlyoffice_disable_cert')->value('value') ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    );

                    $client = Http::timeout(60);
                    if ($disableCert) {
                        $client = $client->withoutVerifying();
                    }

                    try {
                        $response = $client->get($fileUrl);
                    } catch (\Throwable $downloadError) {
                        // Fallback auto sur erreur certificat TLS (cURL 60)
                        if (!$disableCert && str_contains($downloadError->getMessage(), 'cURL error 60')) {
                            $response = Http::timeout(60)->withoutVerifying()->get($fileUrl);
                        } else {
                            throw $downloadError;
                        }
                    }

                    if ($response->successful()) {
                        $fileContent = $response->body();
                        $ext      = strtolower($template->file_type ?: 'docx');
                        $filename = 'tpl_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $templateId) . '_' . time() . '.' . $ext;
                        $destDir  = public_path('images/templates');
                        $savedAbsPath = '';
                        $savedStoragePath = '';

                        // Supprimer l'ancien fichier si existant dans le dossier attendu
                        if ($template->storage_path && str_starts_with($template->storage_path, 'images/templates/')) {
                            $old = public_path($template->storage_path);
                            if (file_exists($old)) {
                                @unlink($old);
                            }
                        }

                        // Essai 1: écriture legacy dans public/images/templates
                        try {
                            if (!is_dir($destDir)) {
                                mkdir($destDir, 0755, true);
                            }
                            file_put_contents($destDir . '/' . $filename, $fileContent);
                            $savedAbsPath = $destDir . '/' . $filename;
                            $savedStoragePath = 'images/templates/' . $filename;
                        } catch (\Throwable $writePublicError) {
                            // Essai 2 (fallback): disque public Laravel (storage/app/public/templates)
                            $fallbackStoragePath = 'templates/' . $filename;
                            \Illuminate\Support\Facades\Storage::disk('public')->put($fallbackStoragePath, $fileContent);
                            $savedAbsPath = \Illuminate\Support\Facades\Storage::disk('public')->path($fallbackStoragePath);
                            $savedStoragePath = $fallbackStoragePath;

                            \Log::warning('OO callback write fallback to storage/public', [
                                'template_id' => $templateId,
                                'error' => $writePublicError->getMessage(),
                                'saved_path' => $savedStoragePath,
                            ]);
                        }

                        $template->storage_path = $savedStoragePath;
                        $template->save();
                        \Log::info('OO file saved: ' . $savedStoragePath . ' size=' . strlen($fileContent));

                        // ── Extraction automatique des variables après sauvegarde OO ──
                        $absNewPath = $savedAbsPath;
                        if (in_array($ext, ['docx', 'xlsx', 'pptx']) && file_exists($absNewPath)) {
                            foreach ($this->extractVarsFromUploadedFile($absNewPath) as $fileVar) {
                                $this->firstOrCreateTemplateVariable(
                                    $template,
                                    (string) ($fileVar['key'] ?? 'var'),
                                    (string) ($fileVar['label'] ?? ($fileVar['key'] ?? 'var'))
                                );
                            }
                            \Log::info('OO vars extracted for template ' . $templateId);
                        }
                    } else {
                        \Log::warning('OO template callback: échec téléchargement', [
                            'template_id' => $templateId,
                            'http_status' => $response->status(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('OO callback save failed for template ' . $templateId . ': ' . $e->getMessage());
                }
            }
        } else {
            // Si callback sans URL et template Office sans fichier, créer un bootstrap pour sortir du blocage UI.
            try {
                $template = \App\Models\DocumentTemplate::find($templateId);
                if (
                    $template
                    && empty($template->storage_path)
                    && in_array(strtolower((string) ($template->file_type ?: 'docx')), ['docx', 'xlsx', 'pptx'], true)
                ) {
                    $this->ensureTemplateBootstrapFile($template);
                }
            } catch (\Throwable $e) {
                \Log::warning('OO callback bootstrap fallback error', [
                    'template_id' => $templateId,
                    'error' => $e->getMessage(),
                ]);
            }
            \Log::info('OO callback ignored template=' . $templateId . ' status=' . $status . ' reason=' . (!in_array($status, [2, 4, 6], true) ? 'status_not_persistable' : 'missing_url'));
        }

        return response()->json(['error' => 0]);
    }

    /**
     * Fichier template pour OnlyOffice via URL signée temporaire (sans session utilisateur).
     */
    public function ooTemplateFile(Request $request, string $templateId)
    {
        $expires = (int) $request->query('expires', 0);
        $access  = (string) $request->query('access', '');

        if ($expires <= 0 || $expires < time()) {
            abort(403, 'Lien expiré ou invalide');
        }

        $expected = hash_hmac('sha256', 'tplfile|' . $templateId . '|' . $expires, (string) config('app.key'));
        if (!hash_equals($expected, $access)) {
            abort(403, 'Lien expiré ou invalide');
        }

        $template = DocumentTemplate::findOrFail($templateId);
        if (!$template->storage_path) {
            abort(404, 'Template sans fichier');
        }

        $fileName = $template->file_name ?: ('template-' . $template->id);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: ($template->file_type ?: 'bin'));

        $mimeMap = [
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'pdf'  => 'application/pdf',
        ];
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';

        if (str_starts_with($template->storage_path, 'images/')) {
            $absPath = public_path($template->storage_path);
            if (!file_exists($absPath)) {
                abort(404, 'Fichier template introuvable');
            }

            return response()->file($absPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
                'Cache-Control' => 'no-store',
            ]);
        }

        if (!Storage::disk('public')->exists($template->storage_path)) {
            abort(404, 'Fichier template introuvable');
        }

        return Storage::disk('public')->response($template->storage_path, $fileName, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function storeTemplate(Request $request)
    {
        $this->guardPermission('administration.templates');
        $request->validate([
            'name'      => 'required|string|max:255',
            'file_name' => 'nullable|string|max:255',
            'file_type' => 'required|in:pdf,docx,xlsx,pptx',
            'content'   => 'nullable|string',
        ]);
        $adminScope = $this->resolveAdminScope();
        // Forcer l'administration si l'utilisateur est scoped
        $administrationId = ($adminScope && $adminScope['type'] === 'emitter')
            ? $adminScope['id']
            : ($request->input('administration_id') ?: null);
        $template = DocumentTemplate::create([
            'id'               => Str::uuid(),
            'name'             => $request->input('name'),
            'file_name'        => $request->input('file_name', ''),
            'file_type'        => $request->input('file_type'),
            'content'          => $request->input('content', ''),
            'administration_id'=> $administrationId,
            'created_by'       => auth()->id(),
        ]);

        // Créer immédiatement un fichier source pour les templates Office
        // afin d'éviter les templates "sans fichier" bloqués en CloseGuard.
        $this->ensureTemplateBootstrapFile($template);

        $this->saveDetectedTemplateVars(
            $template,
            array_values($this->extractVarsFromTemplateText($template->content))
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'       => true,
                'id'            => $template->id,
                'name'          => $template->name,
                'file_name'     => $template->file_name,
                'file_type'     => $template->file_type,
                'has_file'      => (bool) ($template->storage_path && file_exists(public_path($template->storage_path))),
                'administration'=> $template->administration?->name ?? null,
                'variables_url' => route('admin.index', ['tab' => 'templates', 'selected_template' => $template->id]),
                'share_url'     => route('admin.templates.share', $template->id),
            ]);
        }
        return back()->with('success', 'Template créé.')->withInput(['tab' => 'templates']);
    }

    private function ensureTemplateBootstrapFile(DocumentTemplate $template): void
    {
        $ext = strtolower((string) ($template->file_type ?: 'docx'));
        if (!in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
            return;
        }

        if ($template->storage_path) {
            if (str_starts_with($template->storage_path, 'images/')) {
                $existing = public_path($template->storage_path);
                if (file_exists($existing)) {
                    return;
                }
            } else {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($template->storage_path)) {
                    return;
                }
            }
        }

        $blankMap = [
            'docx' => public_path('empty_template.docx'),
            'xlsx' => public_path('blank_xlsx.xlsx'),
            'pptx' => public_path('blank_pptx.pptx'),
        ];

        $src = $blankMap[$ext] ?? null;
        if (!$src || !file_exists($src)) {
            \Log::warning('Template bootstrap skipped: blank file missing', [
                'template_id' => (string) $template->id,
                'ext' => $ext,
                'src' => $src,
            ]);
            return;
        }

        $destDir = public_path('images/templates');
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $fileName = 'tpl_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $template->id) . '_' . time() . '.' . $ext;
        $destPath = $destDir . DIRECTORY_SEPARATOR . $fileName;

        if (!@copy($src, $destPath)) {
            \Log::warning('Template bootstrap failed: copy error', [
                'template_id' => (string) $template->id,
                'src' => $src,
                'dest' => $destPath,
            ]);
            return;
        }

        if ($ext === 'docx') {
            $this->injectBootstrapDocxHint($destPath, $template);
        }

        $template->storage_path = 'images/templates/' . $fileName;
        $template->save();
    }

    private function injectBootstrapDocxHint(string $absDocxPath, DocumentTemplate $template): void
    {
        if (!class_exists('ZipArchive') || !file_exists($absDocxPath)) {
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($absDocxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            return;
        }

        $hint = 'NOUVEAU TEMPLATE : saisissez votre contenu ici puis enregistrez (Ctrl+S).';
        $hint2 = 'Exemple de variable: {{ NOM DU DEMANDEUR }}';
        $safe1 = htmlspecialchars($hint, ENT_XML1, 'UTF-8');
        $safe2 = htmlspecialchars($hint2, ENT_XML1, 'UTF-8');

        // Insère un paragraphe visible au début du document pour éviter l'effet "page blanche".
        $insert = '<w:p><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">' . $safe1 . '</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t xml:space="preserve">' . $safe2 . '</w:t></w:r></w:p>';

        $newXml = preg_replace('/<w:body>/i', '<w:body>' . $insert, $xml, 1);
        if (is_string($newXml) && $newXml !== $xml) {
            $zip->addFromString('word/document.xml', $newXml);
        }

        $zip->close();
    }

    public function updateTemplate(Request $request, DocumentTemplate $template)
    {
        $this->guardPermission('administration.templates');
        $request->validate([
            'name'      => 'required|string|max:255',
            'file_name' => 'nullable|string|max:255',
            'file_type' => 'required|in:pdf,docx,xlsx,pptx',
            'content'   => 'nullable|string',
        ]);
        $template->update([
            'name'             => $request->input('name'),
            'file_name'        => $request->input('file_name', $template->file_name),
            'file_type'        => $request->input('file_type'),
            'content'          => $request->input('content', ''),
            'administration_id'=> $request->input('administration_id', $template->administration_id),
        ]);

        $this->saveDetectedTemplateVars(
            $template,
            array_values($this->extractVarsFromTemplateText($template->content))
        );

        return back()->with('success', 'Template mis à jour.')->withInput(['tab' => 'templates']);
    }

    public function destroyTemplate(DocumentTemplate $template)
    {
        $this->guardPermission('administration.templates');
        $template->delete();
        return back()->with('success', 'Template supprimé.')->withInput(['tab' => 'templates']);
    }

    public function storeTemplateVariable(Request $request, DocumentTemplate $template)
    {
        $request->validate([
            'label'      => 'required|string|max:255',
            'field_type' => 'required|in:text,date,number,select,textarea',
        ]);
        $key = \Str::slug($request->input('label'), '_');
        $template->variables()->updateOrCreate(
            ['key' => $key],
            [
                'id'          => Str::uuid(),
                'label'       => $request->input('label'),
                'field_type'  => $request->input('field_type'),
                'placeholder' => $request->input('placeholder', ''),
            ]
        );
        return back()->with('success', 'Variable ajoutée.')->withInput(['tab' => 'templates', 'selected_template' => $template->id]);
    }

    public function destroyTemplateVariable(DocumentTemplate $template, string $variableId)
    {
        $template->variables()->where('id', $variableId)->delete();
        return back()->with('success', 'Variable supprimée.')->withInput(['tab' => 'templates', 'selected_template' => $template->id]);
    }

    public function updateTemplateVariable(Request $request, DocumentTemplate $template, string $variableId)
    {
        $request->validate([
            'label'      => 'required|string|max:255',
            'field_type' => 'required|in:text,date,number,select,textarea',
        ]);
        $template->variables()->where('id', $variableId)->update([
            'label'       => $request->input('label'),
            'field_type'  => $request->input('field_type'),
            'placeholder' => $request->input('placeholder', ''),
        ]);
        return back()->with('success', 'Variable modifiée.')->withInput(['tab' => 'templates', 'selected_template' => $template->id]);
    }

    public function updateTemplateShare(Request $request, DocumentTemplate $template)
    {
        $userId  = $request->input('user_id');
        $action  = $request->input('action', 'toggle'); // 'add' | 'remove' | 'toggle'
        $setting = AppSetting::firstOrCreate(['key' => 'template_share_map'], ['value' => '{}']);
        $map     = json_decode($setting->value ?: '{}', true) ?: [];
        $shared  = $map[$template->id] ?? [];
        if ($action === 'add' || ($action === 'toggle' && !in_array($userId, $shared))) {
            $shared[] = $userId;
        } else {
            $shared = array_values(array_filter($shared, fn($id) => $id !== $userId));
        }
        // Récupérer les nouveaux bénéficiaires (présents maintenant, absents avant)
        $prevShared = $map[$template->id] ?? [];
        $map[$template->id] = array_values(array_unique($shared));
        $newlyAdded = array_diff($map[$template->id], $prevShared);
        $setting->update(['value' => json_encode($map)]);

        // Notifier les utilisateurs nouvellement ajoutés
        foreach ($newlyAdded as $uid) {
            NotificationService::templateShared($template, $uid, auth()->user()->name);
        }

        return back()->with('success', 'Partage mis à jour.')->withInput(['tab' => 'templates', 'selected_template' => $template->id]);
    }

    public function saveTemplateZones(Request $request, DocumentTemplate $template)
    {
        $zones = $request->input('zones', []);
        if (!is_array($zones)) {
            return response()->json(['success' => false, 'message' => 'Format de zones invalide.'], 422);
        }
        $sanitized = array_map(function ($z) {
            return [
                'x'      => round((float) ($z['x'] ?? 0), 4),
                'y'      => round((float) ($z['y'] ?? 0), 4),
                'w'      => round((float) ($z['w'] ?? 22), 4),
                'h'      => round((float) ($z['h'] ?? 18), 4),
                'sealed' => (bool) ($z['sealed'] ?? true),
                'label'  => trim(strip_tags((string) ($z['label'] ?? ''))),
            ];
        }, array_values($zones));

        $template->update(['signature_zones' => json_encode($sanitized)]);
        return response()->json(['success' => true, 'message' => count($sanitized) . ' zone(s) de signature enregistrée(s).' ]);
    }

    public function forceSaveTemplate(Request $request, DocumentTemplate $template)
    {
        $request->validate([
            'doc_key' => 'required|string|max:255',
        ]);

        $onlyofficeUrl = rtrim((string) (AppSetting::where('key', 'onlyoffice_server_url')->value('value') ?: ''), '/');
        $onlyofficeSecret = (string) (AppSetting::where('key', 'onlyoffice_secret')->value('value') ?: '');

        if ($onlyofficeUrl === '') {
            return response()->json([
                'success' => false,
                'message' => 'Serveur OnlyOffice non configuré.',
            ], 422);
        }

        $disableCert = filter_var(
            AppSetting::where('key', 'onlyoffice_disable_cert')->value('value') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $docKey = (string) $request->input('doc_key');
        $commandPayload = [
            'c' => 'forcesave',
            'key' => $docKey,
            'userdata' => 'tpl-force-' . $template->id . '-' . time(),
        ];

        if ($onlyofficeSecret !== '') {
            $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
            $body = rtrim(strtr(base64_encode(json_encode($commandPayload)), '+/', '-_'), '=');
            $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", $onlyofficeSecret, true)), '+/', '-_'), '=');
            $jwt = "$header.$body.$signature";
            $commandPayload['token'] = $jwt;
        }

        try {
            $client = Http::timeout(20)->acceptJson();
            if ($disableCert) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($onlyofficeUrl . '/coauthoring/CommandService.ashx', $commandPayload);
            $data = $response->json();
            $errorCode = is_array($data) ? (int) ($data['error'] ?? -1) : -1;

            Log::info('OnlyOffice CommandService forcesave', [
                'template_id' => (string) $template->id,
                'doc_key' => $docKey,
                'http_status' => $response->status(),
                'oo_error' => $errorCode,
                'oo_response' => $data,
            ]);

            if ($response->successful() && $errorCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Forcesave déclenché.',
                ]);
            }

            // error=4 : aucune modification avant le forcesave → session déjà fermée ou doc non modifié
            // Ce n'est pas une vraie erreur, le fichier est déjà dans son dernier état.
            if ($errorCode === 4 || $errorCode === 1) {
                return response()->json([
                    'success' => true,
                    'message' => 'Document déjà sauvegardé (aucun changement en attente).',
                    'oo_code' => $errorCode,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'OnlyOffice a refusé le forcesave (code ' . $errorCode . ').',
                'details' => $data,
            ], 422);
        } catch (\Throwable $e) {
            Log::warning('OnlyOffice CommandService forcesave failed', [
                'template_id' => (string) $template->id,
                'doc_key' => $docKey,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur réseau lors du forcesave OnlyOffice: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Règles de routage ─────────────────────────────────────────────────────
    public function storeRoutingRule(Request $request)
    {
        $data = $request->validate([
            'template_id'        => 'required|exists:document_templates,id',
            'target_type'        => 'nullable|in:recipient,user',
            'recipient_id'       => 'nullable|exists:recipient_administrations,id',
            'target_user_id'     => 'nullable|exists:users,id',
            'condition_field'    => 'nullable|string|max:100',
            'condition_operator' => 'nullable|string|max:20',
            'condition_value'    => 'nullable|string|max:255',
            'priority'           => 'integer|min:0',
        ]);

        $targetType = (string) ($data['target_type'] ?? 'recipient');
        $recipientId = $data['recipient_id'] ?? null;
        $targetUserId = $data['target_user_id'] ?? null;

        if ($targetType === 'recipient' && !$recipientId) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Veuillez sélectionner une administration destinataire.',
            ]);
        }

        if ($targetType === 'user' && !$targetUserId) {
            throw ValidationException::withMessages([
                'target_user_id' => 'Veuillez sélectionner un utilisateur de la même administration.',
            ]);
        }

        $adminScope = $this->resolveAdminScope();
        $template = DocumentTemplate::query()->select(['id', 'administration_id'])->findOrFail($data['template_id']);

        if ($adminScope && $adminScope['type'] === 'recipient' && $targetType === 'recipient' && $recipientId !== $adminScope['id']) {
            throw ValidationException::withMessages([
                'recipient_id' => 'Cette règle doit cibler votre administration.',
            ]);
        }

        if ($targetType === 'user') {
            $assignmentQuery = UserDirectionAssignment::query()->where('user_id', $targetUserId);

            if ($adminScope) {
                $assignmentQuery
                    ->where('direction_scope_type', $adminScope['type'])
                    ->where('direction_scope_id', $adminScope['id']);
            } elseif (!empty($template->administration_id)) {
                $assignmentQuery
                    ->where('direction_scope_type', 'emitter')
                    ->where('direction_scope_id', $template->administration_id);
            }

            if (!$assignmentQuery->exists()) {
                throw ValidationException::withMessages([
                    'target_user_id' => 'L\'utilisateur sélectionné n\'appartient pas à la même administration.',
                ]);
            }

            $recipientId = null;
        } else {
            $targetUserId = null;
        }

        RoutingRule::create([
            'id' => Str::uuid(),
            'template_id' => $data['template_id'],
            'target_type' => $targetType,
            'recipient_id' => $recipientId,
            'target_user_id' => $targetUserId,
            'condition_field' => $data['condition_field'] ?? null,
            'condition_operator' => $data['condition_operator'] ?? null,
            'condition_value' => $data['condition_value'] ?? null,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => true,
        ]);

        return back()->with('success', 'Règle de routage créée.')->withInput(['tab' => 'routing']);
    }

    public function destroyRoutingRule(RoutingRule $routingRule)
    {
        $routingRule->delete();
        return back()->with('success', 'Règle supprimée.')->withInput(['tab' => 'routing']);
    }

    // ── Entités sous tutelle ─────────────────────────────────────────────────
    public function storeSubEntity(Request $request)
    {
        $adminScope = $this->resolveAdminScope();
        $request->validate([
            'scope_type'        => $adminScope ? 'nullable|in:emitter,recipient' : 'required|in:emitter,recipient',
            'scope_id'          => $adminScope ? 'nullable|string|max:50' : 'required|string|max:50',
            'name'              => 'required|string|max:255',
            'code'              => 'required|string|max:50',
            'parent_code'       => 'nullable|string|max:50',
            'direction_type_id' => 'nullable|string',
            'manager_name'      => 'nullable|string|max:255',
            'manager_email'     => 'nullable|email|max:255',
            'description'       => 'nullable|string',
        ]);

        // Forcer scope_type + scope_id si l'utilisateur est scoped
        if (isset($adminScope) && $adminScope) {
            $scopeType = $adminScope['type'];
            $scopeId   = $adminScope['id'];
        } else {
            $scopeType = $request->input('scope_type');
            $scopeId   = $request->input('scope_id');
        }
        $code      = strtoupper(trim((string) $request->input('code')));

        // Unicité du code par administration (scope_type + scope_id), pas globale
        $exists = SubEntity::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['code' => 'Ce code est déjà utilisé par une entité de cette administration.'])
                ->withInput();
        }

        $data = $request->only([
            'name', 'parent_code',
            'direction_type_id', 'manager_name', 'manager_email', 'description',
        ]);
        $data['scope_type'] = $scopeType;
        $data['scope_id']   = $scopeId;
        $data['code']      = $code;
        $data['is_active'] = $request->boolean('is_active', true);
        SubEntity::create($data);
        return back()->with('success', 'Entité sous tutelle créée.')->withInput(['tab' => 'sub-entities']);
    }

    public function updateSubEntity(Request $request, SubEntity $subEntity)
    {
        $request->validate([
            'scope_type'        => 'required|in:emitter,recipient',
            'scope_id'          => 'required|string|max:50',
            'name'              => 'required|string|max:255',
            'code'              => 'required|string|max:50',
            'parent_code'       => 'nullable|string|max:50',
            'direction_type_id' => 'nullable|string',
            'manager_name'      => 'nullable|string|max:255',
            'manager_email'     => 'nullable|email|max:255',
            'description'       => 'nullable|string',
        ]);

        $scopeType = $request->input('scope_type');
        $scopeId   = $request->input('scope_id');
        $code      = strtoupper(trim((string) $request->input('code')));

        // Unicité du code par administration (scope_type + scope_id), en excluant l'entité elle-même
        $exists = SubEntity::where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->where('id', '!=', $subEntity->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['code' => 'Ce code est déjà utilisé par une entité de cette administration.'])
                ->withInput();
        }

        $data = $request->only([
            'scope_type', 'scope_id', 'name', 'parent_code',
            'direction_type_id', 'manager_name', 'manager_email', 'description',
        ]);
        $data['code']      = $code;
        $data['is_active'] = $request->boolean('is_active', true);
        $subEntity->update($data);
        return back()->with('success', 'Entité sous tutelle mise à jour.')->withInput(['tab' => 'sub-entities']);
    }

    public function destroySubEntity(SubEntity $subEntity)
    {
        $subEntity->delete();
        return back()->with('success', 'Entité sous tutelle supprimée.')->withInput(['tab' => 'sub-entities']);
    }

    // ── Actes demandés ────────────────────────────────────────────────────────
    public function storeRequestedAct(Request $request)
    {
        $data = $request->validate([
            'administration_id'  => 'nullable|string',
            'direction_code'     => 'nullable|string|max:50',
            'document_name'      => 'required|string|max:255',
            'required_documents' => 'nullable|string',
            'applicant_fields'   => 'nullable|string',
            'auto_generate_enabled' => 'nullable|boolean',
            'auto_template_id' => 'nullable|string|exists:document_templates,id',
            'unique_key_field' => 'nullable|string|max:120',
        ]);
        // Forcer l'administration si l'utilisateur est scoped
        $adminScope = $this->resolveAdminScope();
        if ($adminScope && $adminScope['type'] === 'emitter') {
            $data['administration_id'] = $adminScope['id'];
        }

        $autoGenerateEnabled = $request->boolean('auto_generate_enabled');
        $data['auto_generate_enabled'] = $autoGenerateEnabled;
        $data['auto_template_id'] = $autoGenerateEnabled ? ($data['auto_template_id'] ?? null) : null;
        $data['unique_key_field'] = $autoGenerateEnabled ? trim((string) ($data['unique_key_field'] ?? '')) : null;

        if ($autoGenerateEnabled && empty($data['auto_template_id'])) {
            return back()->withErrors(['auto_template_id' => 'Veuillez sélectionner un template pour l\'auto-génération.'])->withInput();
        }

        if ($autoGenerateEnabled && $data['unique_key_field'] === '') {
            return back()->withErrors(['unique_key_field' => 'Veuillez saisir le champ clé unique.'])->withInput();
        }

        if ($autoGenerateEnabled && !empty($data['administration_id'])) {
            $templateAdminId = (string) (DocumentTemplate::query()->whereKey($data['auto_template_id'])->value('administration_id') ?? '');
            if ($templateAdminId !== '' && $templateAdminId !== (string) $data['administration_id']) {
                return back()->withErrors(['auto_template_id' => 'Le template sélectionné doit appartenir à la même administration.'])->withInput();
            }
        }

        $data['required_documents'] = json_decode($data['required_documents'] ?? '[]', true) ?: [];
        $data['applicant_fields']   = json_decode($data['applicant_fields']   ?? '[]', true) ?: [];
        RequestedAct::create($data);
        return back()->with('success', 'Acte demandé créé.')->withInput(['tab' => 'requested-acts']);
    }

    public function updateRequestedAct(Request $request, RequestedAct $requestedAct)
    {
        $data = $request->validate([
            'administration_id'  => 'required|string',
            'direction_code'     => 'nullable|string|max:50',
            'document_name'      => 'required|string|max:255',
            'required_documents' => 'nullable|string',
            'applicant_fields'   => 'nullable|string',
            'auto_generate_enabled' => 'nullable|boolean',
            'auto_template_id' => 'nullable|string|exists:document_templates,id',
            'unique_key_field' => 'nullable|string|max:120',
        ]);

        $autoGenerateEnabled = $request->boolean('auto_generate_enabled');
        $data['auto_generate_enabled'] = $autoGenerateEnabled;
        $data['auto_template_id'] = $autoGenerateEnabled ? ($data['auto_template_id'] ?? null) : null;
        $data['unique_key_field'] = $autoGenerateEnabled ? trim((string) ($data['unique_key_field'] ?? '')) : null;

        if ($autoGenerateEnabled && empty($data['auto_template_id'])) {
            return back()->withErrors(['auto_template_id' => 'Veuillez sélectionner un template pour l\'auto-génération.'])->withInput();
        }

        if ($autoGenerateEnabled && $data['unique_key_field'] === '') {
            return back()->withErrors(['unique_key_field' => 'Veuillez saisir le champ clé unique.'])->withInput();
        }

        if ($autoGenerateEnabled) {
            $templateAdminId = (string) (DocumentTemplate::query()->whereKey($data['auto_template_id'])->value('administration_id') ?? '');
            if ($templateAdminId !== '' && $templateAdminId !== (string) $data['administration_id']) {
                return back()->withErrors(['auto_template_id' => 'Le template sélectionné doit appartenir à la même administration.'])->withInput();
            }
        }

        $data['required_documents'] = json_decode($data['required_documents'] ?? '[]', true) ?: [];
        $data['applicant_fields']   = json_decode($data['applicant_fields']   ?? '[]', true) ?: [];
        $requestedAct->update($data);
        return back()->with('success', 'Acte demandé mis à jour.')->withInput(['tab' => 'requested-acts']);
    }

    public function destroyRequestedAct(RequestedAct $requestedAct)
    {
        $requestedAct->delete();
        return back()->with('success', 'Acte demandé supprimé.')->withInput(['tab' => 'requested-acts']);
    }

    // ── Profils / Rôles ───────────────────────────────────────────────────────
    public function storeProfile(Request $request)
    {
        $this->guardPermission('administration.user-profiles');
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string|max:500',
            'administration_type' => 'nullable|in:emitter,recipient',
            'administration_id'   => 'nullable|string|max:36',
            'permissions'         => 'nullable|array',
        ]);
        $menuPermissions = $data['permissions'] ?? [];
        $adminScope = $this->resolveAdminScope();
        $administrationSelection = $this->resolveProfileAdministrationSelection($data, $adminScope);

        AdministrationProfile::create([
            'id'                  => Str::uuid(),
            'name'                => $data['name'],
            'description'         => $data['description'] ?? '',
            'administration_type' => $administrationSelection['administration_type'],
            'administration_id'   => $administrationSelection['administration_id'],
            'permissions'         => [
                'description'     => $data['description'] ?? '',
                'menuPermissions' => $menuPermissions,
            ],
        ]);
        return back()->with('success', 'Profil créé.')->withInput(['tab' => 'user-profiles']);
    }

    public function updateProfile(Request $request, AdministrationProfile $profile)
    {
        $this->guardPermission('administration.user-profiles');
        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string|max:500',
            'administration_type' => 'nullable|in:emitter,recipient',
            'administration_id'   => 'nullable|string|max:36',
            'permissions'         => 'nullable|array',
        ]);
        $menuPermissions = $data['permissions'] ?? [];
        $adminScope = $this->resolveAdminScope();
        $administrationSelection = $this->resolveProfileAdministrationSelection($data, $adminScope);

        $profile->update([
            'name'                => $data['name'],
            'description'         => $data['description'] ?? '',
            'administration_type' => $administrationSelection['administration_type'],
            'administration_id'   => $administrationSelection['administration_id'],
            'permissions'         => [
                'description'     => $data['description'] ?? '',
                'menuPermissions' => $menuPermissions,
            ],
        ]);
        return back()->with('success', 'Profil mis à jour.')->withInput(['tab' => 'user-profiles']);
    }

    public function assignProfile(Request $request)
    {
        $this->guardPermission('administration.user-profiles');
        $data = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'profile_id' => 'nullable|exists:administration_profiles,id',
        ]);
        \App\Models\User::where('id', $data['user_id'])->update(['profile_id' => $data['profile_id'] ?: null]);
        return back()->with('success', 'Profil assigné.')->withInput(['tab' => 'user-profiles']);
    }

    public function destroyProfile(AdministrationProfile $profile)
    {
        $this->guardPermission('administration.user-profiles');
        $profile->delete();
        return back()->with('success', 'Profil supprimé.')->withInput(['tab' => 'user-profiles']);
    }

    // ── Instructions de traitement courrier ───────────────────────────────────
    public function storeInstruction(Request $request)
    {
        $data = $request->validate([
            'nom'         => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
        ]);
        Instruction::create(array_merge($data, ['actif' => true]));
        return back()->with('success', 'Instruction créée.')->withInput(['tab' => 'instructions']);
    }

    public function updateInstruction(Request $request, Instruction $instruction)
    {
        $data = $request->validate([
            'nom'         => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
        ]);
        $instruction->update([
            'nom'         => $data['nom'],
            'description' => $data['description'] ?? null,
            'actif'       => $request->boolean('actif', true),
        ]);
        return back()->with('success', 'Instruction mise à jour.')->withInput(['tab' => 'instructions']);
    }

    public function destroyInstruction(Instruction $instruction)
    {
        $instruction->delete();
        return back()->with('success', 'Instruction supprimée.')->withInput(['tab' => 'instructions']);
    }

    // ── Utilisateurs (onglet admin) ────────────────────────────────────────
    public function storeUserTab(Request $request)
    {
        $this->guardPermission('administration.users');
        $data = $request->validate([
            'nom'            => 'required|string|max:100',
            'prenoms'        => 'nullable|string|max:150',
            'name'           => 'required|string|max:191',
            'email'          => 'required|email|unique:users,email',
            'phone'          => 'nullable|string|max:20',
            'role'           => 'required|in:admin,user,signer,manager',
            'profile_id'     => 'nullable|uuid|exists:administration_profiles,id',
            'password'       => 'required|string|min:8|confirmed',
            'status'         => 'nullable|in:active,inactive,suspended',
            'quota'          => 'nullable|string|max:50',
            'avatar'         => 'nullable|image|max:5120',
            'administration_type'  => 'nullable|in:emitter,recipient',
            'administration_id'    => 'nullable|string|max:36',
            'sub_entity_id'        => 'nullable|string|max:36',
        ]);

        try {
            $fullName = trim(($data['prenoms'] ?? '') . ' ' . $data['nom']);
            $avatarPath = null;
            if ($request->hasFile('avatar')) {
                ClamAvScanner::scan($request->file('avatar'), 'utilisateurs');
                $storedPath = $request->file('avatar')->store('avatars', 'public');
                $avatarPath = 'storage/' . ltrim($storedPath, '/');
            }

            $selectedAdminType = $data['administration_type'] ?? null;
            $selectedAdminId = $data['administration_id'] ?? null;
            $selectedProfileId = $data['profile_id'] ?? null;

            if (empty($selectedAdminType) || empty($selectedAdminId)) {
                return back()
                    ->withInput()
                    ->withErrors(['users' => 'Administration et type d\'administration sont obligatoires.']);
            }

            $selectedProfile = $selectedProfileId ? AdministrationProfile::find($selectedProfileId) : null;
            $profileMatchesScope = $selectedProfile
                && $selectedProfile->administration_id === $selectedAdminId
                && ($selectedProfile->effective_administration_type ?? 'emitter') === $selectedAdminType;

            if (!$profileMatchesScope) {
                $fallbackProfile = AdministrationProfile::query()
                    ->where('administration_id', $selectedAdminId)
                    ->where('administration_type', $selectedAdminType)
                    ->orderBy('name')
                    ->first();

                if ($fallbackProfile) {
                    $selectedProfileId = $fallbackProfile->id;
                } else {
                    return back()
                        ->withInput()
                        ->withErrors(['users' => 'Aucun profil trouvé pour cette administration. Veuillez d\'abord créer un profil.']);
                }
            }

            $payload = [
                'name'       => $data['name'],
                'full_name'  => $fullName,
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'phone'      => $data['phone'] ?? null,
                'role'       => $data['role'],
                'profile_id' => $selectedProfileId,
                'status'     => $data['status'] ?? 'active',
                'quota'      => $data['quota'] ?? null,
                'avatar'     => $avatarPath,
                'locale'     => 'fr',
            ];

            try {
                $user = User::create($payload);
            } catch (QueryException $e) {
                $msg = strtolower($e->getMessage());
                if (str_contains($msg, 'unknown column') && str_contains($msg, 'locale')) {
                    unset($payload['locale']);
                    $user = User::create($payload);
                } else {
                    throw $e;
                }
            }

            if (!empty($data['administration_type']) && !empty($data['administration_id'])) {
                $subEntity = $data['sub_entity_id'] ? SubEntity::find($data['sub_entity_id']) : null;
                $admin = $data['administration_type'] === 'emitter'
                    ? IssuingAdministration::find($data['administration_id'])
                    : RecipientAdministration::find($data['administration_id']);
                UserDirectionAssignment::create([
                    'user_id'              => $user->id,
                    'direction_scope_type' => $data['administration_type'],
                    'direction_scope_id'   => $data['administration_id'],
                    'sub_entity_code'      => $subEntity?->code ?? null,
                    'direction_label'      => $subEntity?->name ?? $admin?->name ?? '',
                ]);
            }

            $this->sendAccountCreationEmail($user);

            return redirect()->route('admin.index', ['tab' => 'users'])->with('success', 'Utilisateur créé avec succès.');
        } catch (\Throwable $e) {
            Log::error('storeUserTab failed', [
                'email' => $data['email'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['users' => 'Échec de création utilisateur: ' . $e->getMessage()]);
        }
    }

    public function updateUserTab(Request $request, User $user)
    {
        $this->guardPermission('administration.users');
        $data = $request->validate([
            'nom'            => 'required|string|max:100',
            'prenoms'        => 'nullable|string|max:150',
            'name'           => 'required|string|max:191',
            'email'          => 'required|email|unique:users,email,' . $user->id,
            'phone'          => 'nullable|string|max:20',
            'role'           => 'required|in:admin,user,signer,manager',
            'profile_id'     => 'nullable|uuid|exists:administration_profiles,id',
            'password'       => 'nullable|string|min:8|confirmed',
            'status'         => 'nullable|in:active,inactive,suspended',
            'quota'          => 'nullable|string|max:50',
            'avatar'         => 'nullable|image|max:5120',
            'administration_type'  => 'nullable|in:emitter,recipient',
            'administration_id'    => 'nullable|string|max:36',
            'sub_entity_id'        => 'nullable|string|max:36',
        ]);

        $selectedAdminType = $data['administration_type'] ?? null;
        $selectedAdminId = $data['administration_id'] ?? null;
        $selectedProfileId = $data['profile_id'] ?? $user->profile_id;

        if (!empty($selectedAdminType) && !empty($selectedAdminId)) {
            $selectedProfile = $selectedProfileId ? AdministrationProfile::find($selectedProfileId) : null;
            $profileMatchesScope = $selectedProfile
                && $selectedProfile->administration_id === $selectedAdminId
                && ($selectedProfile->effective_administration_type ?? 'emitter') === $selectedAdminType;

            if (!$profileMatchesScope) {
                $fallbackProfile = AdministrationProfile::query()
                    ->where('administration_id', $selectedAdminId)
                    ->where('administration_type', $selectedAdminType)
                    ->orderBy('name')
                    ->first();

                if ($fallbackProfile) {
                    $selectedProfileId = $fallbackProfile->id;
                } else {
                    return back()
                        ->withInput()
                        ->withErrors(['users' => 'Aucun profil trouvé pour cette administration. Veuillez d\'abord créer un profil.']);
                }
            }
        }

        $fullName = trim(($data['prenoms'] ?? '') . ' ' . $data['nom']);
        $update = [
            'name'       => $data['name'],
            'full_name'  => $fullName,
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? $user->phone,
            'role'       => $data['role'],
            'profile_id' => $selectedProfileId,
            'status'     => $data['status'] ?? $user->status,
            'quota'      => $data['quota'] ?? $user->quota,
        ];
        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }
        if ($request->hasFile('avatar')) {
            if ($user->avatar && str_starts_with($user->avatar, 'storage/')) {
                $oldStorage = ltrim(substr($user->avatar, strlen('storage/')), '/');
                if (Storage::disk('public')->exists($oldStorage)) {
                    Storage::disk('public')->delete($oldStorage);
                }
            } elseif ($user->avatar && str_starts_with($user->avatar, 'images/')) {
                $old = public_path($user->avatar);
                if (file_exists($old)) @unlink($old);
            }
            ClamAvScanner::scan($request->file('avatar'), 'utilisateurs');
            $storedPath = $request->file('avatar')->store('avatars', 'public');
            $update['avatar'] = 'storage/' . ltrim($storedPath, '/');
        }
        $user->update($update);

        // Mise à jour direction
        $assignment = UserDirectionAssignment::where('user_id', $user->id)->first();
        if (!empty($data['administration_type']) && !empty($data['administration_id'])) {
            $subEntity = $data['sub_entity_id'] ? SubEntity::find($data['sub_entity_id']) : null;
            $admin = $data['administration_type'] === 'emitter'
                ? IssuingAdministration::find($data['administration_id'])
                : RecipientAdministration::find($data['administration_id']);
            $assignData = [
                'user_id'              => $user->id,
                'direction_scope_type' => $data['administration_type'],
                'direction_scope_id'   => $data['administration_id'],
                'sub_entity_code'      => $subEntity?->code ?? null,
                'direction_label'      => $subEntity?->name ?? $admin?->name ?? '',
            ];
            if ($assignment) $assignment->update($assignData);
            else UserDirectionAssignment::create($assignData);
        } elseif ($assignment) {
            $assignment->delete();
        }

        return redirect()->route('admin.index', ['tab' => 'users'])->with('success', 'Utilisateur mis à jour.');
    }

    public function destroyUserTab(User $user)
    {
        $this->guardPermission('administration.users');
        if ($user->avatar && str_starts_with($user->avatar, 'storage/')) {
            $oldStorage = ltrim(substr($user->avatar, strlen('storage/')), '/');
            if (Storage::disk('public')->exists($oldStorage)) {
                Storage::disk('public')->delete($oldStorage);
            }
        } elseif ($user->avatar && str_starts_with($user->avatar, 'images/')) {
            $old = public_path($user->avatar);
            if (file_exists($old)) @unlink($old);
        }
        UserDirectionAssignment::where('user_id', $user->id)->delete();
        $user->delete();
        return redirect()->route('admin.index', ['tab' => 'users'])->with('success', 'Utilisateur supprimé.');
    }

    public function toggleUserStatusTab(User $user)
    {
        $this->guardPermission('administration.users');
        $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);
        return redirect()->route('admin.index', ['tab' => 'users'])->with('success', 'Statut mis à jour.');
    }

    public function toggleUserTwoFactorTab(User $user)
    {
        $this->guardPermission('administration.users');

        if (!Schema::hasColumn('users', 'two_factor_enabled')) {
            return redirect()->route('admin.index', ['tab' => 'users'])
                ->withErrors(['users' => 'Colonne 2FA absente. Exécutez les migrations puis réessayez.']);
        }

        $enabled = (bool) ($user->two_factor_enabled ?? true);

        $user->forceFill([
            'two_factor_enabled' => !$enabled,
        ])->save();

        if ($enabled) {
            $user->forceFill([
                'two_factor_code' => null,
                'two_factor_expires_at' => null,
            ])->save();
        }

        return redirect()->route('admin.index', ['tab' => 'users'])->with(
            'success',
            !$enabled
                ? 'Double authentification activée pour cet utilisateur.'
                : 'Double authentification désactivée pour cet utilisateur.'
        );
    }

    // ── Enrichissement IA (Ollama) des variables d'un template ─────────────────────
    public function aiEnrichTemplateVars(\App\Models\DocumentTemplate $template)
    {
        try {
            $ollama = new \App\Services\OllamaService();

            // Vérifier la disponibilité d'Ollama
            $available = $ollama->isAvailable();

            // Toujours refaire une détection brute pour capter les nouvelles balises
            // ajoutées récemment dans OnlyOffice (ou dans le contenu HTML du template).
            $detected = $this->extractVarsFromTemplateText($template->content);

            if ($template->storage_path) {
                $ext = strtolower(pathinfo($template->storage_path, PATHINFO_EXTENSION));
                if (in_array($ext, ['docx', 'xlsx', 'pptx'])) {
                    $absPath = str_starts_with($template->storage_path, 'images/')
                        ? public_path($template->storage_path)
                        : Storage::disk('public')->path($template->storage_path);
                    foreach ($this->extractVarsFromUploadedFile($absPath) as $fileVar) {
                        $detected[$fileVar['key']] = $fileVar;
                    }
                }
            }

            if (!empty($detected)) {
                $this->saveDetectedTemplateVars($template, array_values($detected));
            }

            $existingVars = $template->variables()->get();
            if ($existingVars->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune variable détectée dans le fichier actuel. Si vous venez d\'éditer dans OnlyOffice, enregistrez/fermez le document puis réessayez.',
                ], 422);
            }

            $rawArray = $existingVars->map(fn ($v) => ['key' => $v->key, 'label' => $v->label])->toArray();

            // Enrichir (avec IA ou fallback heuristique)
            if ($available) {
                $enriched = $ollama->enrichVariables($rawArray);
                $source   = 'ollama';
            } else {
                $enriched = $ollama->fallbackEnrich($rawArray);
                $source   = 'fallback';
            }

            // Mettre à jour chaque variable en base
            $validTypes = ['text', 'date', 'number', 'select', 'textarea'];
            foreach ($enriched as $item) {
                $key = $item['key'] ?? null;
                if (!$key) continue;
                $type = in_array($item['field_type'] ?? '', $validTypes, true) ? $item['field_type'] : 'text';
                $template->variables()
                    ->where('key', $key)
                    ->update([
                        'label'       => $item['label']       ?? $key,
                        'field_type'  => $type,
                        'required'    => (bool) ($item['required'] ?? false),
                        'placeholder' => $item['placeholder']  ?? '',
                    ]);
            }

            return response()->json([
                'success'   => true,
                'source'    => $source,
                'available' => $available,
                'message'   => ($available
                    ? '✅ ' . count($enriched) . ' variable(s) enrichie(s) par Ollama (' . $ollama->getModel() . ').'
                    : '⚠️ Ollama indisponible. Enrichissement heuristique appliqué sur ' . count($enriched) . ' variable(s).'),
                'variables' => $enriched,
                'count'     => count($enriched),
            ]);

        } catch (\Throwable $e) {
            Log::error('aiEnrichTemplateVars: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enrichissement IA : ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Re-détection des variables [variable] pour un template existant ─────────────
    public function detectTemplateVars(\App\Models\DocumentTemplate $template)
    {
        $vars = $this->extractVarsFromTemplateText($template->content);

        if ($template->storage_path) {
            $ext = strtolower(pathinfo($template->storage_path, PATHINFO_EXTENSION));
            if (in_array($ext, ['docx', 'xlsx', 'pptx'])) {
                if (str_starts_with($template->storage_path, 'images/')) {
                    $absPath = public_path($template->storage_path);
                } else {
                    $absPath = Storage::disk('public')->path($template->storage_path);
                }

                foreach ($this->extractVarsFromUploadedFile($absPath) as $fileVar) {
                    $vars[$fileVar['key']] = $fileVar;
                }
            }
        }

        $vars = array_values($vars);
        $saved = $this->saveDetectedTemplateVars($template, $vars);

        if (count($vars) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune variable trouvée dans le contenu du modèle ni dans un fichier Office associé. Utilisez des balises [variable] ou {{ variable }} puis enregistrez le modèle.',
                'variables' => [],
                'count' => 0,
            ]);
        }

        return response()->json([
            'success'   => true,
            'message'   => count($vars) . ' variable(s) trouvée(s), ' . $saved . ' nouvelle(s) enregistrée(s).',
            'variables' => $vars,
            'count'     => count($vars),
        ]);
    }

    /**
     * Récupération d'urgence: réextrait les variables d'un fichier déjà stocké
     * (utilisé si le template montre 0 variables mais a un fichier stocké)
     */
    public function recoverTemplateVars(\App\Models\DocumentTemplate $template)
    {
        if (!$template->storage_path) {
            return response()->json([
                'success' => false,
                'message' => 'Ce modèle n\'a pas de fichier stocké. Ouvrez-le dans OnlyOffice et enregistrez-le (Ctrl+S).',
                'count' => 0,
            ]);
        }

        $ext = strtolower(pathinfo($template->storage_path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'xlsx', 'pptx'])) {
            return response()->json([
                'success' => false,
                'message' => 'Ce fichier n\'est pas un format Office reconnu (DOCX/XLSX/PPTX).',
                'count' => 0,
            ]);
        }

        try {
            if (str_starts_with($template->storage_path, 'images/')) {
                $absPath = public_path($template->storage_path);
            } else {
                $absPath = Storage::disk('public')->path($template->storage_path);
            }

            if (!file_exists($absPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier stocké n\'existe pas ou a été supprimé.',
                    'count' => 0,
                ]);
            }

            // Extraire et sauvegarder les variables
            $extractedVars = $this->extractVarsFromUploadedFile($absPath);
            $saved = 0;

            foreach ($extractedVars as $fileVar) {
                $var = $this->firstOrCreateTemplateVariable(
                    $template,
                    (string) ($fileVar['key'] ?? 'var'),
                    (string) ($fileVar['label'] ?? ($fileVar['key'] ?? 'var'))
                );
                if ($var && $var->wasRecentlyCreated) {
                    $saved++;
                }
            }

            $count = count($extractedVars);
            \Log::info('Template recovery: ' . $template->id . ' → ' . $count . ' var(s), ' . $saved . ' new');

            return response()->json([
                'success' => true,
                'message' => $count . ' variable(s) trouvée(s) et récupérée(s) (' . $saved . ' nouvelles).',
                'count' => $count,
                'saved' => $saved,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Template recovery failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération: ' . $e->getMessage(),
                'count' => 0,
            ], 500);
        }
    }

    // ── État de sauvegarde OO d'un template (pour blocage UI à la fermeture) ──
    public function templateSaveState(\App\Models\DocumentTemplate $template)
    {
        $fileExists = false;
        $storagePath = (string) ($template->storage_path ?? '');

        if ($storagePath !== '') {
            if (str_starts_with($storagePath, 'images/')) {
                $fileExists = file_exists(public_path($storagePath));
            } else {
                $fileExists = Storage::disk('public')->exists($storagePath);
            }
        }

        return response()->json([
            'success'         => true,
            'template_id'     => (string) $template->id,
            'has_storage_path'=> $storagePath !== '',
            'file_saved'      => $fileExists,
            'variables_count' => $template->variables()->count(),
            'updated_at'      => (string) $template->updated_at,
        ]);
    }

    // ── Extraction automatique des variables [variable] depuis un fichier Office ────
    private function extractVarsFromUploadedFile(string $absFilePath): array
    {
        if (!class_exists('ZipArchive') || !file_exists($absFilePath)) return [];
        $zip = new \ZipArchive();
        if ($zip->open($absFilePath) !== true) return [];

        $found = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match('/\.xml$/i', $name)) continue;
            if (preg_match('#\[Content_Types\]|_rels/#', $name)) continue;
            $xml = $zip->getFromIndex($i);
            if ($xml === false) continue;
            // Défragmenter les runs Word pour mieux détecter {{ }} saisis dans OO
            $isWordContent = preg_match('#word/(document|header|footer|endnote|footnote)#i', $name);
            $normalizedXml = $isWordContent ? $this->defragmentRuns((string) $xml) : $xml;
            $text = html_entity_decode(strip_tags((string) $normalizedXml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Support des deux syntaxes : {{variable}} (ancien) et [variable] (nouveau)
            preg_match_all('/(?:\{\s*\{)\s*([^{}]+?)\s*(?:\}\s*\})/u', $text, $m1);
            preg_match_all('/\[([^\[\]]+?)\]/u', $text, $m2);
            $allMatches = array_merge($m1[1], $m2[1]);
            foreach ($allMatches as $orig) {
                $orig = trim($orig);
                if (!$orig) continue;
                $slug = $this->makeVariableSlug($orig);
                if (!isset($found[$slug])) $found[$slug] = $orig;
            }
        }
        $zip->close();

        $result = [];
        foreach ($found as $slug => $orig) {
            $result[] = ['key' => $slug, 'label' => $orig];
        }
        return $result;
    }

    /**
     * Regroupe les runs Word d'un paragraphe quand il contient des placeholders.
     * Cela évite de perdre la détection des {{ variables }} fragmentées par OnlyOffice.
     */
    private function defragmentRuns(string $xml): string
    {
        return preg_replace_callback(
            '/<w:p[ >].*?<\/w:p>/s',
            function (array $match) {
                $para = $match[0];

                preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $para, $texts);
                $fullText = implode('', $texts[1]);

                if (!preg_match('/\[[^\[\]]+\]/', $fullText) && strpos($fullText, '{{') === false) {
                    return $para;
                }

                $firstRpr = '';
                if (preg_match('/<w:r[ >].*?(<w:rPr>.*?<\/w:rPr>)/s', $para, $rprMatch)) {
                    $firstRpr = $rprMatch[1];
                }

                $pPr = '';
                if (preg_match('/<w:pPr>.*?<\/w:pPr>/s', $para, $pPrMatch)) {
                    $pPr = $pPrMatch[0];
                }

                $newRun = '<w:r>' . $firstRpr . '<w:t xml:space="preserve">' . $fullText . '</w:t></w:r>';
                return '<w:p>' . $pPr . $newRun . '</w:p>';
            },
            $xml
        ) ?? $xml;
    }

    private function extractVarsFromTemplateText(?string $content): array
    {
        if (!$content) return [];

        $text = trim(strip_tags(html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') return [];

        $found = [];
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/u', $text, $m1);
        preg_match_all('/\[([^\[\]]+?)\]/u', $text, $m2);

        foreach (array_merge($m1[1], $m2[1]) as $orig) {
            $orig = trim($orig);
            if (!$orig) continue;
            $slug  = $this->makeVariableSlug($orig);
            if (!isset($found[$slug])) {
                $found[$slug] = ['key' => $slug, 'label' => $orig];
            }
        }

        return $found;
    }

    private function saveDetectedTemplateVars(DocumentTemplate $template, array $vars): int
    {
        $saved = 0;

        foreach ($vars as $v) {
            $rawKey = (string) ($v['key'] ?? 'var');
            $label = (string) ($v['label'] ?? $rawKey);
            $created = $this->firstOrCreateTemplateVariable(
                $template,
                $rawKey,
                $label
            );
            if ($created && $created->wasRecentlyCreated) {
                $saved++;
            }
        }

        return $saved;
    }

    private function firstOrCreateTemplateVariable(DocumentTemplate $template, string $rawKey, string $rawLabel): ?\App\Models\TemplateVariable
    {
        $key = $this->makeVariableSlug($rawKey);
        $label = function_exists('mb_substr') ? mb_substr($rawLabel, 0, 255) : substr($rawLabel, 0, 255);

        $attributes = ['template_id' => $template->id, 'key' => $key];
        $defaults = [
            'label' => $label,
            'field_type' => 'text',
            'required' => false,
            'placeholder' => '',
            'default_value' => '',
            'options' => json_encode([]),
        ];

        try {
            return \App\Models\TemplateVariable::firstOrCreate($attributes, $defaults);
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            $isTooLong = str_contains($message, 'data too long') && str_contains($message, 'template_variables');
            if (!$isTooLong) {
                \Log::warning('Template variable persistence failed', [
                    'template_id' => $template->id,
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }

            // Compatibilite: si la base est encore en VARCHAR(150), retenter avec une cle reduite.
            $legacyKey = $this->shortenVariableKey($key, 150, $rawKey);
            $attributes['key'] = $legacyKey;

            try {
                return \App\Models\TemplateVariable::firstOrCreate($attributes, $defaults);
            } catch (\Throwable $retryError) {
                \Log::warning('Template variable persistence failed after fallback', [
                    'template_id' => $template->id,
                    'key' => $key,
                    'legacy_key' => $legacyKey,
                    'error' => $retryError->getMessage(),
                ]);
                return null;
            }
        }
    }

    private function shortenVariableKey(string $key, int $max, string $seed = ''): string
    {
        if (strlen($key) <= $max) {
            return $key;
        }

        $hashSource = $seed !== '' ? $seed : $key;
        $hash = substr(sha1($hashSource), 0, 10);
        $base = substr($key, 0, max(1, $max - 11));
        return rtrim($base, '_') . '_' . $hash;
    }

    private function makeVariableSlug(string $orig): string
    {
        $source = $orig;
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $orig);
            if ($ascii !== false && $ascii !== '') {
                $source = $ascii;
            }
        }

        $slug = strtolower($source);
        $slug = str_replace("'", '_', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim((string) $slug, '_');
        if ($slug === '') {
            $slug = 'var';
        }

        // La colonne template_variables.key est VARCHAR(500).
        // On reserve un suffixe hash pour conserver l'unicite en cas de troncature.
        $max = 500;
        if (strlen($slug) > $max) {
            $hash = substr(sha1($orig), 0, 10);
            $base = substr($slug, 0, $max - 11);
            $slug = rtrim($base, '_') . '_' . $hash;
        }

        return $slug;
    }

    // ── Notification création de compte ──────────────────────────────────────

    public function notifyUserAccount(User $user): \Illuminate\Http\RedirectResponse
    {
        $this->guardPermission('administration.users');
        $this->sendAccountCreationEmail($user);
        return redirect()->back()->with('success', "Notification de compte envoyée à {$user->email}.");
    }

    private function sendAccountCreationEmail(User $user): void
    {
        try {
            $appName = config('app.name', 'E-Parapheur');
            $appUrl  = rtrim(config('app.url', url('/')), '/');
            $displayName = trim((string) ($user->full_name ?? $user->name ?? '')) ?: $user->email;

            $body = "Bonjour {$displayName},\n\n"
                . "Votre compte utilisateur sur la plateforme {$appName} a été créé avec succès.\n\n"
                . "═══════════════════════════════\n"
                . "INFORMATIONS DE CONNEXION\n"
                . "═══════════════════════════════\n"
                . "  • Identifiant (email) : {$user->email}\n"
                . "  • Lien d'accès        : {$appUrl}\n"
                . "═══════════════════════════════\n\n"
                . "Pour obtenir votre mot de passe, veuillez contacter l'administrateur de la plateforme.\n\n"
                . "Cordialement,\n"
                . "L'équipe {$appName}";

            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($user, $appName) {
                $message->to($user->email, $user->full_name ?: $user->name)
                        ->subject("Création de votre compte — {$appName}");
            });
        } catch (\Throwable $e) {
            Log::warning('sendAccountCreationEmail failed', [
                'user_id' => (string) $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
