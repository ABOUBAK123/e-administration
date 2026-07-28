<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\DocumentVersion;
use App\Models\DocumentUserPreference;
use App\Models\ActRequestSubmission;
use App\Models\Notification;
use App\Models\RecipientAdministration;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use App\Models\AppSetting;
use App\Services\ClamAvScanner;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Traits\GuardsPermissions;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    use GuardsPermissions;

    public function index(Request $request)
    {
        $this->guardPermission('documents.view');
        $user = Auth::user();
        $userId = (string) ($user?->id ?? '');
        $userEmail = (string) ($user?->email ?? '');

        $subEntityCodes = UserDirectionAssignment::query()
            ->where('user_id', $userId)
            ->whereNotNull('sub_entity_code')
            ->pluck('sub_entity_code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->values();

        $profile = $user?->profile;
        $recipientAdminIds = collect();
        if ($profile && $profile->administration_type === 'recipient' && $profile->administration_id) {
            $recipientAdminIds = collect([$profile->administration_id]);
        } else {
            $adminCode = strtoupper(trim((string) ($profile?->administration?->code ?? '')));
            if ($adminCode !== '') {
                $recipientAdminIds = RecipientAdministration::query()
                    ->whereRaw('UPPER(code) = ?', [$adminCode])
                    ->pluck('id');
            }
        }

        $sharedDocumentIds = DocumentShare::query()
            ->where('mode', 'internal')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
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
            ->pluck('document_id')
            ->unique()
            ->values();

        $documents = Document::query()
            ->where('owner_id', $userId)
            ->orWhere('created_by', $userId)
            ->orWhereIn('id', $sharedDocumentIds)
            ->latest()
            ->get();

        $documentAccessPermissions = [];
        foreach ($documents as $document) {
            if ($this->userCanManageDocument($document, $userId)) {
                $documentAccessPermissions[(string) $document->id] = 'modification';
            }
        }

        if ($sharedDocumentIds->isNotEmpty()) {
            $shareRows = DocumentShare::query()
                ->whereIn('document_id', $sharedDocumentIds)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
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
                ->get(['document_id', 'permission']);

            foreach ($shareRows->groupBy('document_id') as $documentId => $rows) {
                $hasModification = $rows->contains(fn ($row) => (string) $row->permission === 'modification');
                $documentAccessPermissions[(string) $documentId] = $hasModification ? 'modification' : 'lecture';
            }
        }

        // Préférences (favoris + étiquettes) de l'utilisateur connecté
        $preferences = DocumentUserPreference::where('user_id', Auth::id())
            ->get()
            ->keyBy('document_id');

        // Nombre de partages par document
        $sharesCount = DocumentShare::whereIn('document_id', $documents->pluck('id'))
            ->selectRaw('document_id, count(*) as cnt')
            ->groupBy('document_id')
            ->pluck('cnt', 'document_id');

        // Administrations destinataires actives pour le modal de partage
        $recipientAdministrations = RecipientAdministration::where('is_active', true)->get();

        $currentAdminId = Auth::user()?->profile?->administration_id;

        $internalUsersQuery = User::query()
            ->where('status', 'active')
            ->where('id', '!=', Auth::id())
            ->with(['directionAssignments' => function ($q) {
                $q->select('id', 'user_id', 'sub_entity_code', 'direction_label');
            }]);

        if ($currentAdminId) {
            $internalUsersQuery->whereHas('profile', function ($q) use ($currentAdminId) {
                $q->where('administration_id', $currentAdminId);
            });
        }

        $internalUsers = $internalUsersQuery->get(['id', 'name', 'full_name', 'email', 'profile_id']);

        $internalSubEntities = UserDirectionAssignment::query()
            ->whereIn('user_id', $internalUsers->pluck('id'))
            ->whereNotNull('sub_entity_code')
            ->select('user_id', 'sub_entity_code', 'direction_label')
            ->get()
            ->groupBy(function ($row) {
                return strtoupper(trim((string) $row->sub_entity_code));
            })
            ->filter(function ($rows, $code) {
                return $code !== '';
            })
            ->map(function ($rows, $code) {
                $label = trim((string) ($rows->first()->direction_label ?? ''));
                return [
                    'code' => $code,
                    'label' => $label !== '' ? $label : $code,
                    'users_count' => $rows->pluck('user_id')->unique()->count(),
                ];
            })
            ->values();

        $onlyofficeUrl = AppSetting::where('key', 'onlyoffice_server_url')->value('value') ?: '';

        return view('documents.index', compact(
            'documents',
            'preferences',
            'sharesCount',
            'recipientAdministrations',
            'internalUsers',
            'internalSubEntities',
            'onlyofficeUrl',
            'documentAccessPermissions'
        ));
    }

    public function create()
    {
        $this->guardPermission('documents.upload');
        return view('documents.create');
    }

    /**
     * Upload AJAX depuis le modal workflow (retourne JSON)
     */
    public function uploadAjax(Request $request)
    {
        // Toute accès au module documents suffit pour uploader ses propres fichiers
        $this->guardPermission('documents');
        $request->validate([
            // zip est ajouté car Windows/WAMP détecte docx/xlsx/pptx comme application/zip
            'file'   => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,csv,zip|max:51200',
            'title'  => 'nullable|string|max:500',
            'folder' => 'nullable|string|max:500',
        ]);

        $file   = $request->file('file');
        ClamAvScanner::scan($file, 'documents');
        $title  = $request->title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $path   = $file->store('documents', 'public');
        $folder = $request->input('folder');

        $document = Document::create([
            'id'          => Str::uuid(),
            'title'       => $title,
            'description' => $folder ? "Dossier: {$folder}" : null,
            'file_path'   => '/storage/' . $path,
            'final_file_path' => '/storage/' . $path,
            'file_size'   => $file->getSize(),
            'mime_type'   => $file->getMimeType(),
            'status'      => 'draft',
            'owner_id'    => Auth::id(),
            'created_by'  => Auth::id(),
        ]);

        DocumentVersion::create([
            'id'          => Str::uuid(),
            'document_id' => $document->id,
            'version'     => 1,
            'file_path'   => $document->file_path,
            'creator_id'  => Auth::id(),
            'change_log'  => 'Version initiale',
        ]);

        return response()->json([
            'id'        => $document->id,
            'title'     => $document->title,
            'file_path' => $document->file_path,
            'mime_type' => $document->mime_type,
        ], 201);
    }

    public function store(Request $request)
    {
        $this->guardPermission('documents.upload');
        $request->validate([
            'title'  => 'required|string|max:500',
            'file'   => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,csv,zip|max:51200',
            'folder' => 'nullable|string|max:500',
        ]);

        $file     = $request->file('file');
        ClamAvScanner::scan($file, 'documents');
        $path     = $file->store('documents', 'public');
        $folder   = $request->input('folder');
        $document = Document::create([
            'title'       => $request->title,
            'description' => $folder ? "Dossier: {$folder}" : $request->input('description'),
            'file_path'   => '/storage/' . $path,
            'final_file_path' => '/storage/' . $path,
            'file_size'   => $file->getSize(),
            'mime_type'   => $file->getMimeType(),
            'status'      => 'draft',
            'owner_id'    => Auth::id(),
            'created_by'  => Auth::id(),
        ]);

        DocumentVersion::create([
            'document_id' => $document->id,
            'version'     => 1,
            'file_path'   => $document->file_path,
            'creator_id'  => Auth::id(),
            'change_log'  => 'Version initiale',
        ]);

        return redirect()->route('documents.index')->with('success', 'Document créé avec succès.');
    }

    public function show(Document $document)
    {
        $this->guardPermission('documents.view');
        abort_if(Auth::id() !== $document->owner_id, 403);
        $document->load(['versions', 'signatures.signer', 'qrCodes']);
        return view('documents.show', compact('document'));
    }

    public function edit(Document $document)
    {
        $this->guardPermission('documents.view');
        abort_if(Auth::id() !== $document->owner_id, 403);
        return view('documents.edit', compact('document'));
    }

    public function update(Request $request, Document $document)
    {
        $this->guardPermission('documents.view');
        abort_if(Auth::id() !== $document->owner_id, 403);
        $request->validate(['title' => 'required|string|max:500']);
        $document->update($request->only('title', 'description', 'status'));
        return redirect()->route('documents.show', $document)->with('success', 'Document mis à jour.');
    }

    public function destroy(Document $document)
    {
        $this->guardPermission('documents.delete');
        abort_if(Auth::id() !== $document->owner_id, 403);

        // Déplacer en corbeille (soft delete) sans supprimer le fichier physique
        $document->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('documents.index')->with('success', 'Document déplacé vers la corbeille.');
    }

    public function trash()
    {
        $this->guardPermission('documents.view');

        $documents = Document::onlyTrashed()
            ->where('owner_id', Auth::id())
            ->orderByDesc('deleted_at')
            ->get();

        return view('documents.trash', compact('documents'));
    }

    public function restore(string $id)
    {
        $this->guardPermission('documents.delete');

        $document = Document::onlyTrashed()
            ->where('owner_id', Auth::id())
            ->findOrFail($id);

        $document->restore();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('documents.trash')->with('success', 'Document restauré avec succès.');
    }

    public function forceDelete(string $id)
    {
        $this->guardPermission('documents.delete');

        $document = Document::onlyTrashed()
            ->where('owner_id', Auth::id())
            ->findOrFail($id);

        foreach (['file_path', 'signed_file_path', 'final_file_path'] as $field) {
            if (!empty($document->$field)) {
                $rel = ltrim(str_replace('/storage/', '', (string) $document->$field), '/');
                if (Storage::disk('public')->exists($rel)) {
                    Storage::disk('public')->delete($rel);
                }
            }
        }

        $document->forceDelete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('documents.trash')->with('success', 'Document supprimé définitivement.');
    }

    public function download(Request $request, Document $document)
    {
        abort_if(!$this->userCanAccessDocument($document), 403);

        $user = Auth::user();
        if ($user && (string) $user->id !== (string) $document->owner_id) {
            $userEmail = (string) ($user->email ?? '');
            $profile = $user->profile;

            $recipientAdminIds = collect();
            if ($profile && $profile->administration_type === 'recipient' && $profile->administration_id) {
                $recipientAdminIds = collect([$profile->administration_id]);
            } else {
                $adminCode = strtoupper(trim((string) ($profile?->administration?->code ?? '')));
                if ($adminCode !== '') {
                    $recipientAdminIds = RecipientAdministration::query()
                        ->whereRaw('UPPER(code) = ?', [$adminCode])
                        ->pluck('id');
                }
            }

            $recipientShares = DocumentShare::query()
                ->where('document_id', $document->id)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
                })
                ->where(function ($q) use ($user, $userEmail, $recipientAdminIds) {
                    $q->where('recipient_name', 'user:' . $user->id);

                    if ($userEmail !== '') {
                        $q->orWhere('recipient_email', $userEmail);
                    }

                    if ($recipientAdminIds->isNotEmpty()) {
                        $q->orWhereIn('recipient_administration_id', $recipientAdminIds);
                    }
                })
                ->get(['tracking_number', 'recipient_administration_id', 'applicant_email', 'applicant_full_name']);

            foreach ($recipientShares as $share) {
                $trackingNumber = strtoupper(trim((string) ($share->tracking_number ?? '')));
                $submission = null;

                if ($trackingNumber !== '') {
                    $submission = ActRequestSubmission::query()
                        ->whereRaw('UPPER(tracking_number) = ?', [$trackingNumber])
                        ->first();
                }

                if (!$submission && !empty($share->recipient_administration_id)) {
                    $submission = ActRequestSubmission::query()
                        ->where('recipient_administration_id', $share->recipient_administration_id)
                        ->where('applicant_email', (string) ($share->applicant_email ?? ''))
                        ->where('applicant_full_name', (string) ($share->applicant_full_name ?? ''))
                        ->whereIn('status', ['pending', 'in_progress', 'sent', 'recu'])
                        ->latest()
                        ->first();
                }

                if ($submission && $submission->status !== 'treated') {
                    $submission->status = 'treated';
                    $submission->save();
                }
            }
        }

        $preferSource = $request->boolean('source');
        $sourcePath = $preferSource
            ? (string) ($document->file_path ?: $document->final_file_path ?: $document->signed_file_path ?: '')
            : (string) ($document->final_file_path ?: $document->signed_file_path ?: $document->file_path ?: '');
        $path = ltrim(str_replace('/storage/', '', $sourcePath), '/');
        $ext  = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'bin';

        if ($path === '' || !Storage::disk('public')->exists($path)) {
            abort(404, 'Fichier introuvable sur le serveur.');
        }

        // Sanitiser le titre : supprimer / \ et caractères de contrôle
        $safeTitle = preg_replace('/[\/\\\\\x00-\x1f]+/', '-', $document->title);
        $safeTitle = trim($safeTitle, '-') ?: 'document';

        $name = pathinfo($safeTitle, PATHINFO_EXTENSION) === $ext
            ? $safeTitle
            : $safeTitle . '.' . $ext;

        if ($request->boolean('inline')) {
            return Storage::disk('public')->response($path, $name, [
                'Content-Type' => ($preferSource ? ($document->mime_type ?: 'application/octet-stream') : (strtolower((string) pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'pdf' ? 'application/pdf' : ($document->mime_type ?: 'application/octet-stream'))),
                'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            ]);
        }

        return Storage::disk('public')->download($path, $name);
    }

    /**
     * Téléchargement externe via lien signé (partage externe).
     */
    public function sharedDownload(Request $request, DocumentShare $share)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Lien invalide ou expiré');
        }

        if ($share->mode !== 'external') {
            abort(403, 'Accès non autorisé pour ce partage');
        }

        if ($share->expires_at && $share->expires_at->isPast()) {
            abort(410, 'Ce lien de téléchargement a expiré');
        }

        $document = Document::findOrFail($share->document_id);
        $sourcePath = (string) ($document->final_file_path ?: $document->signed_file_path ?: $document->file_path ?: '');
        $path = ltrim(str_replace('/storage/', '', $sourcePath), '/');
        $ext  = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'bin';

        if ($path === '' || !Storage::disk('public')->exists($path)) {
            abort(404, 'Fichier introuvable');
        }

        $safeTitle = preg_replace('/[\/\\\\\x00-\x1f]+/', '-', $document->title);
        $safeTitle = trim((string) $safeTitle, '-') ?: 'document';
        $name = pathinfo($safeTitle, PATHINFO_EXTENSION) === $ext ? $safeTitle : ($safeTitle . '.' . $ext);

        return Storage::disk('public')->download($path, $name);
    }

    /**
     * Fichier document pour OnlyOffice via URL signée temporaire (sans session utilisateur).
     */
    public function onlyofficeFile(Request $request, Document $document)
    {
        $expires = (int) $request->query('expires', 0);
        $access  = (string) $request->query('access', '');

        if ($expires <= 0 || $expires < time()) {
            abort(403, 'Lien expiré ou invalide');
        }

        $signKey = (string) config('app.key');
        $expected = hash_hmac('sha256', $document->id . '|' . $expires, $signKey);

        if (!hash_equals($expected, $access)) {
            abort(403, 'Lien expiré ou invalide');
        }

        $path = ltrim(str_replace('/storage/', '', (string) $document->file_path), '/');
        $ext  = pathinfo((string) $document->file_path, PATHINFO_EXTENSION) ?: 'bin';

        if ($path === '' || !Storage::disk('public')->exists($path)) {
            abort(404, 'Fichier introuvable');
        }

        $safeTitle = preg_replace('/[\/\\\\\x00-\x1f]+/', '-', $document->title);
        $safeTitle = trim($safeTitle, '-') ?: 'document';

        $name = pathinfo($safeTitle, PATHINFO_EXTENSION) === $ext
            ? $safeTitle
            : $safeTitle . '.' . $ext;

        return Storage::disk('public')->response($path, $name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Callback OnlyOffice pour les documents (sauvegarde du fichier édité).
     */
    public function onlyofficeCallback(Request $request, Document $document)
    {
        $access = (string) $request->query('access', '');
        $canEdit = (int) $request->query('can_edit', 0);
        $expires = (int) $request->query('expires', 0);
        $expected = hash_hmac(
            'sha256',
            'cb|' . $document->id . '|' . $canEdit . '|' . $expires,
            (string) config('app.key')
        );

        if (!hash_equals($expected, $access) || $expires <= 0 || $expires < time()) {
            return response()->json(['error' => 1], 403);
        }

        if ($canEdit !== 1) {
            return response()->json(['error' => 0]);
        }

        $status  = (int) $request->input('status', 0);
        $fileUrl = (string) $request->input('url', '');

        // 2 = must save, 6 = must force save (OnlyOffice callbacks)
        if (in_array($status, [2, 6], true) && $fileUrl !== '') {
            // ── Validation SSRF : l'URL doit provenir du serveur OO configuré ──
            $ooServerUrl = (string) AppSetting::where('key', 'onlyoffice_server_url')->value('value');
            if ($ooServerUrl !== '') {
                $ooHost   = parse_url(rtrim($ooServerUrl, '/'), PHP_URL_HOST);
                $fileHost = parse_url($fileUrl, PHP_URL_HOST);
                if (!$ooHost || !$fileHost || strtolower((string) $ooHost) !== strtolower((string) $fileHost)) {
                    Log::warning('OnlyOffice callback: URL rejetée (hôte non autorisé)', [
                        'document_id'  => (string) $document->id,
                        'allowed_host' => $ooHost,
                        'received_host'=> $fileHost,
                    ]);
                    return response()->json(['error' => 0]);
                }
            }

            try {
                $response = Http::timeout(60)->get($fileUrl);
                if ($response->successful()) {
                    $path = ltrim(str_replace('/storage/', '', (string) $document->file_path), '/');
                    Storage::disk('public')->put($path, $response->body());
                    $document->touch();
                } else {
                    Log::warning('OnlyOffice callback download failed', [
                        'document_id' => (string) $document->id,
                        'status' => $status,
                        'http_status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('OnlyOffice callback exception', [
                    'document_id' => (string) $document->id,
                    'status' => $status,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Protocole OnlyOffice: 0 = OK
        return response()->json(['error' => 0]);
    }

    public function toggleFavorite(Request $request, Document $document)
    {
        abort_if(!$this->userCanAccessDocument($document), 403);

        $pref = DocumentUserPreference::firstOrCreate(
            ['user_id' => Auth::id(), 'document_id' => $document->id],
            ['is_favorite' => false, 'label_codes' => []]
        );
        $pref->update(['is_favorite' => !$pref->is_favorite]);
        return response()->json(['is_favorite' => $pref->is_favorite]);
    }

    public function updateLabels(Request $request, Document $document)
    {
        abort_if(!$this->userCanAccessDocument($document), 403);

        $codes = collect(explode(',', $request->input('codes', '')))
            ->map(fn($c) => strtoupper(trim($c)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $pref = DocumentUserPreference::firstOrCreate(
            ['user_id' => Auth::id(), 'document_id' => $document->id],
            ['is_favorite' => false, 'label_codes' => []]
        );
        $pref->update(['label_codes' => $codes]);
        return response()->json(['label_codes' => $codes]);
    }

    public function rename(Request $request, Document $document)
    {
        abort_if(Auth::id() !== $document->owner_id, 403);

        $request->validate(['title' => 'required|string|max:500']);
        $document->update(['title' => $request->title]);
        return response()->json(['title' => $document->title]);
    }

    public function move(Request $request, Document $document)
    {
        abort_if(Auth::id() !== $document->owner_id, 403);

        $folder = $request->input('folder', '');
        $document->update(['description' => $folder ? "Dossier: {$folder}" : null]);
        return response()->json(['ok' => true]);
    }

    public function share(Request $request, Document $document)
    {
        $this->guardPermission('documents.share');
        abort_if(!$this->userCanManageDocument($document, Auth::id()), 403);

        $request->validate([
            'mode'       => 'required|in:internal,external,admin,recipient_administration',
            'permission' => 'nullable|in:lecture,modification',
            'trackingNumber' => 'nullable|string|max:60',
        ]);

        $mode = $request->input('mode') === 'recipient_administration' ? 'admin' : $request->input('mode');

        try {

        $hasDelay = $request->boolean('hasDelay') || $request->boolean('has_delay');
        $delayValue = (int) ($request->input('delayValue', $request->input('delay_value', 0)) ?: 0);
        $delayUnit = (string) ($request->input('delayUnit', $request->input('delay_unit', 'hours')) ?: 'hours');
        $delayUnit = $delayUnit === 'days' ? 'days' : 'hours';

        $expiresAt = null;
        if ($hasDelay && $delayValue > 0) {
            $expiresAt = now()->add($delayUnit, $delayValue);
        }

        $basePayload = [
            'document_id'                  => $document->id,
            'shared_by'                    => Auth::id(),
            'mode'                         => $mode,
            'permission'                   => $request->input('permission', 'lecture'),
            'has_delay'                    => $hasDelay,
            'delay_value'                  => $delayValue > 0 ? $delayValue : null,
            'delay_unit'                   => $delayValue > 0 ? $delayUnit : null,
            'expires_at'                   => $expiresAt,
            // Toujours null par defaut; renseigne explicitement uniquement en mode admin.
            'recipient_administration_id'  => null,
            'applicant_full_name'          => $request->input('applicantFullName'),
            'applicant_matricule'          => $request->input('applicantMatricule'),
            'applicant_email'              => $request->input('applicantEmail'),
            'applicant_phone'              => $request->input('applicantPhone'),
            'applicant_rib'               => $request->input('applicantRib') ? strtoupper(trim((string) $request->input('applicantRib'))) : null,
            'tracking_number'              => strtoupper(trim((string) $request->input('trackingNumber', ''))),
        ];

        $createdShares = collect();
        $updatedTrackingStatus = false;

        if ($mode === 'internal') {
            $targetType = (string) $request->input('internalTargetType', 'user');

            if ($targetType === 'user') {
                $targetUserId = (string) $request->input('internalUserId', '');
                $targetUser = User::query()
                    ->where('id', $targetUserId)
                    ->where('status', 'active')
                    ->first();

                if (!$targetUser) {
                    return response()->json(['ok' => false, 'message' => 'Utilisateur destinataire introuvable.'], 422);
                }

                $createdShares->push(DocumentShare::create($this->filterDocumentSharePayload($basePayload + [
                    'recipient_name'  => 'user:' . $targetUser->id,
                    'recipient_email' => $targetUser->email,
                ])));

                $this->createShareNotification([
                    'recipient_id' => $targetUser->id,
                    'title' => 'Nouveau document partagé',
                    'message' => 'Le document "' . $document->title . '" vous a été partagé.',
                    'type' => 'info',
                    'action_url' => route('documents.index'),
                    'is_read' => false,
                ]);

                $this->sendInternalShareEmail($targetUser->email, $document->title);
            } else {
                $subEntityCode = strtoupper(trim((string) $request->input('internalSubEntityCode', '')));
                if ($subEntityCode === '') {
                    return response()->json(['ok' => false, 'message' => 'Veuillez sélectionner une entité sous tutelle.'], 422);
                }

                $currentAdminId = Auth::user()?->profile?->administration_id;

                $targetUserIds = UserDirectionAssignment::query()
                    ->whereRaw('UPPER(sub_entity_code) = ?', [$subEntityCode])
                    ->pluck('user_id')
                    ->unique();

                $targetUsersQuery = User::query()
                    ->whereIn('id', $targetUserIds)
                    ->where('status', 'active')
                    ->where('id', '!=', Auth::id());

                if ($currentAdminId) {
                    $targetUsersQuery->whereHas('profile', function ($q) use ($currentAdminId) {
                        $q->where('administration_id', $currentAdminId);
                    });
                }

                $targetUsers = $targetUsersQuery->get(['id', 'email']);

                if ($targetUsers->isEmpty()) {
                    return response()->json(['ok' => false, 'message' => 'Aucun utilisateur actif trouvé pour cette entité sous tutelle.'], 422);
                }

                foreach ($targetUsers as $targetUser) {
                    $createdShares->push(DocumentShare::create($this->filterDocumentSharePayload($basePayload + [
                        'recipient_name'  => 'user:' . $targetUser->id,
                        'recipient_email' => $targetUser->email,
                    ])));

                    $this->createShareNotification([
                        'recipient_id' => $targetUser->id,
                        'title' => 'Nouveau document partagé',
                        'message' => 'Le document "' . $document->title . '" a été partagé à votre entité sous tutelle.',
                        'type' => 'info',
                        'action_url' => route('documents.index'),
                        'is_read' => false,
                    ]);

                    $this->sendInternalShareEmail($targetUser->email, $document->title);
                }
            }
        } elseif ($mode === 'external') {
            $recipientEmail = trim((string) $request->input('recipientEmail', ''));
            if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['ok' => false, 'message' => 'Adresse email externe invalide.'], 422);
            }

            $share = DocumentShare::create($this->filterDocumentSharePayload($basePayload + [
                'recipient_name'  => trim((string) $request->input('recipientName', 'Destinataire externe')),
                'recipient_email' => $recipientEmail,
            ]));
            $createdShares->push($share);

            $linkExpiry = $share->expires_at ?: now()->addDays(7);
            $downloadLink = URL::temporarySignedRoute('documents.shared-download', $linkExpiry, ['share' => $share->id]);

            try {
                Mail::raw(
                    "Bonjour,\n\nUn document vous a ete partage.\nTitre: {$document->title}\nLien de telechargement: {$downloadLink}\n\nCe lien est securise et peut expirer.",
                    function ($message) use ($recipientEmail, $document) {
                        $message->to($recipientEmail)->subject('Partage de document: ' . $document->title);
                    }
                );
            } catch (\Throwable $e) {
                Log::error('Echec envoi email partage externe', [
                    'document_id' => (string) $document->id,
                    'recipient_email' => $recipientEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $recipientAdministrationId = (string) $request->input('recipientAdministrationId', '');
            if ($recipientAdministrationId === '') {
                return response()->json(['ok' => false, 'message' => 'Veuillez sélectionner une administration destinataire.'], 422);
            }

            $trackingNumber = strtoupper(trim((string) $request->input('trackingNumber', '')));
            if ($trackingNumber === '') {
                return response()->json(['ok' => false, 'message' => 'Numero de suivi obligatoire pour ce type de partage.'], 422);
            }

            $recipientAdministration = RecipientAdministration::find($recipientAdministrationId);
            if (!$recipientAdministration) {
                return response()->json(['ok' => false, 'message' => 'Administration destinataire introuvable.'], 422);
            }

            $adminSector = strtolower((string) ($recipientAdministration->metadata['sector'] ?? ''));
            if ($adminSector === 'banques' && empty(trim((string) $request->input('applicantRib', '')))) {
                return response()->json(['ok' => false, 'message' => 'Le RIB est obligatoire pour les établissements bancaires.'], 422);
            }

            // Trace de partage administration (audit)
            $createdShares->push(DocumentShare::create($this->filterDocumentSharePayload($basePayload + [
                'recipient_name' => 'admin:' . $recipientAdministration->id,
                'recipient_email' => $request->input('applicantEmail'),
                'recipient_administration_id' => $recipientAdministration->id,
            ])));

            // Chercher les utilisateurs dont le profil appartient directement à cette administration destinataire
            $targetUsers = User::query()
                ->where('status', 'active')
                ->whereHas('profile', function ($q) use ($recipientAdministrationId) {
                    $q->where('administration_id', $recipientAdministrationId)
                      ->where('administration_type', 'recipient');
                })
                ->get(['id', 'email']);

            foreach ($targetUsers as $targetUser) {
                // Créer un share individuel pour que le document apparaisse dans /reception
                // même si le profil n'est pas parfaitement configuré côté requête
                $alreadyShared = DocumentShare::where('document_id', $document->id)
                    ->where('recipient_name', 'user:' . $targetUser->id)
                    ->exists();

                if (!$alreadyShared) {
                    DocumentShare::create($this->filterDocumentSharePayload([
                        'document_id'                 => $document->id,
                        'shared_by'                   => Auth::id(),
                        'mode'                        => 'admin',
                        'permission'                  => 'lecture',
                        'has_delay'                   => $hasDelay,
                        'delay_value'                 => $delayValue > 0 ? $delayValue : null,
                        'delay_unit'                  => $delayValue > 0 ? $delayUnit : null,
                        'expires_at'                  => $expiresAt,
                        'recipient_name'              => 'user:' . $targetUser->id,
                        'recipient_email'             => $targetUser->email,
                        'recipient_administration_id' => $recipientAdministration->id,
                    ]));
                }

                $this->createShareNotification([
                    'recipient_id' => $targetUser->id,
                    'title' => 'Document recu (administration)',
                    'message' => 'Le document "' . $document->title . '" a ete partage a votre administration.',
                    'type' => 'info',
                    'action_url' => route('reception.index'),
                    'is_read' => false,
                ]);
            }

            if ($trackingNumber !== '') {
                $submission = ActRequestSubmission::query()
                    ->whereRaw('UPPER(tracking_number) = ?', [$trackingNumber])
                    ->first();

                Log::info('DocumentController@share tracking lookup', [
                    'tracking_number_searched' => $trackingNumber,
                    'submission_found' => $submission ? $submission->id : null,
                    'document_id' => (string) $document->id,
                ]);

                if ($submission) {
                    try {
                        // Conserver une valeur de statut généralement admise sur tous les schémas existants.
                        $submission->status = 'in_progress';
                        $submission->save();
                        $updatedTrackingStatus = true;
                        Log::info('DocumentController@share submission status set to in_progress', ['submission_id' => $submission->id]);
                    } catch (\Throwable $e) {
                        Log::warning('DocumentController@share unable to update submission status', [
                            'submission_id' => (string) $submission->id,
                            'tracking_number' => $trackingNumber,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Le document est considere comme envoye a l'administration destinataire.
            if ($document->status !== 'sent') {
                $document->update(['status' => 'sent']);
            }
        }

        $sharesTotal = DocumentShare::where('document_id', $document->id)->count();

        // Notifier le destinataire interne
        // TODO: Implement NotificationService::documentShared() when available
        // NotificationService::documentShared($document, $share, Auth::user()->name);

        $message = 'Document partagé avec succès.';
        if ($mode === 'admin' && !$updatedTrackingStatus) {
            $message .= ' (Statut de la demande non mis à jour — numéro de suivi introuvable)';
        }

        return response()->json([
            'ok'           => true,
            'message'      => $message,
            'shares_count' => $sharesTotal,
            'created_shares' => $createdShares->count(),
            'document_status' => $document->status,
            'tracking_status_updated' => $updatedTrackingStatus,
        ]);
        } catch (\Throwable $e) {
            Log::error('DocumentController@share failed', [
                'document_id' => (string) $document->id,
                'user_id' => (string) Auth::id(),
                'mode' => $request->input('mode'),
                'internal_target_type' => $request->input('internalTargetType'),
                'recipient_administration_id' => $request->input('recipientAdministrationId'),
                'tracking_number' => $request->input('trackingNumber'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Erreur interne lors du partage du document.',
            ], 500);
        }
    }

    private function sendInternalShareEmail(string $recipientEmail, string $documentTitle): void
    {
        if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $documentsUrl = route('documents.index');

        try {
            Mail::raw(
                "Bonjour,\n\nUn document vous a ete partage en interne.\nTitre: {$documentTitle}\nAcces: {$documentsUrl}\n\nConnectez-vous pour le consulter dans l'onglet Mes documents.",
                function ($message) use ($recipientEmail, $documentTitle) {
                    $message->to($recipientEmail)->subject('Nouveau document partage: ' . $documentTitle);
                }
            );
        } catch (\Throwable $e) {
            Log::error('Echec envoi email partage interne', [
                'recipient_email' => $recipientEmail,
                'document_title' => $documentTitle,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function filterDocumentSharePayload(array $payload): array
    {
        static $allowed = null;

        if ($allowed === null) {
            try {
                $allowed = Schema::hasTable('document_shares')
                    ? array_flip(Schema::getColumnListing('document_shares'))
                    : [];
            } catch (\Throwable $e) {
                Log::warning('DocumentController@share unable to read document_shares columns', [
                    'error' => $e->getMessage(),
                ]);
                $allowed = [];
            }
        }

        if ($allowed === []) {
            return $payload;
        }

        return array_filter(
            $payload,
            static fn ($key) => isset($allowed[$key]),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function createShareNotification(array $payload): void
    {
        try {
            Notification::create($payload);
        } catch (\Throwable $e) {
            Log::warning('DocumentController@share notification skipped', [
                'recipient_id' => (string) ($payload['recipient_id'] ?? ''),
                'title' => (string) ($payload['title'] ?? ''),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function lookupActRequestByTracking(Request $request)
    {
        $request->validate([
            'tracking_number' => 'required|string|max:60',
        ]);

        $trackingNumber = strtoupper(trim((string) $request->input('tracking_number', '')));

        $submission = ActRequestSubmission::query()
            ->with(['recipientAdministration:id,name,code'])
            ->whereRaw('UPPER(tracking_number) = ?', [$trackingNumber])
            ->first();

        if (!$submission) {
            return response()->json([
                'ok' => false,
                'message' => 'Numero de suivi introuvable.',
            ], 404);
        }

        if (empty($submission->recipient_administration_id)) {
            return response()->json([
                'ok' => false,
                'message' => 'Aucune administration destinataire associee a ce numero de suivi.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'tracking_number' => (string) $submission->tracking_number,
                'recipient_administration_id' => (string) $submission->recipient_administration_id,
                'recipient_administration_name' => (string) ($submission->recipientAdministration?->name ?? ''),
                'recipient_administration_code' => (string) ($submission->recipientAdministration?->code ?? ''),
                'applicant_full_name' => (string) ($submission->applicant_full_name ?? ''),
                'applicant_email' => (string) ($submission->applicant_email ?? ''),
                'applicant_phone' => (string) ($submission->applicant_phone ?? ''),
            ],
        ]);
    }

    private function userCanAccessDocument(Document $document): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($this->userCanManageDocument($document, $user->id)) {
            return true;
        }

        return $this->resolveCurrentUserSharePermission($document, $user) !== null;
    }

    private function resolveCurrentUserSharePermission(Document $document, User $user): ?string
    {
        $subEntityCodes = UserDirectionAssignment::query()
            ->where('user_id', $user->id)
            ->whereNotNull('sub_entity_code')
            ->pluck('sub_entity_code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->values();

        $profile = $user->profile;
        $recipientAdminIds = collect();
        if ($profile && $profile->administration_type === 'recipient' && $profile->administration_id) {
            $recipientAdminIds = collect([$profile->administration_id]);
        } else {
            $adminCode = strtoupper(trim((string) ($profile?->administration?->code ?? '')));
            if ($adminCode !== '') {
                $recipientAdminIds = RecipientAdministration::query()
                    ->whereRaw('UPPER(code) = ?', [$adminCode])
                    ->pluck('id');
            }
        }

        $shares = DocumentShare::query()
            ->where('document_id', $document->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) use ($user, $subEntityCodes, $recipientAdminIds) {
                $q->where('recipient_name', 'user:' . $user->id);

                if (!empty($user->email)) {
                    $q->orWhere('recipient_email', $user->email);
                }

                foreach ($subEntityCodes as $code) {
                    $q->orWhere('recipient_name', 'sub_entity:' . $code);
                }

                if ($recipientAdminIds->isNotEmpty()) {
                    $q->orWhereIn('recipient_administration_id', $recipientAdminIds);
                }
            })
            ->get(['permission']);

        if ($shares->isEmpty()) {
            return null;
        }

        return $shares->contains(fn ($share) => (string) $share->permission === 'modification')
            ? 'modification'
            : 'lecture';
    }

    private function userCanManageDocument(Document $document, $userId): bool
    {
        $userId = (string) ($userId ?? '');
        if ($userId === '') {
            return false;
        }

        return (string) $document->owner_id === $userId
            || (string) ($document->created_by ?? '') === $userId;
    }

    public function versions(Document $document)
    {
        abort_if(Auth::id() !== $document->owner_id, 403);

        $versions = $document->versions()
            ->orderByDesc('version')
            ->get()
            ->map(fn($v) => [
                'id'         => $v->id,
                'version'    => $v->version,
                'file_path'  => $v->file_path,
                'change_log' => $v->change_log,
                'created_at' => $v->created_at?->toISOString(),
            ]);

        return response()->json($versions);
    }

    public function changeStatus(Request $request, Document $document)
    {
        abort_if(Auth::id() !== $document->owner_id, 403);
        $request->validate(['status' => 'required|in:draft,active,archived']);
        $document->update(['status' => $request->status]);
        return response()->json(['status' => $document->status]);
    }

    public function createNew(Request $request)
    {
        $this->guardPermission('documents');
        $title  = $request->input('title', 'Nouveau document');
        $type   = $request->input('type', 'file');
        $folder = $request->input('folder');

        $filePath = '';
        $mimeType = 'application/octet-stream';

        if ($type !== 'folder') {
            // Déterminer l'extension et le mime type
            $ext = 'docx';
            if (str_ends_with(strtolower($title), '.xlsx') || $type === 'sheet') {
                $ext = 'xlsx'; $mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            } elseif (str_ends_with(strtolower($title), '.pptx') || $type === 'presentation') {
                $ext = 'pptx'; $mimeType = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            } elseif (str_ends_with(strtolower($title), '.docx') || $type === 'doc' || $type === 'file') {
                $ext = 'docx'; $mimeType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }

            // Copier le fichier vierge correspondant depuis les assets publics
            $blankSource = public_path("blank_{$ext}.{$ext}");
            // Fallback sur empty_template.docx pour docx
            if (!file_exists($blankSource) && $ext === 'docx') {
                $blankSource = public_path('empty_template.docx');
            }

            $storeName = Str::uuid() . '.' . $ext;
            $storedPath = 'documents/' . $storeName;

            if (file_exists($blankSource)) {
                Storage::disk('public')->put($storedPath, file_get_contents($blankSource));
            } else {
                Storage::disk('public')->put($storedPath, '');
            }

            $filePath = '/storage/' . $storedPath;
        }

        $doc = Document::create([
            'id'          => Str::uuid(),
            'title'       => $title,
            'description' => $type === 'folder' ? '[folder]' : ($folder ? "Dossier: {$folder}" : null),
            'file_path'   => $filePath,
            'final_file_path' => $filePath,
            'mime_type'   => $type === 'folder' ? 'application/x-folder' : $mimeType,
            'status'      => 'draft',
            'owner_id'    => Auth::id(),
            'created_by'  => Auth::id(),
        ]);

        if ($type !== 'folder' && $filePath) {
            DocumentVersion::create([
                'id'          => Str::uuid(),
                'document_id' => $doc->id,
                'version'     => 1,
                'file_path'   => $filePath,
                'creator_id'  => Auth::id(),
                'change_log'  => 'Création',
            ]);
        }

        return response()->json([
            'id'               => $doc->id,
            'owner_id'         => $doc->owner_id,
            'is_owner'         => true,
            'can_share'        => true,
            'share_permission' => 'modification',
            'can_edit_content' => true,
            'title'            => $doc->title,
            'description'      => $doc->description,
            'file_path'        => $doc->file_path,
            'final_file_path'  => $doc->final_file_path,
            'file_size'        => $doc->file_size,
            'mime_type'        => $doc->mime_type,
            'status'           => $doc->status,
            'shares_count'     => 0,
            'created_at'       => $doc->created_at->toISOString(),
            'updated_at'       => $doc->updated_at->toISOString(),
        ]);
    }

    /**
     * Génère la configuration OnlyOffice (JWT) pour ouvrir un document existant
     */
    public function onlyofficeConfig(Request $request)
    {
        $docId = $request->input('document_id');
        $doc   = Document::find($docId);
        $user  = Auth::user();

        if (!$doc || !$user || !$this->userCanAccessDocument($doc)) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }

        $canManageDocument = $this->userCanManageDocument($doc, $user->id);
        $sharePermission = $canManageDocument ? 'modification' : $this->resolveCurrentUserSharePermission($doc, $user);
        if (!$sharePermission) {
            return response()->json(['error' => 'Non autorisé'], 403);
        }
        $canEdit = $canManageDocument || $sharePermission === 'modification';

        $onlyofficeUrl    = AppSetting::where('key', 'onlyoffice_server_url')->value('value') ?: '';
        $onlyofficeSecret = AppSetting::where('key', 'onlyoffice_secret')->value('value') ?: '';
        $appPublicUrl     = AppSetting::where('key', 'app_public_url')->value('value') ?: '';

        if (!$onlyofficeUrl) {
            return response()->json(['error' => 'OnlyOffice non configuré'], 400);
        }

        $relativePath = ltrim(str_replace('/storage/', '', (string) $doc->file_path), '/');
        if (!$relativePath || !Storage::disk('public')->exists($relativePath)) {
            return response()->json(['error' => 'Fichier introuvable sur le serveur de stockage'], 404);
        }

        // Token d'accès temporaire indépendant du host (proxy/ngrok).
        $expires = now()->addHours(8)->timestamp;
        $access  = hash_hmac('sha256', $doc->id . '|' . $expires, (string) config('app.key'));
        $signedUrl = route('documents.onlyofficeFile', [
            'document' => $doc->id,
            'expires'  => $expires,
            'access'   => $access,
        ], false);

        if ($appPublicUrl) {
            // On respecte exactement la base publique configurée (y compris un éventuel /public).
            $docUrl = rtrim($appPublicUrl, '/') . $signedUrl;
        } else {
            $docUrl = url($signedUrl);
        }

        $fileExt = strtolower(pathinfo(parse_url($doc->file_path, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'docx');
        $docType = match($fileExt) {
            'xlsx', 'xls', 'ods', 'csv' => 'cell',
            'pptx', 'ppt', 'odp'         => 'slide',
            'pdf'                         => 'pdf',
            default                       => 'word',
        };

        $callbackExpires = now()->addHours(8)->timestamp;
        $callbackCanEdit = $canEdit ? 1 : 0;
        $callbackAccess = hash_hmac(
            'sha256',
            'cb|' . $doc->id . '|' . $callbackCanEdit . '|' . $callbackExpires,
            (string) config('app.key')
        );
        $callbackUrl = ($appPublicUrl ? rtrim($appPublicUrl, '/') : rtrim(config('app.url'), '/'))
            . '/api/oo-callback/document/' . $doc->id
            . '?access=' . $callbackAccess
            . '&can_edit=' . $callbackCanEdit
            . '&expires=' . $callbackExpires;

        $payload = [
            'document' => [
                'fileType' => $fileExt,
                'key'      => 'doc-' . $doc->id . '-' . ($doc->updated_at ? $doc->updated_at->timestamp : time()),
                'title'    => $doc->title,
                'url'      => $docUrl,
                'permissions' => ['edit' => $canEdit, 'download' => true, 'print' => true],
            ],
            'documentType' => $docType,
            'editorConfig' => [
                'mode'        => $canEdit ? 'edit' : 'view',
                'lang'        => 'fr',
                'callbackUrl' => $callbackUrl,
                'user'        => ['id' => 'u-' . Auth::id(), 'name' => Auth::user()->name ?? 'Utilisateur'],
                'customization' => [
                    'autosave' => $canEdit,
                    'compactHeader' => true,
                    'features' => [
                        'tabStyle' => 'fill',
                        'tabBackground' => 'header',
                    ],
                ],
            ],
        ];

        $payload['document']['permissions']['chat'] = false;

        $token = '';
        if ($onlyofficeSecret) {
            $header    = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
            $body      = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
            $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$body", $onlyofficeSecret, true)), '+/', '-_'), '=');
            $token = "$header.$body.$signature";
        }

        return response()->json([
            'ooUrl'        => rtrim($onlyofficeUrl, '/'),
            'token'        => $token,
            'ooConfig'     => $payload,
            'documentType' => $docType,
            'fileType'     => $fileExt,
            'key'          => $payload['document']['key'],
            'title'        => $doc->title,
            'url'          => $docUrl,
        ]);
    }

    public function convertToPdf(Document $document)
    {
        abort_if(!$this->userCanManageDocument($document, Auth::id()), 403);

        if (($document->description ?? '') === '[folder]') {
            return response()->json(['ok' => false, 'message' => 'Un dossier ne peut pas être converti.'], 422);
        }

        $sourcePath = (string) ($document->file_path ?: $document->final_file_path ?: '');
        $relative = ltrim(str_replace('/storage/', '', $sourcePath), '/');
        if ($relative === '' || !Storage::disk('public')->exists($relative)) {
            return response()->json(['ok' => false, 'message' => 'Fichier source introuvable.'], 404);
        }

        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        $convertible = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp'];
        if (!in_array($ext, $convertible, true)) {
            return response()->json(['ok' => false, 'message' => 'Ce type de fichier ne peut pas être converti en PDF.'], 422);
        }

        $absOfficePath = Storage::disk('public')->path($relative);
        $pdfAbsPath = $this->convertOfficeToPdf($absOfficePath);
        if (!$pdfAbsPath || !file_exists($pdfAbsPath) || $this->isSuspiciousPdf($pdfAbsPath)) {
            if ($pdfAbsPath && file_exists($pdfAbsPath)) {
                @unlink($pdfAbsPath);
            }
            Log::warning('Document convertToPdf failed', [
                'document_id' => (string) $document->id,
                'file' => $relative,
                'pdfAbsPath' => $pdfAbsPath,
                'pdfExists' => $pdfAbsPath ? file_exists($pdfAbsPath) : false,
            ]);
            return response()->json(['ok' => false, 'message' => 'La conversion PDF a échoué. Vérifiez LibreOffice/OnlyOffice.'], 422);
        }

        $pdfDestPath = 'documents/' . pathinfo($relative, PATHINFO_FILENAME) . '-' . now()->format('Ymd-His') . '.pdf';
        Storage::disk('public')->put($pdfDestPath, file_get_contents($pdfAbsPath));
        @unlink($pdfAbsPath);

        $publicPdfPath = '/storage/' . $pdfDestPath;
        $title = (string) ($document->title ?? 'document');
        $titleWithoutExt = preg_replace('/\.(doc|docx|xls|xlsx|ppt|pptx|odt|ods|odp|pdf)$/i', '', $title) ?: $title;
        $pdfTitle = rtrim($titleWithoutExt) . '.pdf';
        $document->update([
            'title' => $pdfTitle,
            'file_path' => $publicPdfPath,
            'final_file_path' => $publicPdfPath,
            'mime_type' => 'application/pdf',
            'file_size' => (int) Storage::disk('public')->size($pdfDestPath),
        ]);

        try {
            $nextVersion = ((int) ($document->versions()->max('version') ?? 0)) + 1;
            DocumentVersion::create([
                'id' => Str::uuid(),
                'document_id' => $document->id,
                'version' => $nextVersion,
                'file_path' => $publicPdfPath,
                'creator_id' => Auth::id(),
                'change_log' => 'Conversion en PDF depuis Mes Documents',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Document convertToPdf: version save failed', [
                'document_id' => (string) $document->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Conversion en PDF effectuée avec succès.',
            'title' => $document->title,
            'file_path' => $publicPdfPath,
            'final_file_path' => $publicPdfPath,
            'file_size' => $document->file_size,
            'status' => $document->status,
        ]);
    }

    private function convertOfficeToPdf(string $absOfficePath): ?string
    {
        foreach (['soffice', 'libreoffice'] as $bin) {
            @exec('where ' . $bin . ' 2>NUL', $out, $rc);
            if ($rc !== 0) {
                @exec('which ' . $bin . ' 2>/dev/null', $out2, $rc2);
                $rc = $rc2;
            }
            if ($rc === 0) {
                $tmpOutDir = storage_path('app/tmp/pdf-convert');
                if (!is_dir($tmpOutDir)) {
                    @mkdir($tmpOutDir, 0755, true);
                }
                @exec($bin . ' --headless --nologo --convert-to pdf --outdir '
                    . escapeshellarg($tmpOutDir) . ' ' . escapeshellarg($absOfficePath),
                    $o, $code);
                if ($code === 0) {
                    $pdfFile = $tmpOutDir . DIRECTORY_SEPARATOR . pathinfo($absOfficePath, PATHINFO_FILENAME) . '.pdf';
                    if (file_exists($pdfFile)) {
                        return $pdfFile;
                    }
                    $candidates = glob($tmpOutDir . DIRECTORY_SEPARATOR . '*.pdf') ?: [];
                    if (!empty($candidates)) {
                        usort($candidates, fn($a, $b) => filemtime($b) <=> filemtime($a));
                        return $candidates[0];
                    }
                }
            }
        }

        try {
            $ooUrl    = rtrim((string) AppSetting::where('key', 'onlyoffice_server_url')->value('value'), '/');
            $ooSecret = (string) AppSetting::where('key', 'onlyoffice_secret')->value('value');
            $appUrl   = rtrim((string) AppSetting::where('key', 'app_public_url')->value('value'), '/');

            if (empty($ooUrl) || empty($appUrl)) {
                Log::warning('OnlyOffice not configured', [
                    'ooUrl' => empty($ooUrl) ? 'EMPTY' : 'SET',
                    'appUrl' => empty($appUrl) ? 'EMPTY' : 'SET',
                ]);
                return null;
            }

            $ext      = strtolower(pathinfo($absOfficePath, PATHINFO_EXTENSION));
            $convKey  = md5(pathinfo($absOfficePath, PATHINFO_FILENAME) . time());

            // Essayer la conversion avec URL API (évite problèmes d'accès /storage/)
            $tmpDir  = 'tmp-convert';
            $tmpName = uniqid('conv_', true) . '.' . $ext;
            $fileContent = file_get_contents($absOfficePath);
            Storage::disk('public')->put($tmpDir . '/' . $tmpName, $fileContent);
            $fileUrl = $appUrl . '/api/tmp-convert/' . $tmpName;

            $payload = [
                'async' => false,
                'embeddedfonts' => true,
                'filetype' => $ext,
                'key' => $convKey,
                'outputtype' => 'pdf',
                'title' => pathinfo($absOfficePath, PATHINFO_FILENAME) . '.pdf',
                'url' => $fileUrl,
            ];

            Log::info('OnlyOffice conversion request', [
                'fileUrl' => $fileUrl,
                'filetype' => $ext,
                'ooUrl' => $ooUrl,
            ]);

            $headers = ['Authorization: Bearer '];
            if (!empty($ooSecret)) {
                $jwtHeader  = rtrim(strtr(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
                $jwtBody    = rtrim(strtr(base64_encode(json_encode(['payload' => $payload])), '+/', '-_'), '=');
                $jwtSig     = rtrim(strtr(base64_encode(hash_hmac('sha256', "$jwtHeader.$jwtBody", $ooSecret, true)), '+/', '-_'), '=');
                $jwt        = "$jwtHeader.$jwtBody.$jwtSig";
                $headers    = ['Authorization: Bearer ' . $jwt, 'Accept: application/json'];
            }

            $ch = curl_init($ooUrl . '/ConvertService.ashx');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
            ]);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!$body) {
                Log::warning('OnlyOffice conversion response empty', ['filetype' => $ext, 'httpCode' => $httpCode]);
                return null;
            }

            $xml = @simplexml_load_string($body);
            if (!$xml) {
                Log::warning('OnlyOffice conversion response invalid', ['filetype' => $ext, 'body' => substr($body, 0, 500)]);
                return null;
            }

            $error = $xml->Error ?? null;
            if ($error && (int)$error !== 0) {
                Log::warning('OnlyOffice conversion error', [
                    'filetype' => $ext,
                    'error' => (string)$error,
                    'response' => $body,
                ]);
                return null;
            }

            $pdfUrl = (string)($xml->FileUrl ?? null);
            if (!$pdfUrl) {
                Log::warning('OnlyOffice conversion failed - no fileUrl', [
                    'response' => $body,
                    'filetype' => $ext,
                ]);
                return null;
            }

            $pdfContent = @file_get_contents($pdfUrl);
            if (!$pdfContent) {
                $ch2 = curl_init($pdfUrl);
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                ]);
                $pdfContent = curl_exec($ch2);
                curl_close($ch2);
            }

            if (!$pdfContent) {
                Log::warning('OnlyOffice PDF download failed', ['pdfUrl' => $pdfUrl]);
                Storage::disk('public')->delete($tmpDir . '/' . $tmpName);
                return null;
            }

            $localPdf = storage_path('app/tmp/pdf-convert/' . pathinfo($absOfficePath, PATHINFO_FILENAME) . '_oo.pdf');
            @mkdir(dirname($localPdf), 0755, true);
            file_put_contents($localPdf, $pdfContent);

            Storage::disk('public')->delete($tmpDir . '/' . $tmpName);

            return $localPdf;
        } catch (\Throwable $e) {
            Log::warning('Document convertOfficeToPdf failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function isSuspiciousPdf(string $absPdfPath): bool
    {
        if (!file_exists($absPdfPath)) {
            return true;
        }
        $size = @filesize($absPdfPath);
        if ($size === false || $size < 2048) {
            return true;
        }
        $head = @file_get_contents($absPdfPath, false, null, 0, 4096);
        return !is_string($head) || strpos($head, '%PDF-') !== 0;
    }
}
