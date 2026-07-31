<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\ActRequestSubmission;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Notification;
use App\Models\RecipientAdministration;
use App\Models\SubEntity;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ReceptionController extends Controller
{
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

    public function index(Request $request)
    {
        $search = $request->get('q', '');
        $subtab = in_array($request->get('subtab', 'inbox'), ['inbox', 'archives'], true)
            ? $request->get('subtab', 'inbox')
            : 'inbox';
        $receptionArchivalDays = $this->archivalDays('reception_archival_days');
        $receptionArchivalThreshold = $receptionArchivalDays > 0 ? now()->subDays($receptionArchivalDays) : null;
        $user = Auth::user();
        $userId = $user?->id;
        $userEmail = $user?->email;

        $subEntityCodes = collect();
        if (Schema::hasTable('user_direction_assignments')) {
            try {
                $subEntityCodes = UserDirectionAssignment::query()
                    ->where('user_id', $userId)
                    ->whereNotNull('sub_entity_code')
                    ->pluck('sub_entity_code')
                    ->map(fn ($code) => strtoupper(trim((string) $code)))
                    ->filter()
                    ->values();
            } catch (\Throwable $e) {
                Log::warning('Reception fallback: cannot query user_direction_assignments', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $recipientAdminIds = collect();
        $profile = $user?->profile;

        // Si l'utilisateur appartient directement à une administration destinataire,
        // son profil a administration_type='recipient' et administration_id = id de la RecipientAdministration
        if ($profile && $profile->administration_type === 'recipient' && $profile->administration_id) {
            $recipientAdminIds = collect([$profile->administration_id]);
        } elseif (Schema::hasTable('recipient_administrations')) {
            // Fallback : chercher par le code de l'administration émettrice (cas emetteur)
            $adminCode = strtoupper(trim((string) ($profile?->administration?->code ?? '')));
            if ($adminCode !== '') {
                try {
                    $recipientAdminIds = RecipientAdministration::query()
                        ->whereRaw('UPPER(code) = ?', [$adminCode])
                        ->pluck('id');
                } catch (\Throwable $e) {
                    Log::warning('Reception fallback: cannot query recipient_administrations', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $sharedDocIds = collect();

        if (!Schema::hasTable('document_shares')) {
            Log::warning('Reception fallback: missing document_shares table');
        } elseif (!Schema::hasColumns('document_shares', ['document_id', 'recipient_name'])) {
            Log::warning('Reception fallback: document_shares table exists but missing required columns');
        } else {
            try {
                $sharesQuery = DocumentShare::query()
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    })
                    ->where(function ($q) use ($userId, $userEmail, $subEntityCodes, $recipientAdminIds) {
                        $q->where('recipient_name', 'user:' . $userId);

                        if (!empty($userEmail)) {
                            $q->orWhere('recipient_email', $userEmail);
                        }

                        foreach ($subEntityCodes as $code) {
                            $q->orWhere('recipient_name', 'sub_entity:' . $code);
                        }

                        if ($recipientAdminIds->isNotEmpty()) {
                            $q->orWhereIn('recipient_administration_id', $recipientAdminIds);
                        }
                    });

                if ($subtab === 'archives') {
                    if ($receptionArchivalThreshold !== null) {
                        $sharesQuery->where('created_at', '<', $receptionArchivalThreshold);
                    } else {
                        $sharesQuery->whereRaw('1 = 0');
                    }
                } elseif ($receptionArchivalThreshold !== null) {
                    $sharesQuery->where('created_at', '>=', $receptionArchivalThreshold);
                }

                $sharedDocIds = $sharesQuery
                    ->pluck('document_id')
                    ->unique()
                    ->values();
            } catch (\Throwable $e) {
                Log::warning('Reception fallback: cannot query document shares', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if (!Schema::hasTable('documents')) {
            Log::warning('Reception fallback: missing documents table');
            $documents = new LengthAwarePaginator([], 0, 20, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            return view('reception.index', compact('documents', 'search', 'subtab', 'receptionArchivalDays'));
        }

        $query = Document::with(['owner', 'issuingAdministration'])
            ->whereIn('id', $sharedDocIds);

        if (Schema::hasColumn('documents', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($search) {
            $query->where('title', 'LIKE', "%{$search}%");
        }

        $documents = $query->latest()->paginate(20);

        // Récupérer les infos du demandeur pour chaque document (le share le plus récent par document)
        $sharesInfo = collect();
        if ($sharedDocIds->isNotEmpty()) {
            try {
                $sharesInfo = DocumentShare::query()
                    ->whereIn('document_id', $sharedDocIds)
                    ->whereNotNull('applicant_full_name')
                    ->orderByDesc('created_at')
                    ->get(['document_id', 'applicant_full_name', 'applicant_phone', 'applicant_email', 'applicant_rib', 'tracking_number'])
                    ->keyBy('document_id');
            } catch (\Throwable $e) {
                Log::warning('Reception: cannot load applicant info', ['message' => $e->getMessage()]);
            }
        }

        // Récupérer les entités sous tutelle filles de la direction de l'utilisateur connecté
        $subEntities = collect();
        if ($profile && $profile->administration_type === 'recipient' && $profile->administration_id) {
            try {
                // Direction de l'utilisateur connecté
                $userSubEntityCode = UserDirectionAssignment::where('user_id', $userId)
                    ->whereNotNull('sub_entity_code')
                    ->value('sub_entity_code');

                if ($userSubEntityCode) {
                    // Entités filles : parent_code = code de la direction de l'utilisateur
                    $subEntities = SubEntity::where('scope_type', 'recipient')
                        ->where('scope_id', $profile->administration_id)
                        ->whereRaw('UPPER(parent_code) = ?', [strtoupper(trim($userSubEntityCode))])
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get(['id', 'code', 'name']);
                }
                // Si l'utilisateur n'a pas de direction assignée : $subEntities reste vide
                // → le bouton "Transmettre" n'apparaîtra pas dans la vue
            } catch (\Throwable $e) {
                Log::warning('Reception: cannot load sub_entities', ['message' => $e->getMessage()]);
            }
        }

        // Récupérer le reception_status par document pour l'utilisateur connecté
        $receptionStatuses = collect();
        $transmissionInfo = collect();
        if ($sharedDocIds->isNotEmpty() && Schema::hasColumn('document_shares', 'reception_status')) {
            try {
                $receptionStatuses = DocumentShare::query()
                    ->whereIn('document_id', $sharedDocIds)
                    ->where(function ($q) use ($userId, $userEmail, $subEntityCodes, $recipientAdminIds) {
                        $q->where('recipient_name', 'user:' . $userId);
                        if (!empty($userEmail)) {
                            $q->orWhere('recipient_email', $userEmail);
                        }
                        foreach ($subEntityCodes as $code) {
                            $q->orWhere('recipient_name', 'sub_entity:' . $code);
                        }
                        if ($recipientAdminIds->isNotEmpty()) {
                            $q->orWhereIn('recipient_administration_id', $recipientAdminIds);
                        }
                    })
                    ->whereNotNull('reception_status')
                    ->orderByRaw("CASE reception_status WHEN 'transmis' THEN 1 WHEN 'recu' THEN 2 ELSE 3 END")
                    ->get(['document_id', 'reception_status', 'recipient_name'])
                    ->keyBy('document_id');

                // Récupérer les noms des entités transmises (pour le tooltip)
                if (Schema::hasTable('sub_entities')) {
                    try {
                        $transmissionInfo = DocumentShare::query()
                            ->whereIn('document_id', $sharedDocIds)
                            ->where('reception_status', 'transmis')
                            ->where(function ($q) use ($userId, $userEmail, $subEntityCodes, $recipientAdminIds) {
                                $q->where('recipient_name', 'user:' . $userId);
                                if (!empty($userEmail)) {
                                    $q->orWhere('recipient_email', $userEmail);
                                }
                                foreach ($subEntityCodes as $code) {
                                    $q->orWhere('recipient_name', 'sub_entity:' . $code);
                                }
                                if ($recipientAdminIds->isNotEmpty()) {
                                    $q->orWhereIn('recipient_administration_id', $recipientAdminIds);
                                }
                            })
                            ->get(['document_id', 'recipient_name'])
                            ->map(function ($share) {
                                // Extraire le code de la sub_entity du recipient_name (format: sub_entity:CODE)
                                if (str_starts_with($share->recipient_name, 'sub_entity:')) {
                                    $code = substr($share->recipient_name, 11); // Enlever 'sub_entity:' prefix
                                    $subEntity = SubEntity::where('code', $code)->first();
                                    return $subEntity?->name ?? $code;
                                }
                                return null;
                            })
                            ->keyBy('document_id');
                    } catch (\Throwable $e) {
                        Log::warning('Reception: cannot load transmission info', ['message' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Reception: cannot load reception_status', ['message' => $e->getMessage()]);
            }
        }

        return view('reception.index', compact(
            'documents',
            'search',
            'sharesInfo',
            'subEntities',
            'receptionStatuses',
            'transmissionInfo',
            'subtab',
            'receptionArchivalDays'
        ));
    }

    /**
     * Marque le document comme reçu (téléchargé) pour l'utilisateur connecté.
     */
    public function markReceived(Request $request, Document $document)
    {
        $user      = Auth::user();
        $userId    = $user?->id;
        $profile   = $user?->profile;
        $userEmail = (string) ($user?->email ?? '');

        // Codes de direction de l'utilisateur (mêmes conditions que index())
        $subEntityCodes = collect();
        if (Schema::hasTable('user_direction_assignments')) {
            try {
                $subEntityCodes = UserDirectionAssignment::where('user_id', $userId)
                    ->whereNotNull('sub_entity_code')
                    ->pluck('sub_entity_code')
                    ->map(fn ($c) => strtoupper(trim((string) $c)))
                    ->filter()
                    ->values();
            } catch (\Throwable $e) { /* ignore */ }
        }

        $recipientAdminIds = collect();
        if ($profile && $profile->administration_type === 'recipient' && $profile->administration_id) {
            $recipientAdminIds = collect([$profile->administration_id]);
        }

        // Trouver le share avec les mêmes conditions que index()
        $share = DocumentShare::where('document_id', $document->id)
            ->where(function ($q) use ($userId, $userEmail, $subEntityCodes, $recipientAdminIds) {
                $q->where('recipient_name', 'user:' . $userId);
                if ($userEmail !== '') {
                    $q->orWhere('recipient_email', $userEmail);
                }
                foreach ($subEntityCodes as $code) {
                    $q->orWhere('recipient_name', 'sub_entity:' . $code);
                }
                if ($recipientAdminIds->isNotEmpty()) {
                    $q->orWhereIn('recipient_administration_id', $recipientAdminIds);
                }
            })
            // Priorité : share personnel > sub_entity > administration
            ->orderByRaw("CASE
                WHEN recipient_name = ? THEN 0
                WHEN recipient_name LIKE 'sub_entity:%' THEN 1
                ELSE 2 END", ['user:' . $userId])
            ->first();

        if (!$share) {
            return response()->json(['ok' => false, 'reception_status' => null]);
        }

        if (in_array($share->reception_status, [null, ''], true)) {
            $share->reception_status = 'recu';
            $share->save();
        }

        return response()->json(['ok' => true, 'reception_status' => $share->reception_status]);
    }

    public function forward(Request $request, Document $document)
    {
        $user = Auth::user();
        $profile = $user?->profile;

        if (!$profile || $profile->administration_type !== 'recipient' || !$profile->administration_id) {
            return response()->json(['ok' => false, 'message' => 'Accès non autorisé.'], 403);
        }

        $recipientAdminId = $profile->administration_id;

        // Vérifier que l'utilisateur a bien accès à ce document via un share
        $hasAccess = DocumentShare::where('document_id', $document->id)
            ->where(function ($q) use ($user, $recipientAdminId) {
                $q->where('recipient_name', 'user:' . $user->id)
                  ->orWhere('recipient_email', $user->email)
                  ->orWhere('recipient_administration_id', $recipientAdminId);
            })
            ->exists();

        if (!$hasAccess) {
            return response()->json(['ok' => false, 'message' => 'Vous n\'avez pas accès à ce document.'], 403);
        }

        $request->validate([
            'sub_entity_code' => 'required|string|max:60',
        ]);

        $subEntityCode = strtoupper(trim((string) $request->input('sub_entity_code')));

        // Vérifier que l'entité cible est une fille de la direction de l'utilisateur
        $userSubEntityCode = UserDirectionAssignment::where('user_id', $user->id)
            ->whereNotNull('sub_entity_code')
            ->value('sub_entity_code');

        $subEntityQuery = SubEntity::where('scope_type', 'recipient')
            ->where('scope_id', $recipientAdminId)
            ->whereRaw('UPPER(code) = ?', [$subEntityCode])
            ->where('is_active', true);

        if ($userSubEntityCode) {
            $subEntityQuery->whereRaw('UPPER(parent_code) = ?', [strtoupper(trim($userSubEntityCode))]);
        }

        $subEntity = $subEntityQuery->first();

        if (!$subEntity) {
            return response()->json(['ok' => false, 'message' => 'Entité sous tutelle introuvable ou non autorisée.'], 422);
        }

        // Trouver les users actifs de cette entité, dans la même administration destinataire
        $targetUserIds = UserDirectionAssignment::whereRaw('UPPER(sub_entity_code) = ?', [$subEntityCode])
            ->pluck('user_id')
            ->unique();

        $targetUsers = User::whereIn('id', $targetUserIds)
            ->where('status', 'active')
            ->where('id', '!=', $user->id)
            ->whereHas('profile', function ($q) use ($recipientAdminId) {
                $q->where('administration_id', $recipientAdminId)
                  ->where('administration_type', 'recipient');
            })
            ->get(['id', 'email']);

        if ($targetUsers->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Aucun utilisateur actif trouvé pour cette entité sous tutelle.'], 422);
        }

        $created = 0;
        foreach ($targetUsers as $targetUser) {
            // Éviter les doublons
            $alreadyShared = DocumentShare::where('document_id', $document->id)
                ->where('recipient_name', 'user:' . $targetUser->id)
                ->exists();

            if (!$alreadyShared) {
                DocumentShare::create([
                    'document_id'   => $document->id,
                    'shared_by'     => $user->id,
                    'mode'          => 'internal',
                    'permission'    => 'lecture',
                    'has_delay'     => false,
                    'recipient_name'  => 'sub_entity:' . $subEntityCode,
                    'recipient_email' => $targetUser->email,
                ]);

                Notification::create([
                    'recipient_id' => $targetUser->id,
                    'title'        => 'Document transmis',
                    'message'      => 'Le document "' . $document->title . '" vous a été transmis par votre administration.',
                    'type'         => 'info',
                    'action_url'   => route('reception.index'),
                    'is_read'      => false,
                ]);

                $created++;
            }
        }

        // Mettre à jour le reception_status à 'transmis' pour le share de l'utilisateur
        // (mêmes conditions que index() et markReceived())
        if (Schema::hasColumn('document_shares', 'reception_status')) {
            $userEmail     = (string) ($user->email ?? '');
            $userSubCodes  = UserDirectionAssignment::where('user_id', $user->id)
                ->whereNotNull('sub_entity_code')
                ->pluck('sub_entity_code')
                ->map(fn ($c) => strtoupper(trim((string) $c)))
                ->filter()->values();

            $userShare = DocumentShare::where('document_id', $document->id)
                ->where(function ($q) use ($user, $userEmail, $userSubCodes, $recipientAdminId) {
                    $q->where('recipient_name', 'user:' . $user->id);
                    if ($userEmail !== '') {
                        $q->orWhere('recipient_email', $userEmail);
                    }
                    foreach ($userSubCodes as $code) {
                        $q->orWhere('recipient_name', 'sub_entity:' . $code);
                    }
                    if ($recipientAdminId) {
                        $q->orWhere('recipient_administration_id', $recipientAdminId);
                    }
                })
                ->orderByRaw("CASE
                    WHEN recipient_name = ? THEN 0
                    WHEN recipient_name LIKE 'sub_entity:%' THEN 1
                    ELSE 2 END", ['user:' . $user->id])
                ->first();

            if ($userShare) {
                $userShare->reception_status = 'transmis';
                $userShare->save();

                $trackingNumber = strtoupper(trim((string) ($userShare->tracking_number ?? '')));
                if ($trackingNumber === '') {
                    $trackingNumber = strtoupper(trim((string) (DocumentShare::query()
                        ->where('document_id', $document->id)
                        ->where('recipient_administration_id', $recipientAdminId)
                        ->whereNotNull('tracking_number')
                        ->latest()
                        ->value('tracking_number') ?? '')));
                }
                if ($trackingNumber !== '') {
                    $submission = ActRequestSubmission::query()
                        ->whereRaw('UPPER(tracking_number) = ?', [$trackingNumber])
                        ->first();

                    if ($submission && $submission->status === 'sent') {
                        $submission->status = 'recu';
                        $submission->save();
                    }
                }
            }
        }

        return response()->json([
            'ok'      => true,
            'message' => "Document transmis à {$created} utilisateur(s) de l'entité \"{$subEntity->name}\".",
        ]);
    }
}
