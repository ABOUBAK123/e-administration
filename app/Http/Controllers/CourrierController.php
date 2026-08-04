<?php

namespace App\Http\Controllers;

use App\Models\AdministrationProfile;
use App\Models\AdministrationSmtpSetting;
use App\Models\Courrier;
use App\Models\AppSetting;
use App\Models\Instruction;
use App\Models\IssuingAdministration;
use App\Models\Notification;
use App\Models\RecipientAdministration;
use App\Models\SubEntity;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Services\ClamAvScanner;
use App\Traits\GuardsPermissions;
use Illuminate\Support\Str;

class CourrierController extends Controller
{
    use GuardsPermissions;

    private array $subtabs = [
        'enregistrement'  => ['icon' => 'fas fa-plus-circle',   'label' => 'Enregistrement'],
        'envoi'           => ['icon' => 'fas fa-paper-plane',   'label' => 'Envoi'],
        'reception'       => ['icon' => 'fas fa-inbox',         'label' => 'Réception'],
        'liste'           => ['icon' => 'fas fa-list',           'label' => 'Liste des courriers'],
        'imputation'      => ['icon' => 'fas fa-share',          'label' => 'Imputation'],
        'en-traitement'   => ['icon' => 'fas fa-spinner',        'label' => 'En traitement'],
        'suivi-imputation'=> ['icon' => 'fas fa-binoculars',     'label' => 'Suivi imputation'],
        'traite'          => ['icon' => 'fas fa-check-circle',   'label' => 'Courrier traité'],
        'archives'        => ['icon' => 'fas fa-archive',        'label' => 'Archives'],
    ];

    private const SUBTAB_PERMISSION = [
        'enregistrement'   => 'courrier.enregistrement',
        'envoi'            => 'courrier.liste',
        'reception'        => 'courrier.liste',
        'liste'            => 'courrier.liste',
        'imputation'       => 'courrier.imputation',
        'en-traitement'    => 'courrier.en-traitement',
        'suivi-imputation' => 'courrier.suivi-imputation',
        'traite'           => 'courrier.traite',
        'archives'         => 'courrier.archives',
    ];

    private function canImputerAction(): bool
    {
        return $this->canPermission('courrier.imputation')
            || $this->canPermission('courrier.en-traitement');
    }

    private function visibleSubtabs(): array
    {
        return array_filter($this->subtabs, function ($config, $key) {
            $perm = self::SUBTAB_PERMISSION[$key] ?? null;
            return $perm ? $this->canPermission($perm) : false;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Retourne les rôles/profils considérés comme directeurs
     * (autorisés à voir l'imputation).
     */
    private const ROLES_IMPUTATION = [
        'DIRECTEUR',
        'DIRECTEUR DE CABINET',
        'DIR CAB',
        'DIRECTEUR GÉNÉRAL',
        'DIRECTEUR GENERAL',
        'SOUS-DIRECTEUR',
        'SOUS DIRECTEUR',
    ];

    /**
     * Vérifie si l'utilisateur connecté a un profil directeur.
     */
    private function utilisateurEstDirecteur(): bool
    {
        $user = Auth::user();
        if (!$user || !$user->profile_id) {
            return false;
        }
        $profileName = mb_strtoupper(trim($user->profile->name ?? ''), 'UTF-8');
        return in_array($profileName, self::ROLES_IMPUTATION, true);
    }

    /**
     * Retourne le code direction (sub_entity_code) d'un utilisateur.
     */
    private function directionCodeForUser(?string $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        return UserDirectionAssignment::where('user_id', $userId)
            ->value('sub_entity_code');
    }

    /**
     * Retourne le code d'entité de l'utilisateur connecté sans fallback.
     */
    private function currentUserSubEntityCode(): ?string
    {
        $code = UserDirectionAssignment::where('user_id', Auth::id())
            ->value('sub_entity_code');

        $code = strtoupper(trim((string) $code));
        return $code !== '' ? $code : null;
    }

    /**
     * Scope metier strict pour le courrier: administration + sous-entite.
     * Retourne null si une des deux informations est absente.
     *
     * @return array{administration_id:string, sub_entity_code:string}|null
     */
    private function currentUserCourrierScope(): ?array
    {
        $adminId = $this->administrationId();
        $subEntityCode = $this->currentUserSubEntityCode();

        if (!$adminId || !$subEntityCode) {
            return null;
        }

        // Verrou de coherence: l'entite doit appartenir a l'administration du profil.
        $entityBelongsToAdmin = SubEntity::query()
            ->where('scope_id', $adminId)
            ->whereRaw('UPPER(code) = ?', [$subEntityCode])
            ->exists();

        if (!$entityBelongsToAdmin) {
            return null;
        }

        return [
            'administration_id' => $adminId,
            'sub_entity_code' => $subEntityCode,
        ];
    }

    private function appendWorkflowParticipant(Courrier $courrier, ?string $userId): array
    {
        return $this->appendWorkflowParticipantIds(
            $courrier->workflow_participants,
            array_filter([
                $courrier->impute_par,
                $courrier->traite_par,
                $userId,
            ])
        );
    }

    private function appendWorkflowParticipantIds($existing, array $userIds): array
    {
        return collect(is_array($existing) ? $existing : [])
            ->merge($userIds)
            ->filter(fn($id) => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Entités filles de l'entité du user connecté (même administration).
     */
    private function imputationChildEntities(): \Illuminate\Support\Collection
    {
        $adminId = $this->administrationId();
        $parentCode = $this->currentUserSubEntityCode();

        if (!$adminId || !$parentCode) {
            return collect();
        }

        $base = SubEntity::query()
            ->where('scope_id', $adminId)
            ->where('is_active', true);

        $parent = (clone $base)
            ->whereRaw('UPPER(code) = ?', [$parentCode])
            ->first();

        if ($parent) {
            $base->where('scope_type', $parent->scope_type);
        }

        return $base
            ->whereRaw('UPPER(parent_code) = ?', [$parentCode])
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    private function currentUserSubEntity(): ?SubEntity
    {
        $adminId = $this->administrationId();
        $code = $this->currentUserSubEntityCode();

        if (!$adminId || !$code) {
            return null;
        }

        return SubEntity::query()
            ->where('scope_id', $adminId)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->first();
    }

    private function parentImputeurContext(): ?array
    {
        $currentSubEntity = $this->currentUserSubEntity();
        $parentCode = strtoupper(trim((string) ($currentSubEntity?->parent_code ?? '')));
        $adminId = $this->administrationId();

        if (!$adminId || $parentCode === '') {
            return null;
        }

        $parentSubEntity = SubEntity::query()
            ->where('scope_id', $adminId)
            ->whereRaw('UPPER(code) = ?', [$parentCode])
            ->first();

        if (!$parentSubEntity) {
            return null;
        }

        $candidateUserIds = UserDirectionAssignment::query()
            ->whereRaw('UPPER(sub_entity_code) = ?', [$parentCode])
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($candidateUserIds->isEmpty()) {
            return null;
        }

        $parentUser = User::query()
            ->whereIn('id', $candidateUserIds)
            ->whereHas('profile', function ($q) use ($adminId) {
                $q->where('administration_id', $adminId);
            })
            ->get()
            ->first(function (User $user) {
                $profileName = mb_strtoupper(trim((string) ($user->profile->name ?? '')), 'UTF-8');
                return in_array($profileName, self::ROLES_IMPUTATION, true);
            });

        if (!$parentUser) {
            return null;
        }

        return [
            'user_id' => $parentUser->id,
            'sub_entity_code' => strtoupper(trim((string) $parentSubEntity->code)),
        ];
    }

    private function currentSubEntityResponsibleUser(): ?User
    {
        $adminId = $this->administrationId();
        $subEntityCode = $this->currentUserSubEntityCode();

        if (!$adminId || !$subEntityCode) {
            return null;
        }

        $candidateUserIds = UserDirectionAssignment::query()
            ->whereRaw('UPPER(sub_entity_code) = ?', [$subEntityCode])
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($candidateUserIds->isEmpty()) {
            return null;
        }

        return User::query()
            ->whereIn('id', $candidateUserIds)
            ->whereHas('profile', function ($q) use ($adminId) {
                $q->where('administration_id', $adminId);
            })
            ->get()
            ->first(function (User $user) {
                $profileName = mb_strtoupper(trim((string) ($user->profile->name ?? '')), 'UTF-8');
                return in_array($profileName, self::ROLES_IMPUTATION, true);
            });
    }

    private function responsibleUserBySubEntityCode(?string $subEntityCode): ?User
    {
        $adminId = $this->administrationId();
        $normalizedCode = strtoupper(trim((string) $subEntityCode));

        if (!$adminId || $normalizedCode === '') {
            return null;
        }

        $candidateUserIds = UserDirectionAssignment::query()
            ->whereRaw('UPPER(sub_entity_code) = ?', [$normalizedCode])
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($candidateUserIds->isEmpty()) {
            return null;
        }

        return User::query()
            ->whereIn('id', $candidateUserIds)
            ->whereHas('profile', function ($q) use ($adminId) {
                $q->where('administration_id', $adminId);
            })
            ->get()
            ->first(function (User $user) {
                $profileName = mb_strtoupper(trim((string) ($user->profile->name ?? '')), 'UTF-8');
                return in_array($profileName, self::ROLES_IMPUTATION, true);
            });
    }

    /**
     * Résout la configuration SMTP d'administration à utiliser pour l'envoi
     * des emails de notification courrier, en s'appuyant sur l'utilisateur
     * qui déclenche l'action (agent enregistrant ou imputant le courrier).
     */
    private function resolveCourrierSmtpSetting(User $user): ?AdministrationSmtpSetting
    {
        $assignment = UserDirectionAssignment::where('user_id', $user->id)->first();
        if ($assignment && $assignment->direction_scope_id) {
            $type = $assignment->direction_scope_type === 'recipient' ? 'recipient' : 'emitter';
            return AdministrationSmtpSetting::forAdministration($assignment->direction_scope_id, $type);
        }

        if ($user->profile_id) {
            $profile = AdministrationProfile::find($user->profile_id);
            if ($profile && $profile->administration_id) {
                $type = ($profile->effective_administration_type ?? 'emitter') === 'recipient' ? 'recipient' : 'emitter';
                return AdministrationSmtpSetting::forAdministration($profile->administration_id, $type);
            }
        }

        return null;
    }

    private function applyCourrierScopedSmtpConfiguration(AdministrationSmtpSetting $smtp): void
    {
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
    }

    /**
     * Configure le mailer courant sur la config SMTP de l'administration
     * de l'utilisateur passé en paramètre. Retourne un message d'erreur
     * explicite si aucune configuration n'est trouvée ou incomplète.
     */
    private function configureCourrierMailerFor(User $user): ?string
    {
        $smtp = $this->resolveCourrierSmtpSetting($user);
        if (!$smtp) {
            return "Aucune configuration SMTP d'administration n'a ete trouvee pour l'utilisateur {$user->id}.";
        }

        if (!$smtp->mail_host || !$smtp->mail_from_address) {
            return "La configuration SMTP d'administration est incomplete (hote ou expediteur manquant) pour l'utilisateur {$user->id}.";
        }

        $this->applyCourrierScopedSmtpConfiguration($smtp);
        return null;
    }

    private function sendImputationEmailToTargetResponsible(Courrier $courrier, ?string $targetSubEntityCode): void
    {
        if ($courrier->type !== 'arrive') {
            return;
        }

        $responsibleUser = $this->responsibleUserBySubEntityCode($targetSubEntityCode);
        if (!$responsibleUser) {
            Log::warning('Responsable entite cible introuvable pour email d\'imputation.', [
                'courrier_id' => (string) $courrier->id,
                'numero' => $courrier->numero,
                'target_sub_entity_code' => strtoupper(trim((string) $targetSubEntityCode)),
                'administration_id' => $courrier->administration_id,
            ]);
            return;
        }

        Notification::create([
            'recipient_id' => $responsibleUser->id,
            'title' => 'Courrier impute pour traitement',
            'message' => 'Le courrier n° ' . $courrier->numero . ' vous a ete impute pour traitement.',
            'type' => 'info',
            'action_url' => route('courrier.en-traitement'),
            'is_read' => false,
        ]);

        $recipientEmail = trim((string) $responsibleUser->email);
        if ($recipientEmail === '') {
            return;
        }

        $currentUser = Auth::user();
        $smtpError = $currentUser ? $this->configureCourrierMailerFor($currentUser) : "Utilisateur non authentifie.";
        if ($smtpError !== null) {
            Log::error('Echec envoi email d\'imputation a l\'entite cible: configuration SMTP indisponible.', [
                'courrier_id' => (string) $courrier->id,
                'recipient_id' => (string) $responsibleUser->id,
                'recipient_email' => $recipientEmail,
                'target_sub_entity_code' => strtoupper(trim((string) $targetSubEntityCode)),
                'error' => $smtpError,
            ]);
            return;
        }

        try {
            Mail::raw(
                "Bonjour {$responsibleUser->name},\n\n" .
                "Un courrier vous a ete impute pour traitement.\n" .
                "Numero : {$courrier->numero}\n" .
                "Objet : {$courrier->objet}\n\n" .
                "Connectez-vous a l'application pour le traiter : " . route('courrier.en-traitement') . "\n",
                function ($message) use ($recipientEmail, $courrier) {
                    $message->to($recipientEmail)
                        ->subject('Courrier impute pour traitement: ' . $courrier->numero);
                }
            );
        } catch (\Throwable $e) {
            Log::error('Echec envoi email d\'imputation a l\'entite cible.', [
                'courrier_id' => (string) $courrier->id,
                'recipient_id' => (string) $responsibleUser->id,
                'recipient_email' => $recipientEmail,
                'target_sub_entity_code' => strtoupper(trim((string) $targetSubEntityCode)),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyResponsibleForPendingImputation(Courrier $courrier): void
    {
        if ($courrier->type !== 'arrive') {
            return;
        }

        $responsibleUser = $this->currentSubEntityResponsibleUser();
        if (!$responsibleUser) {
            Log::warning('Responsable sous-entite introuvable pour notification courrier en attente d\'imputation.', [
                'courrier_id' => (string) $courrier->id,
                'numero' => $courrier->numero,
                'sub_entity_code' => $courrier->sub_entity_code,
                'administration_id' => $courrier->administration_id,
            ]);
            return;
        }

        Notification::create([
            'recipient_id' => $responsibleUser->id,
            'title' => 'Courrier en attente d\'imputation',
            'message' => 'Le courrier n° ' . $courrier->numero . ' est en attente d\'imputation.',
            'type' => 'info',
            'action_url' => route('courrier.imputation'),
            'is_read' => false,
        ]);

        $recipientEmail = trim((string) $responsibleUser->email);
        if ($recipientEmail === '') {
            return;
        }

        $currentUser = Auth::user();
        $smtpError = $currentUser ? $this->configureCourrierMailerFor($currentUser) : "Utilisateur non authentifie.";
        if ($smtpError !== null) {
            Log::error('Echec envoi email courrier en attente d\'imputation: configuration SMTP indisponible.', [
                'courrier_id' => (string) $courrier->id,
                'recipient_id' => (string) $responsibleUser->id,
                'recipient_email' => $recipientEmail,
                'error' => $smtpError,
            ]);
            return;
        }

        try {
            Mail::raw(
                "Bonjour {$responsibleUser->name},\n\n" .
                "Un courrier arrive est en attente d'imputation.\n" .
                "Numero : {$courrier->numero}\n" .
                "Objet : {$courrier->objet}\n\n" .
                "Connectez-vous a l'application pour l'imputer : " . route('courrier.imputation') . "\n",
                function ($message) use ($recipientEmail, $courrier) {
                    $message->to($recipientEmail)
                        ->subject('Courrier en attente d\'imputation: ' . $courrier->numero);
                }
            );
        } catch (\Throwable $e) {
            Log::error('Echec envoi email courrier en attente d\'imputation.', [
                'courrier_id' => (string) $courrier->id,
                'recipient_id' => (string) $responsibleUser->id,
                'recipient_email' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Renvoie l'UUID de l'administration de l'utilisateur connecté.
     */
    private function administrationId(): ?string
    {
        $user = Auth::user();
        if (!$user || !$user->profile_id) {
            return null;
        }
        return $user->profile->administration_id ?? null;
    }

    /**
     * Retourne la date de seuil d'archivage (les courriers créés avant cette date sont archivés).
     * Retourne null si l'archivage n'est pas configuré.
     */
    private function archivalScopeKeySuffix(): ?string
    {
        $user = Auth::user();
        if (!$user || !$user->profile_id) {
            return null;
        }

        $profile = $user->profile;
        if (!$profile || !$profile->administration_id) {
            return null;
        }

        $type = ($profile->effective_administration_type ?? $profile->administration_type ?? 'emitter') === 'recipient'
            ? 'recipient'
            : 'emitter';

        return $type . ':' . $profile->administration_id;
    }

    private function archivalDays(string $baseKey): int
    {
        $scopeSuffix = $this->archivalScopeKeySuffix();

        if ($scopeSuffix) {
            $scopedSetting = AppSetting::where('key', $baseKey . ':' . $scopeSuffix)->first();
            if ($scopedSetting) {
                return max(0, (int) $scopedSetting->value);
            }
        }

        return max(0, (int) AppSetting::where('key', $baseKey)->value('value'));
    }

    private function archivalThreshold(): ?\Carbon\Carbon
    {
        $days = $this->archivalDays('courrier_archival_days');
        if ($days <= 0) {
            return null;
        }
        return now()->subDays($days);
    }

    /**
     * Retourne la config du lecteur (viewer) depuis AppSetting.
     * ['viewer' => 'onlyoffice'|'native', 'oo_url' => '...', 'oo_secret' => '...']
     */
    private function viewerConfig(): array
    {
        $settings = AppSetting::whereIn('key', [
            'onlyoffice_doc_viewer',
            'onlyoffice_server_url',
            'onlyoffice_secret',
        ])->pluck('value', 'key');

        return [
            'viewer'    => $settings['onlyoffice_doc_viewer'] ?? 'native',
            'oo_url'    => rtrim($settings['onlyoffice_server_url'] ?? '', '/'),
            'oo_secret' => $settings['onlyoffice_secret'] ?? '',
        ];
    }

    /**
     * Résout le chemin storage en URL publique.
     */
    private function resolveFileUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        $clean = ltrim($path, '/');
        if (str_starts_with($clean, 'storage/')) {
            return asset($clean);
        }
        return Storage::disk('public')->exists($clean) ? asset('storage/' . $clean) : null;
    }

    /**
     * Retourne le prochain numéro de courrier.
     * Retourne le code de la sous-entité (direction) de l'utilisateur connecté.
     * Cherche dans UserDirectionAssignment, fallback sur 'GEN'.
     */
    private function subEntityCode(): string
    {
        $assignment = UserDirectionAssignment::where('user_id', Auth::id())->first();
        if ($assignment && $assignment->sub_entity_code) {
            return strtoupper(trim($assignment->sub_entity_code));
        }

        // Fallback : chercher une sub_entity rattachée à l'administration du profil de l'utilisateur
        $user = Auth::user();
        if ($user && $user->profile_id) {
            $adminId = $user->profile->administration_id ?? null;
            if ($adminId) {
                $rootEntity = \App\Models\SubEntity::where('scope_id', $adminId)
                    ->where('is_active', true)
                    ->whereNull('parent_code')
                    ->first();
                if ($rootEntity && $rootEntity->code) {
                    return strtoupper(trim($rootEntity->code));
                }
            }
        }

        return 'GEN'; // code générique si aucune entité assignée
    }

    /**
     * Génère le prochain numéro de courrier.
     * Format : {A|D}-{CODE_ENTITE}-{00001}-{ANNEE}
     * Exemple : A-DMOA-00001-2026
     * La séquence est par type + code entité + année (00001 → 99999).
     */
    private function prochainNumero(string $type, ?string $forcedCode = null): string
    {
        $prefix = $type === 'arrive' ? 'A' : 'D';
        $code   = strtoupper(trim((string) ($forcedCode ?? $this->subEntityCode())));
        if ($code === '') {
            $code = 'GEN';
        }
        $annee  = date('Y');

        // Compter les courriers existants pour ce type, ce code et cette année
        $count = Courrier::where('sub_entity_code', $code)
            ->where('type', $type)
            ->whereYear('created_at', $annee)
            ->count();

        $seq = str_pad($count + 1, 5, '0', STR_PAD_LEFT);
        return $prefix . '-' . $code . '-' . $seq . '-' . $annee;
    }

    public function enregistrement(Request $request)
    {
        $this->guardPermission('courrier.enregistrement');
        $typeForm = $request->get('type_courrier', 'arrive');
        $scope = $this->currentUserCourrierScope();
        $scopeCode = $scope['sub_entity_code'] ?? null;
        $recipientAdministrations = RecipientAdministration::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('courrier.index', [
            'subtab'         => 'enregistrement',
            'subtabs'        => $this->visibleSubtabs(),
            'prochainNumero' => $this->prochainNumero($typeForm, $scopeCode),
            'recipientAdministrations' => $recipientAdministrations,
        ]);
    }

    public function envoi(Request $request)
    {
        $this->guardPermission('courrier.liste');

        $search = trim((string) $request->get('q', ''));
        $adminId = $this->administrationId();
        $subEntityCode = $this->subEntityCode();

        $query = Courrier::query()
            ->with(['enregistrePar', 'destinataireAdministration', 'emetteurAdministration'])
            ->where('type', 'depart')
            ->when($adminId, fn ($q) => $q->where('administration_id', $adminId))
            ->when($subEntityCode, fn ($q) => $q->whereRaw('UPPER(sub_entity_code) = ?', [strtoupper((string) $subEntityCode)]))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('numero', 'like', '%' . $search . '%')
                        ->orWhere('objet', 'like', '%' . $search . '%')
                        ->orWhere('destinataire', 'like', '%' . $search . '%');
                });
            })
            ->latest();

        $courriers = $query->get()->map(function (Courrier $c) {
            $firstFile = is_array($c->pieces_jointes) && count($c->pieces_jointes)
                ? $c->pieces_jointes[0]
                : ($c->accuse_reception ?? null);

            return [
                'id' => (string) $c->id,
                'num' => (string) $c->numero,
                'objet' => (string) $c->objet,
                'priorite' => (string) $c->priorite_libelle,
                'statut' => (string) $c->statut_libelle,
                'agent' => (string) ($c->enregistrePar?->name ?? '—'),
                'destinataire' => (string) ($c->destinataire ?? ''),
                'destinataire_admin' => (string) ($c->destinataireAdministration?->name ?? '—'),
                'fichier' => $this->resolveFileUrl($firstFile),
                'date_emission' => optional($c->date_emission)->format('Y-m-d') ?: '',
                'numero_emission' => (string) ($c->numero_emission ?? ''),
                'destinataire_administration_id' => (string) ($c->destinataire_administration_id ?? ''),
            ];
        })->values()->toArray();

        return view('courrier.index', [
            'subtab' => 'envoi',
            'subtabs' => $this->visibleSubtabs(),
            'courriersEnvoi' => $courriers,
            'search' => $search,
        ]);
    }

    public function reception(Request $request)
    {
        $this->guardPermission('courrier.liste');

        $search = trim((string) $request->get('q', ''));
        $adminId = $this->administrationId();

        $query = Courrier::query()
            ->with(['enregistrePar', 'destinataireAdministration', 'emetteurAdministration'])
            ->where('type', 'depart')
            ->when($adminId, fn ($q) => $q->where('destinataire_administration_id', $adminId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('numero', 'like', '%' . $search . '%')
                        ->orWhere('objet', 'like', '%' . $search . '%')
                        ->orWhere('expediteur', 'like', '%' . $search . '%');
                });
            })
            ->latest();

        $courriers = $query->get()->map(function (Courrier $c) {
            $firstFile = is_array($c->pieces_jointes) && count($c->pieces_jointes)
                ? $c->pieces_jointes[0]
                : ($c->accuse_reception ?? null);

            return [
                'id' => (string) $c->id,
                'num' => (string) $c->numero,
                'objet' => (string) $c->objet,
                'priorite' => (string) $c->priorite_libelle,
                'statut' => (string) $c->statut_libelle,
                'agent' => (string) ($c->enregistrePar?->name ?? '—'),
                'expediteur' => (string) ($c->expediteur ?? ''),
                'emetteur_admin' => (string) ($c->emetteurAdministration?->name ?? '—'),
                'fichier' => $this->resolveFileUrl($firstFile),
                'date_emission' => optional($c->date_emission)->format('Y-m-d') ?: '',
                'numero_emission' => (string) ($c->numero_emission ?? ''),
                'destinataire_administration_id' => (string) ($c->destinataire_administration_id ?? ''),
            ];
        })->values()->toArray();

        return view('courrier.index', [
            'subtab' => 'reception',
            'subtabs' => $this->visibleSubtabs(),
            'courriersReception' => $courriers,
            'search' => $search,
        ]);
    }

    public function liste(Request $request)
    {
        $this->guardPermission('courrier.liste');
        $filtre  = $request->get('filtre', 'tous');
        $statut  = $request->get('statut', '');
        $search  = trim($request->get('q', ''));

        // Filtrer par la même sous-entité que l'utilisateur connecté
        $code    = $this->subEntityCode();
        $adminId = $this->administrationId();

        // Correspondance statut French label → enum DB
        $statutMap = [
            'En attente'    => 'en_attente',
            'En traitement' => 'en_traitement',
            'Traité'        => 'traite',
        ];

        $archivalThreshold = $this->archivalThreshold();
        $query = Courrier::with(['enregistrePar', 'destinataireAdministration'])
            ->where('sub_entity_code', $code)
            ->when($adminId, fn($q) => $q->where('administration_id', $adminId))
            ->when($filtre === 'arrive',  fn($q) => $q->where('type', 'arrive'))
            ->when($filtre === 'depart',  fn($q) => $q->where('type', 'depart'))
            ->when($statut !== '' && isset($statutMap[$statut]),
                fn($q) => $q->where('statut', $statutMap[$statut]))
            ->when($search !== '', fn($q) => $q->where(function ($q) use ($search) {
                $q->where('objet',    'like', '%'.$search.'%')
                  ->orWhere('numero', 'like', '%'.$search.'%')
                  ->orWhere('expediteur', 'like', '%'.$search.'%');
            }))
            ->when($archivalThreshold !== null, fn($q) => $q->where('created_at', '>=', $archivalThreshold))
            ->latest();

        $results = $query->get();

        // Transformer en tableaux pour compatibilité avec la vue existante
        $typeLabel    = ['arrive' => 'Arrivé', 'depart' => 'Départ'];
        $urgenceLabel = ['normale' => 'Normale', 'urgent' => 'Urgent', 'tres_urgent' => 'Très urgent'];
        $statutLabel  = ['en_attente' => 'En attente', 'en_traitement' => 'En traitement', 'traite' => 'Traité'];

        $courriers = $results->map(fn($c) => [
            'id'         => (string) $c->id,
            'num'        => $c->numero,
            'objet'      => $c->objet,
            'type'       => $typeLabel[$c->type] ?? $c->type,
            'priorite'   => $urgenceLabel[$c->urgence] ?? $c->urgence,
            'statut'     => $statutLabel[$c->statut] ?? $c->statut,
            'agent'      => $c->enregistrePar?->name ?? '—',
            'expediteur' => $c->expediteur ?? '',
            'destinataire' => $c->destinataire ?? '',
            'date_emission' => optional($c->date_emission)->format('Y-m-d') ?? '',
            'numero_emission' => (string) ($c->numero_emission ?? ''),
            'destinataire_administration_id' => (string) ($c->destinataire_administration_id ?? ''),
            // URL du premier fichier disponible (pour le viewer)
            'fichier'    => $this->resolveFileUrl(
                            is_array($c->pieces_jointes) && count($c->pieces_jointes)
                                ? $c->pieces_jointes[0]
                                : ($c->accuse_reception ?? null)
                        ),
        ])->values()->toArray();

        $viewer = $this->viewerConfig();

        return view('courrier.index', [
            'subtab'       => 'liste',
            'subtabs'      => $this->visibleSubtabs(),
            'courriers'    => $courriers,
            'filtre'       => $filtre,
            'statut'       => $statut,
            'search'       => $search,
            'ooViewer'     => $viewer['viewer'],
            'ooUrl'        => $viewer['oo_url'],
            'ooSecret'     => $viewer['oo_secret'],
        ]);
    }

    public function imputation(Request $request)
    {
        $this->guardPermission('courrier.imputation');
        $viewer = $this->viewerConfig();
        $imputationEntities = $this->imputationChildEntities();
        $scope = $this->currentUserCourrierScope();

        if (!$scope) {
            return view('courrier.index', [
                'subtab'         => 'imputation',
                'subtabs'        => $this->visibleSubtabs(),
                'accesDenied'    => true,
                'courriers'      => collect(),
                'imputFiltre'    => 'tous',
                'imputSearch'    => '',
                'ooViewer'       => $viewer['viewer'],
                'ooUrl'          => $viewer['oo_url'],
            ]);
        }

        // Seuls les directeurs peuvent accéder à l'imputation
        if (!$this->utilisateurEstDirecteur()) {
            return view('courrier.index', [
                'subtab'         => 'imputation',
                'subtabs'        => $this->visibleSubtabs(),
                'accesDenied'    => true,
                'courriers'      => collect(),
                'imputFiltre'    => 'tous',
                'imputSearch'    => '',
                'ooViewer'       => $viewer['viewer'],
                'ooUrl'          => $viewer['oo_url'],
            ]);
        }

        $adminId        = $scope['administration_id'];
        $subEntityCode  = $scope['sub_entity_code']; // entité du directeur connecté
        $imputFiltre    = $request->get('imp_filtre', 'tous');
        $imputSearch    = trim($request->get('imp_q', ''));

        $archivalThreshold = $this->archivalThreshold();
        $query = Courrier::with(['enregistrePar'])
            ->where('type', 'arrive')
            ->where('statut', 'en_attente')
            ->where('administration_id', $adminId)
            // Seuls les courriers de la meme sous-entite que le directeur connecte
            ->whereRaw('UPPER(sub_entity_code) = ?', [$subEntityCode])
            ->when($imputFiltre === 'urgent', fn($q) => $q->whereIn('urgence', ['urgent', 'tres_urgent']))
            ->when($imputSearch !== '', fn($q) => $q->where(function ($q) use ($imputSearch) {
                $q->where('objet', 'like', '%' . $imputSearch . '%')
                  ->orWhere('numero', 'like', '%' . $imputSearch . '%')
                  ->orWhere('expediteur', 'like', '%' . $imputSearch . '%');
            }))
            ->when($archivalThreshold !== null, fn($q) => $q->where('created_at', '>=', $archivalThreshold))
            ->latest();

        $courriers   = $query->get();
        $instructions = Instruction::where('actif', true)->latest()->get();

        return view('courrier.index', [
            'subtab'       => 'imputation',
            'subtabs'      => $this->visibleSubtabs(),
            'accesDenied'  => false,
            'courriers'    => $courriers,
            'imputationEntities' => $imputationEntities,
            'imputFiltre'  => $imputFiltre,
            'imputSearch'  => $imputSearch,
            'instructions' => $instructions,
            'ooViewer'     => $viewer['viewer'],
            'ooUrl'        => $viewer['oo_url'],
        ]);
    }

    public function enTraitement(Request $request)
    {
        $this->guardPermission('courrier.en-traitement');
        $viewer = $this->viewerConfig();
        $imputationEntities = $this->imputationChildEntities();
        $code = $this->subEntityCode();
        $search = trim($request->get('et_q', ''));

        $archivalThreshold = $this->archivalThreshold();
        $courriers = Courrier::with(['imputePar'])
            ->where('type', 'arrive')
            ->where('statut', 'en_traitement')
            ->where('impute_a', $code)
            ->when($search !== '', fn($q) => $q->where(function ($qq) use ($search) {
                $qq->where('numero', 'like', '%' . $search . '%')
                    ->orWhere('objet', 'like', '%' . $search . '%')
                    ->orWhere('expediteur', 'like', '%' . $search . '%');
            }))
            ->when($archivalThreshold !== null, fn($q) => $q->where('created_at', '>=', $archivalThreshold))
            ->latest('impute_le')
            ->get();

        $imputerCodes = UserDirectionAssignment::whereIn('user_id', $courriers->pluck('impute_par')->filter()->unique())
            ->pluck('sub_entity_code', 'user_id');

        $enTraitementRows = $courriers->map(function ($c) use ($imputerCodes) {
            $firstFile = is_array($c->pieces_jointes) && count($c->pieces_jointes)
                ? $c->pieces_jointes[0]
                : ($c->accuse_reception ?? null);

            return [
                'id' => $c->id,
                'num' => $c->numero,
                'objet' => $c->objet,
                'expediteur' => $c->expediteur,
                'delai' => $c->delai_traitement?->format('d/m/Y') ?? '—',
                'imputer_par_code' => strtoupper($imputerCodes[$c->impute_par] ?? '—'),
                'fichier' => $this->resolveFileUrl($firstFile),
            ];
        });

        return view('courrier.index', [
            'subtab' => 'en-traitement',
            'subtabs' => $this->visibleSubtabs(),
            'enTraitementRows' => $enTraitementRows,
            'imputationEntities' => $imputationEntities,
            'canReimputer' => $this->utilisateurEstDirecteur(),
            'etSearch' => $search,
            'ooViewer' => $viewer['viewer'],
            'ooUrl' => $viewer['oo_url'],
        ]);
    }

    public function suiviImputation(Request $request)
    {
        $this->guardPermission('courrier.suivi-imputation');
        $viewer = $this->viewerConfig();
        $userId = Auth::id();
        $search = trim((string) $request->get('q', ''));
        $sort = $request->get('tri_date', 'recent');

        $archivalThreshold = $this->archivalThreshold();
        $rows = Courrier::with(['imputePar'])
            ->where('type', 'arrive')
                        ->where('impute_par', $userId)
            ->where(function ($q) {
                $q->where('statut', 'en_traitement')
                  ->orWhere(function ($qq) {
                      $qq->where('statut', 'traite')
                         ->where('reponse_statut', 'en_attente_validation');
                  });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('numero', 'like', '%' . $search . '%')
                        ->orWhere('objet', 'like', '%' . $search . '%');
                });
            })
            ->when($archivalThreshold !== null, fn($q) => $q->where('created_at', '>=', $archivalThreshold))
            ->orderBy('impute_le', $sort === 'ancien' ? 'asc' : 'desc')
            ->get();

        $suiviRows = $rows->map(function ($c) use ($userId) {
            $today = now()->startOfDay();
            $deadline = $c->delai_traitement?->copy()?->startOfDay();
            $daysLeft = $deadline ? $today->diffInDays($deadline, false) : null;


            $suiviStatut = match (true) {
                $c->statut === 'en_traitement' => 'En cours de traitement',
                $c->reponse_statut === 'validee' => 'Validé',
                $c->reponse_statut === 'rejetee' => 'Rejeté',
                default => 'En attente de validation',
            };

            return [
                'id' => $c->id,
                'num' => $c->numero,
                'objet' => $c->objet,
                'instruction' => $c->instruction_nom ?: '—',
                'date_concernee' => ($c->traite_le ?? $c->impute_le)?->format('d/m/Y H:i') ?: '—',
                'delai_traitement' => $c->delai_traitement?->format('d/m/Y') ?: '—',
                'delai_days_left' => $daysLeft,
                'delai_alert_one_day' => $daysLeft === 1,
                'impute_a' => strtoupper((string) ($c->impute_a ?? '—')),
                'fichier_reponse' => $this->resolveFileUrl($c->fichier_reponse),
                'reponse_nom' => $c->reponse_nom,
                'suivi_statut' => $suiviStatut,
                'can_validate' => $c->statut === 'traite'
                    && $c->reponse_statut === 'en_attente_validation'
                    && !empty($c->fichier_reponse),
                'reponse_statut' => $c->reponse_statut,
            ];
        });

        return view('courrier.index', [
            'subtab' => 'suivi-imputation',
            'subtabs' => $this->visibleSubtabs(),
            'suiviRows' => $suiviRows,
            'suiviSearch' => $search,
            'suiviSort' => $sort,
            'ooViewer' => $viewer['viewer'],
            'ooUrl' => $viewer['oo_url'],
        ]);
    }

    public function traite(Request $request)
    {
        $this->guardPermission('courrier.traite');
        $viewer = $this->viewerConfig();
        $userId = Auth::id();
        $statusFilter = trim((string) $request->get('statut_filtre', ''));
        $search = trim((string) $request->get('q', ''));
        $sort = $request->get('tri_date', 'recent');

        $statusMap = [
            'En attente validation' => 'en_attente_validation',
            'Validé' => 'validee',
            'Rejeté' => 'rejetee',
        ];

        $archivalThreshold = $this->archivalThreshold();
        $rows = Courrier::with(['traitePar'])
            ->where('type', 'arrive')
            ->whereNotNull('traite_le')
            ->where(function ($q) use ($userId) {
                $q->where('impute_par', $userId)
                  ->orWhere('traite_par', $userId)
                  ->orWhereJsonContains('workflow_participants', $userId);
            })
            ->when($statusFilter !== '' && isset($statusMap[$statusFilter]), function ($q) use ($statusMap, $statusFilter) {
                $q->where('reponse_statut', $statusMap[$statusFilter]);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('numero', 'like', '%' . $search . '%')
                        ->orWhere('objet', 'like', '%' . $search . '%');
                });
            })
            ->when($archivalThreshold !== null, fn($q) => $q->where('created_at', '>=', $archivalThreshold))
            ->orderBy('traite_le', $sort === 'ancien' ? 'asc' : 'desc')
            ->get();

        $traiteRows = $rows->map(function ($c) {
            $courrierFile = is_array($c->pieces_jointes) && count($c->pieces_jointes)
                ? $c->pieces_jointes[0]
                : ($c->accuse_reception ?? null);

            $status = match (true) {
                $c->statut === 'en_traitement' => 'En cours de traitement',
                $c->reponse_statut === 'validee' => 'Validé',
                $c->reponse_statut === 'rejetee' => 'Rejeté',
                $c->reponse_statut === 'en_attente_validation' && $c->traite_par === Auth::id() && $c->impute_par !== Auth::id() => 'Validé',
                default => 'En attente validation',
            };

            return [
                'id' => $c->id,
                'num' => $c->numero,
                'objet' => $c->objet,
                'traitement' => $c->reponse_nom ?: 'Réponse de traitement',
                'statut' => $status,
                'date_concernee' => $c->traite_le?->format('d/m/Y H:i') ?: '—',
                'fichier_reponse' => $this->resolveFileUrl($c->fichier_reponse),
                'fichier_courrier' => $this->resolveFileUrl($courrierFile),
            ];
        });

        return view('courrier.index', [
            'subtab' => 'traite',
            'subtabs' => $this->visibleSubtabs(),
            'traiteRows' => $traiteRows,
            'traiteStatusFilter' => $statusFilter,
            'traiteSearch' => $search,
            'traiteSort' => $sort,
            'ooViewer' => $viewer['viewer'],
            'ooUrl' => $viewer['oo_url'],
        ]);
    }

    /**
     * Affiche les courriers archivés (créés avant le seuil d'archivage).
     * Seuls les courriers "âgés" selon le délai configuré sont listés ici.
     */
    public function archives(Request $request)
    {
        $this->guardPermission('courrier.archives');
        $threshold = $this->archivalThreshold();
        $viewer    = $this->viewerConfig();
        $search    = trim($request->get('q', ''));
        $filtre    = $request->get('filtre', 'tous');
        $sort      = $request->get('tri', 'recent');
        $code      = $this->subEntityCode();
        $adminId   = $this->administrationId();

        // Si l'archivage n'est pas configuré, aucun courrier archivé
        $query = Courrier::with('enregistrePar')
            ->when($adminId, fn($q) => $q->where('administration_id', $adminId))
            ->when($code, fn($q) => $q->where('sub_entity_code', $code))
            ->when($filtre === 'arrive', fn($q) => $q->where('type', 'arrive'))
            ->when($filtre === 'depart', fn($q) => $q->where('type', 'depart'))
            ->when($search !== '', fn($q) => $q->where(function ($qq) use ($search) {
                $qq->where('objet', 'like', '%' . $search . '%')
                   ->orWhere('numero', 'like', '%' . $search . '%')
                   ->orWhere('expediteur', 'like', '%' . $search . '%');
            }));

        if ($threshold !== null) {
            $query->where('created_at', '<', $threshold);
        } else {
            // Archivage désactivé : liste vide
            $query->whereRaw('1 = 0');
        }

        $results = $query->orderBy('created_at', $sort === 'ancien' ? 'asc' : 'desc')->get();

        $typeLabel    = ['arrive' => 'Arrivé', 'depart' => 'Départ'];
        $urgenceLabel = ['normale' => 'Normale', 'urgent' => 'Urgent', 'tres_urgent' => 'Très urgent'];
        $statutLabel  = ['en_attente' => 'En attente', 'en_traitement' => 'En traitement', 'traite' => 'Traité'];

        $courriers = $results->map(fn($c) => [
            'num'        => $c->numero,
            'objet'      => $c->objet,
            'type'       => $typeLabel[$c->type] ?? $c->type,
            'priorite'   => $urgenceLabel[$c->urgence] ?? $c->urgence,
            'statut'     => $statutLabel[$c->statut] ?? $c->statut,
            'agent'      => $c->enregistrePar?->name ?? '—',
            'expediteur' => $c->expediteur ?? '',
            'date'       => $c->created_at?->format('d/m/Y') ?? '—',
            'fichier'    => $this->resolveFileUrl(
                                is_array($c->pieces_jointes) && count($c->pieces_jointes)
                                    ? $c->pieces_jointes[0]
                                    : ($c->accuse_reception ?? null)
                            ),
        ])->values()->toArray();

        $archivalDays = $this->archivalDays('courrier_archival_days');

        return view('courrier.index', [
            'subtab'       => 'archives',
            'subtabs'      => $this->visibleSubtabs(),
            'courriers'    => $courriers,
            'filtre'       => $filtre,
            'search'       => $search,
            'sort'         => $sort,
            'archivalDays' => $archivalDays,
            'ooViewer'     => $viewer['viewer'],
            'ooUrl'        => $viewer['oo_url'],
        ]);
    }

    /**
     * Affiche un document dans le viewer configuré (OnlyOffice ou natif).
     * Appelé via /courrier/visualiser?file=URL&title=...
     */
    public function visualiser(Request $request)
    {
        $this->guardPermission('courrier');
        $fileUrl = $request->query('file', '');
        $title   = $request->query('title', 'Document');
        $viewer  = $this->viewerConfig();

        // Valider que l'URL pointe bien vers notre stockage
        $allowed = [asset('storage/'), url('/storage/')];
        $isLocal = collect($allowed)->contains(fn($prefix) => str_starts_with($fileUrl, $prefix));
        if (!$fileUrl || !$isLocal) {
            abort(403, 'Accès refusé.');
        }

        // Générer le token JWT OnlyOffice côté serveur si secret configuré
        $ooJwt = null;
        if ($viewer['viewer'] === 'onlyoffice' && $viewer['oo_url'] && $viewer['oo_secret']) {
            $ext     = strtolower(pathinfo($fileUrl, PATHINFO_EXTENSION));
            $docKey  = 'courrier_' . crc32($fileUrl) . '_' . date('Ymd');
            $payload = [
                'document' => [
                    'fileType'    => $ext,
                    'key'         => $docKey,
                    'title'       => $title,
                    'url'         => $fileUrl,
                    'permissions' => ['edit' => false, 'download' => true, 'print' => true],
                ],
                'documentType' => 'word',
                'editorConfig' => ['mode' => 'view', 'lang' => 'fr'],
            ];
            $header  = rtrim(strtr(base64_encode(json_encode(['alg'=>'HS256','typ'=>'JWT'])), '+/', '-_'), '=');
            $body    = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
            $sig     = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", $viewer['oo_secret'], true)), '+/', '-_'), '=');
            $ooJwt   = "$header.$body.$sig";
        }

        return view('courrier.visualiser', [
            'fileUrl'   => $fileUrl,
            'title'     => $title,
            'ooViewer'  => $viewer['viewer'],
            'ooUrl'     => $viewer['oo_url'],
            'ooSecret'  => $viewer['oo_secret'],
            'ooJwt'     => $ooJwt,
        ]);
    }

    /**
     * Enregistre l'imputation d'un courrier arrivé.
     * Accessible uniquement aux directeurs.
     */
    public function imputer(Request $request)
    {
        abort_if(!$this->canImputerAction(), 403, 'Accès refusé.');
        if (!$this->utilisateurEstDirecteur()) {
            abort(403, 'Accès refusé.');
        }

        $request->validate([
            'courrier_id'     => 'required|uuid|exists:courriers,id',
            'delai_traitement'=> 'required|date',
            'entites'         => 'required|array|min:1',
            'entites.*.entite' => 'required|string|max:50',
            'entites.*.instruction' => 'required',
        ]);

        $courrier = Courrier::where('id', $request->input('courrier_id'))
            ->where('administration_id', $this->administrationId())
            ->where('type', 'arrive')
            ->whereIn('statut', ['en_attente', 'en_traitement'])
            ->firstOrFail();

        $previousStatus = $courrier->statut;

        // Récupérer la première entité + instruction sélectionnées
        $entites = $request->input('entites', []);
        $allowedCodes = $this->imputationChildEntities()
            ->pluck('code')
            ->map(fn($c) => strtoupper(trim((string) $c)))
            ->filter()
            ->values()
            ->all();

        $imputeA = null;
        $instrNom = null;
        $instrDesc = null;

        if (empty($allowedCodes)) {
            return back()
                ->withErrors(['entites' => 'Aucune entité fille autorisée pour votre direction.'])
                ->withInput();
        }

        $first = null;
        foreach ($entites as $item) {
            $candidate = strtoupper(trim((string) ($item['entite'] ?? '')));
            if ($candidate !== '' && in_array($candidate, $allowedCodes, true)) {
                $first = $item;
                $first['entite'] = $candidate;
                break;
            }
        }

        if (!$first) {
            return back()
                ->withErrors(['entites' => 'Vous devez sélectionner une entité fille autorisée.'])
                ->withInput();
        }

        $imputeA = $first['entite'];

        // Lors de la réimputation (en_traitement → en_traitement), le réimputeur
        // devient le nouveau propriétaire du suivi pour recevoir la réponse de
        // l'entité fille. L'ancien impute_par est capturé dans workflow_participants
        // (via appendWorkflowParticipant ci-dessous) pour que sa visibilité
        // dans suivi-imputation soit préservée.
        $suiviOwnerId = Auth::id();

        $instrId = $first['instruction'] ?? null;
        if ($instrId) {
            $instruction = Instruction::where('actif', true)->find($instrId);
            if ($instruction) {
                $instrNom  = $instruction->nom;
                $instrDesc = $instruction->description;
            }
        }

        $workflowParticipants = $this->appendWorkflowParticipant($courrier, Auth::id());

        $courrier->update([
            'statut'           => 'en_traitement',
            'impute_a'         => $imputeA,
            'impute_par'       => $suiviOwnerId,
            'impute_le'        => now(),
            'instruction_nom'  => $instrNom,
            'instruction_desc' => $instrDesc,
            'delai_traitement' => $request->input('delai_traitement'),
            'reponse_statut'   => null,
            'workflow_participants' => $workflowParticipants,
        ]);

        $this->sendImputationEmailToTargetResponsible($courrier, $imputeA);

        $redirectRoute = $previousStatus === 'en_traitement'
            ? 'courrier.en-traitement'
            : ($this->canPermission('courrier.imputation') ? 'courrier.imputation' : 'courrier.en-traitement');

        return redirect()->route($redirectRoute)
            ->with('success', 'Courrier n° ' . $courrier->numero . ' imputé avec succès.');
    }

    /**
     * Soumettre le traitement d'un courrier imputé.
     */
    public function traiter(Request $request)
    {
        $this->guardPermission('courrier.en-traitement');
        $validated = $request->validate([
            'courrier_id' => 'required|uuid|exists:courriers,id',
            'reponse_nom' => 'required|string|max:255',
            'fichier_reponse' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpeg,jpg,png|max:20480',
        ]);

        $courrier = Courrier::where('id', $validated['courrier_id'])
            ->where('type', 'arrive')
            ->where('statut', 'en_traitement')
            ->firstOrFail();

        // Autoriser le traitement seulement à la direction imputée
        abort_if(strtoupper((string) $courrier->impute_a) !== strtoupper($this->subEntityCode()), 403);

        ClamAvScanner::scan($request->file('fichier_reponse'), 'courriers');
        $path = $request->file('fichier_reponse')->store('courriers/reponses', 'public');

        $workflowParticipants = $this->appendWorkflowParticipant($courrier, Auth::id());

        $courrier->update([
            'reponse_nom' => $validated['reponse_nom'],
            'fichier_reponse' => $path,
            'reponse_statut' => 'en_attente_validation',
            'workflow_participants' => $workflowParticipants,
            'traite_par' => Auth::id(),
            'traite_le' => now(),
            'statut' => 'traite',
        ]);

        return redirect()->route('courrier.en-traitement')
            ->with('success', 'Courrier n° ' . $courrier->numero . ' traité et envoyé pour validation.');
    }

    /**
     * Valider la réponse d'un courrier traité (par l'utilisateur imputeur).
     */
    public function validerTraitement(Courrier $courrier)
    {
        $this->guardPermission('courrier.suivi-imputation');
        abort_if($courrier->impute_par !== Auth::id(), 403);
        abort_if($courrier->statut !== 'traite', 422);

        $workflowParticipants = $this->appendWorkflowParticipant($courrier, Auth::id());
        $parentContext = $this->parentImputeurContext();

        if ($parentContext) {
            $courrier->update([
                'impute_par' => $parentContext['user_id'],
                'impute_a' => $this->currentUserSubEntityCode(),
                'impute_le' => now(),
                'reponse_statut' => 'en_attente_validation',
                'workflow_participants' => $this->appendWorkflowParticipantIds($workflowParticipants, [$parentContext['user_id']]),
            ]);

            return redirect()->route('courrier.suivi-imputation')
                ->with('success', 'Réponse validée et transmise au niveau parent pour validation.');
        }

        $courrier->update([
            'reponse_statut' => 'validee',
            'workflow_participants' => $workflowParticipants,
        ]);

        return redirect()->route('courrier.suivi-imputation')
            ->with('success', 'Réponse validée pour le courrier n° ' . $courrier->numero . '.');
    }

    /**
     * Validation locale (OK) : retire du suivi de l'utilisateur courant
     * et affiche le courrier en statut validé dans son sous-onglet traité.
     */
    public function okTraitement(Courrier $courrier)
    {
        $this->guardPermission('courrier.suivi-imputation');
        abort_if($courrier->impute_par !== Auth::id(), 403);
        abort_if($courrier->statut !== 'traite', 422);

        $courrier->update([
            'reponse_statut' => 'validee',
            'workflow_participants' => $this->appendWorkflowParticipant($courrier, Auth::id()),
        ]);

        return redirect()->route('courrier.suivi-imputation')
            ->with('success', 'Traitement validé (OK) pour le courrier n° ' . $courrier->numero . '.');
    }

    /**
     * Rejeter la réponse d'un courrier traité et le remettre en traitement.
     */
    public function rejeterTraitement(Courrier $courrier)
    {
        $this->guardPermission('courrier.suivi-imputation');
        abort_if($courrier->impute_par !== Auth::id(), 403);
        abort_if($courrier->statut !== 'traite', 422);

        $courrier->update([
            'reponse_statut' => 'rejetee',
            'statut' => 'en_traitement',
            'workflow_participants' => $this->appendWorkflowParticipant($courrier, Auth::id()),
        ]);

        return redirect()->route('courrier.suivi-imputation')
            ->with('success', 'Réponse rejetée. Le courrier n° ' . $courrier->numero . ' est renvoyé en traitement.');
    }

    public function scanOcr(Request $request)
    {
        $this->guardPermission('courrier.enregistrement');

        $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,tiff,bmp',
        ], [
            'file.required' => 'Veuillez sélectionner un fichier à analyser.',
            'file.mimes'    => 'Formats acceptés : PDF, JPG, PNG.',
            'file.max'      => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

        $fields = (new \App\Services\CourrierOcrService())->extractFields($request->file('file'));

        if (isset($fields['error'])) {
            return response()->json(['ok' => false, 'message' => $fields['error']], 422);
        }

        return response()->json(['ok' => true, 'fields' => $fields]);
    }

    public function store(Request $request)
    {
        $this->guardPermission('courrier.enregistrement');
        $type = $request->input('type_courrier', 'arrive');
        $scope = $this->currentUserCourrierScope();

        if (!$scope) {
            return back()->withErrors([
                'scope' => 'Votre compte doit etre rattache a une administration et une entite sous tutelle pour enregistrer un courrier.',
            ])->withInput();
        }

        $validated = $request->validate([
            'type_courrier'  => 'required|in:arrive,depart',
            'objet'          => 'required|string|max:500',
            'urgence'        => 'required|in:normale,urgent,tres_urgent',
            'date_emission'  => 'required|date',
            'expediteur'     => 'nullable|string',
            'destinataire'   => 'nullable|string',
            'destinataire_administration_id' => 'required_if:type_courrier,depart|nullable|uuid|exists:recipient_administrations,id',
            'numero_emission'=> 'nullable|string|max:100',
            'observations'   => 'nullable|string',
            'pieces_jointes' => 'nullable|array',
            'pieces_jointes.*'=> 'nullable|file|max:10240',
            'accuse_reception'=> 'nullable|file|max:10240',
        ]);

        $adminId = $scope['administration_id'];
        $subEntityCode = $scope['sub_entity_code'];
        $numero  = $this->prochainNumero($type, $subEntityCode);

        // Gestion fichiers joints
        $pjPaths = [];
        if ($request->hasFile('pieces_jointes')) {
            foreach ($request->file('pieces_jointes') as $file) {
                ClamAvScanner::scan($file, 'courriers');
                $pjPaths[] = $file->store('courriers/pieces_jointes', 'public');
            }
        }

        $accusePath = null;
        if ($request->hasFile('accuse_reception')) {
            ClamAvScanner::scan($request->file('accuse_reception'), 'courriers');
            $accusePath = $request->file('accuse_reception')->store('courriers/accuses', 'public');
        }

        $courrier = Courrier::create([
            'id'               => (string) Str::uuid(),
            'numero'           => $numero,
            'type'             => $type,
            'objet'            => $validated['objet'],
            'urgence'          => $validated['urgence'],
            'date_emission'    => $validated['date_emission'],
            'expediteur'       => $validated['expediteur'] ?? null,
            'destinataire'     => $validated['destinataire'] ?? null,
            'numero_emission'  => $validated['numero_emission'] ?? null,
            'observations'     => $validated['observations'] ?? null,
            'statut'           => 'en_attente',
            'enregistre_par'   => Auth::id(),
            'administration_id'=> $adminId,
            'emetteur_administration_id' => $adminId,
            'destinataire_administration_id' => $type === 'depart'
                ? ($validated['destinataire_administration_id'] ?? null)
                : null,
            'sub_entity_code'  => $subEntityCode,
            'pieces_jointes'   => $pjPaths ?: null,
            'accuse_reception' => $accusePath,
        ]);

        $this->notifyResponsibleForPendingImputation($courrier);

        return redirect()->route('courrier.enregistrement', ['type_courrier' => $type])
            ->with('success', 'Courrier n° ' . $numero . ' enregistré avec succès.');
    }

    public function update(Request $request, Courrier $courrier)
    {
        $this->guardPermission('courrier.liste');

        $adminId = $this->administrationId();
        abort_if(!$adminId || (string) $courrier->administration_id !== (string) $adminId, 403);

        $validated = $request->validate([
            'objet' => 'required|string|max:500',
            'urgence' => 'required|in:normale,urgent,tres_urgent',
            'date_emission' => 'nullable|date',
            'numero_emission' => 'nullable|string|max:100',
            'expediteur' => 'nullable|string',
            'destinataire' => 'nullable|string',
            'destinataire_administration_id' => 'nullable|uuid|exists:recipient_administrations,id',
            'accuse_reception' => 'nullable|file|max:10240',
            'fichier' => 'nullable|file|max:10240',
        ]);

        $updates = [
            'objet' => $validated['objet'],
            'urgence' => $validated['urgence'],
            'date_emission' => $validated['date_emission'] ?? $courrier->date_emission,
            'numero_emission' => $validated['numero_emission'] ?? null,
            'expediteur' => $validated['expediteur'] ?? null,
            'destinataire' => $validated['destinataire'] ?? null,
        ];

        if ($courrier->type === 'depart') {
            $updates['destinataire_administration_id'] = $validated['destinataire_administration_id'] ?? $courrier->destinataire_administration_id;
        }

        if ($request->hasFile('accuse_reception')) {
            ClamAvScanner::scan($request->file('accuse_reception'), 'courriers');
            $updates['accuse_reception'] = $request->file('accuse_reception')->store('courriers/accuses', 'public');
        }

        if ($request->hasFile('fichier')) {
            ClamAvScanner::scan($request->file('fichier'), 'courriers');
            $path = $request->file('fichier')->store('courriers/pieces_jointes', 'public');
            $currentAttachments = is_array($courrier->pieces_jointes) ? $courrier->pieces_jointes : [];
            array_unshift($currentAttachments, $path);
            $updates['pieces_jointes'] = array_values(array_unique($currentAttachments));
        }

        $courrier->update($updates);

        return back()->with('success', 'Courrier n° ' . $courrier->numero . ' mis à jour avec succès.');
    }
}
