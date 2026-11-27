<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Signature;
use App\Services\DocumentSignerList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class QrVerificationController extends Controller
{
    private function getSafeDownloadFilename(Document $document, ?string $ext = null): string
    {
        $safeTitle = (string) $document->title;
        $safeTitle = preg_replace('/[\x00-\x1f\x7f\/\\:*?"<>|]+/', ' ', $safeTitle) ?: $safeTitle;
        $safeTitle = preg_replace('/\s+/', ' ', $safeTitle) ?: $safeTitle;
        $safeTitle = substr(trim($safeTitle), 0, 200) ?: 'document';
        $ext = $ext ?: (pathinfo($safeTitle, PATHINFO_EXTENSION) ?: 'pdf');
        return pathinfo($safeTitle, PATHINFO_EXTENSION) === $ext ? $safeTitle : ($safeTitle . '.' . $ext);
    }

    private function normalizePublicStoragePath(string $sourcePath): string
    {
        $path = trim($sourcePath);
        if ($path === '') {
            return '';
        }

        // Si une URL complete est stockee, garder uniquement le chemin.
        if (preg_match('#^https?://#i', $path) === 1) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsed) ? $parsed : '';
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^https?://[^/]+/#i', '', $path) ?? $path;
        $path = preg_replace('#^/?e-administration_laravel/public/storage/#i', '', $path) ?? $path;
        $path = preg_replace('#^/?e-administration_laravel/storage/#i', '', $path) ?? $path;
        $path = preg_replace('#^/?public/storage/#i', '', $path) ?? $path;
        $path = preg_replace('#^/?storage/#i', '', $path) ?? $path;
        $path = preg_replace('#^/?public/#i', '', $path) ?? $path;

        return ltrim($path, '/');
    }

    private function resolveSourceVersionPath(Document $document): ?string
    {
        $versionPath = $document->versions()
            ->orderBy('version')
            ->value('file_path');

        if (!is_string($versionPath) || trim($versionPath) === '') {
            return null;
        }

        return $versionPath;
    }

    private function buildDownloadResponse(Document $document)
    {
        Log::debug('QR download start', [
            'document_id' => $document->id,
            'file_path' => $document->file_path,
            'signed_file_path' => $document->signed_file_path,
            'final_file_path' => $document->final_file_path,
        ]);

        $signedSource = (string) ($document->signed_file_path ?: $document->final_file_path ?: '');
        if ($signedSource !== '') {
            $signedNormalized = $this->normalizePublicStoragePath($signedSource);
            if ($signedNormalized !== '' && Storage::disk('public')->exists($signedNormalized)) {
                $path = $signedNormalized;
                $sourcePath = $signedSource;
                $ext  = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: (pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf');
                $name = request('filename') ?: $this->getSafeDownloadFilename($document, $ext);
                try {
                    $absPath = Storage::disk('public')->path($path);
                    if (is_file($absPath) && is_readable($absPath)) {
                        return response()->download($absPath, $name);
                    }
                } catch (\Throwable $e) {
                    Log::warning('QR signed fast-path failed, fallback to candidate scan', [
                        'document_id' => $document->id,
                        'signed_path' => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $candidateSources = array_values(array_filter([
            (string) ($document->signed_file_path ?? ''),
            (string) ($document->final_file_path ?? ''),
            (string) ($document->file_path ?? ''),
            (string) ($this->resolveSourceVersionPath($document) ?? ''),
        ], fn ($v) => trim($v) !== ''));

        Log::debug('QR candidate sources', [
            'document_id' => $document->id,
            'sources' => $candidateSources,
        ]);

        $sourcePath = '';
        $path = '';

        foreach ($candidateSources as $sourceCandidate) {
            $normalized = $this->normalizePublicStoragePath($sourceCandidate);
            if ($normalized === '') {
                continue;
            }

            Log::debug('QR testing candidate', [
                'document_id' => $document->id,
                'source' => $sourceCandidate,
                'normalized' => $normalized,
            ]);

            $pathsToTry = [$normalized];
            $baseName = basename($normalized);
            if ($baseName !== '' && $baseName !== $normalized) {
                $pathsToTry[] = 'signed_documents/' . $baseName;
                $pathsToTry[] = 'documents/' . $baseName;
                $pathsToTry[] = 'templates/' . $baseName;
            }

            foreach (array_values(array_unique($pathsToTry)) as $pathCandidate) {
                try {
                    $exists = Storage::disk('public')->exists($pathCandidate);
                    Log::debug('QR path check', [
                        'document_id' => $document->id,
                        'path_candidate' => $pathCandidate,
                        'exists' => $exists,
                    ]);

                    if ($exists) {
                        $sourcePath = $sourceCandidate;
                        $path = $pathCandidate;
                        Log::debug('QR path found', [
                            'document_id' => $document->id,
                            'path' => $path,
                        ]);
                        break 2;
                    }
                } catch (\Throwable $e) {
                    Log::error('QR download exists check failed', [
                        'document_id' => $document->id,
                        'source_path' => $sourceCandidate,
                        'normalized_path' => $pathCandidate,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($path === '') {
            $recoveredPath = $this->recoverSignedPathFromStorage($document);
            if ($recoveredPath !== null) {
                $path = $recoveredPath;
                $sourcePath = $recoveredPath;
            }
        }

        if ($path === '') {
            Log::warning('QR download file not found on disk', [
                'document_id' => $document->id,
                'final_file_path' => $document->final_file_path,
                'signed_file_path' => $document->signed_file_path,
                'file_path' => $document->file_path,
            ]);
            abort(404, 'Fichier introuvable');
        }

        $ext  = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: (pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');
        $name = request('filename') ?: $this->getSafeDownloadFilename($document, $ext);
        try {
            // Utiliser le chemin absolu directement pour eviter les erreurs de slash avec Storage::download()
            $absPath = Storage::disk('public')->path($path);
            if (!is_file($absPath) || !is_readable($absPath)) {
                throw new \Exception('File not accessible');
            }
            return response()->download($absPath, $name);
        } catch (\Throwable $e) {
            Log::error('QR download failed', [
                'document_id' => $document->id,
                'source_path' => $sourcePath,
                'normalized_path' => $path,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Fichier introuvable');
        }
    }

    private function recoverSignedPathFromStorage(Document $document): ?string
    {
        try {
            $signedDirAbs = Storage::disk('public')->path('signed_documents');
        } catch (\Throwable $e) {
            Log::warning('QR signed path recovery skipped: path() unsupported', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!is_dir($signedDirAbs)) {
            return null;
        }

        $allSigned = glob(rtrim($signedDirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'signed_*.pdf') ?: [];
        if (empty($allSigned)) {
            return null;
        }

        $sourcePath = $this->normalizePublicStoragePath((string) ($document->file_path ?? ''));
        $sourceStem = pathinfo($sourcePath, PATHINFO_FILENAME);
        $titleStem = pathinfo($this->normalizePublicStoragePath((string) ($document->title ?? '')), PATHINFO_FILENAME);

        $stemsToTry = array_values(array_unique(array_filter([
            $sourceStem,
            $titleStem,
        ], static fn (string $value) => trim($value) !== '')));

        foreach ($stemsToTry as $stem) {
            $matches = glob(rtrim($signedDirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'signed_' . $stem . '_*.pdf') ?: [];
            if (empty($matches)) {
                continue;
            }

            usort($matches, static fn (string $a, string $b) => (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0));
            $best = $matches[0] ?? null;
            if (!$best || !is_file($best) || !is_readable($best)) {
                continue;
            }

            $relative = 'signed_documents/' . basename($best);

            if ((string) ($document->signed_file_path ?? '') !== $relative || (string) ($document->final_file_path ?? '') !== $relative) {
                try {
                    $document->forceFill(['signed_file_path' => $relative, 'final_file_path' => $relative])->save();
                } catch (\Throwable $e) {
                    Log::warning('QR signed path recovery: failed to persist recovered path', [
                        'document_id' => $document->id,
                        'recovered_path' => $relative,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('QR signed path recovered from storage scan', [
                'document_id' => $document->id,
                'recovered_path' => $relative,
                'matched_stem' => $stem,
            ]);

            return $relative;
        }

        if (count($allSigned) === 1) {
            $best = $allSigned[0];
            if ($best && is_file($best) && is_readable($best)) {
                $relative = 'signed_documents/' . basename($best);

                if ((string) ($document->signed_file_path ?? '') !== $relative || (string) ($document->final_file_path ?? '') !== $relative) {
                    try {
                        $document->forceFill(['signed_file_path' => $relative, 'final_file_path' => $relative])->save();
                    } catch (\Throwable $e) {
                        Log::warning('QR signed path recovery: failed to persist recovered path', [
                            'document_id' => $document->id,
                            'recovered_path' => $relative,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                Log::info('QR signed path recovered from singleton storage scan', [
                    'document_id' => $document->id,
                    'recovered_path' => $relative,
                ]);

                return $relative;
            }
        }

        return null;
    }

    /**
     * Téléchargement direct d'un document via son token QR.
     */
    public function downloadByToken(string $token)
    {
        $token = trim($token);
        abort_if($token === '', 404);

        $document = Document::withTrashed()->where('qr_token', $token)->first();

        if (!$document) {
            $signature = Signature::with(['document' => fn ($query) => $query->withTrashed()])->where('qr_code_token', $token)->first();
            $document = $signature?->document;
        }

        abort_if(!$document, 404);

        return $this->buildDownloadResponse($document);
    }

    private function isSigned(Document $document): bool
    {
        return !empty($document->signed_file_path)
            || $document->status === 'signed'
            || (!empty($document->final_file_path)
                && strtolower((string) pathinfo((string) $document->final_file_path, PATHINFO_EXTENSION)) === 'pdf'
                && $document->signed_at !== null);
    }

    /** @return list<array{name: string, signed_at: string|null}> */
    private function signersForJson(Document $document): array
    {
        return array_map(fn (array $signer) => [
            'name'      => $signer['name'],
            'signed_at' => $signer['signed_at']?->format('d/m/Y à H:i'),
        ], app(DocumentSignerList::class)->for($document));
    }

    public function index()
    {
        // Pré-remplir le token depuis l'URL ?token=...
        return view('qr-verification.index', ['token' => request('token', '')]);
    }

    /**
     * Page publique de vérification (accessible sans auth, cible du QR code).
     * Affiche les infos du document + lien de téléchargement de la version signée.
     */
    public function publicLanding(string $token)
    {
        $token = trim($token);
        abort_if($token === '', 404);

        $document  = Document::withTrashed()
            ->with(['owner', 'issuingAdministration'])
            ->where('qr_token', $token)
            ->first();

        if (!$document) {
            $signatureModel = Signature::with(['document' => fn($q) => $q->withTrashed()->with(['owner', 'issuingAdministration'])])
                ->where('qr_code_token', $token)
                ->first();
            $document = $signatureModel?->document;
        }

        abort_if(!$document, 404, 'Document introuvable');

        // Statut de signature
        $isSigned = $this->isSigned($document);

        $signers = $isSigned ? app(DocumentSignerList::class)->for($document) : [];

        $ext         = $isSigned ? 'pdf' : (pathinfo((string) ($document->file_path ?? ''), PATHINFO_EXTENSION) ?: 'pdf');
        $filename    = $this->getSafeDownloadFilename($document, $ext);
        $downloadUrl = route('qr.download', ['token' => $token, 'filename' => $filename]);

        return view('qr-verification.public', compact('document', 'isSigned', 'signers', 'downloadUrl', 'token'));
    }

    /**
     * Vérifier un code QR / token de document généré (AJAX)
     */
    public function verify(Request $request)
    {
        $request->validate([
            'token' => 'required|string|max:255',
        ]);

        $token = trim($request->token);

        // 1) Chercher un document dont le qr_token correspond
        $document = Document::withTrashed()->with(['owner', 'issuingAdministration'])
            ->where('qr_token', $token)
            ->first();

        if ($document) {
            $isSigned = $this->isSigned($document);

            $currentUserId = (string) (auth()->id() ?? '');
            $isOwner = $currentUserId !== '' && (
                (string) $document->owner_id === $currentUserId ||
                (string) ($document->created_by ?? '') === $currentUserId
            );
            $ext = $isSigned ? 'pdf' : (pathinfo((string) $document->file_path, PATHINFO_EXTENSION) ?: 'pdf');
            $filename = $this->getSafeDownloadFilename($document, $ext);
            $downloadUrl = route('qr.download', ['token' => $token, 'filename' => $filename], false);
            return response()->json([
                'valid'           => true,
                'type'            => 'document',
                'is_signed'       => $isSigned,
                'is_owner'        => $isOwner,
                'editor_url'      => $isOwner ? route('documents.index', ['open_oo' => $document->id], false) : null,
                'document'        => [
                    'id'              => $document->id,
                    'title'           => $document->title,
                    'document_number' => $document->document_number,
                    'sub_entity_code' => $document->sub_entity_code,
                    'created_at'      => $document->created_at?->format('d/m/Y à H:i'),
                    'owner'           => $document->owner?->name,
                    'administration'  => $document->issuingAdministration?->name,
                    'signed_at'       => $document->signed_at?->format('d/m/Y à H:i'),
                ],
                'signers'         => $isSigned ? $this->signersForJson($document) : [],
                'signed_at'       => $document->signed_at?->format('d/m/Y à H:i'),
                'download_url'    => $downloadUrl,
                'message'         => $isSigned
                    ? 'Document authentique — version PDF signée électroniquement.'
                    : 'Document authentique — généré par le système (non encore signé).',
            ]);
        }

        // 2) Fallback : chercher une signature QR
        $signature = Signature::with(['document' => fn ($query) => $query->withTrashed()])
            ->where('qr_code_token', $token)
            ->first();

        if ($signature && $signature->document) {
            $sigDoc = $signature->document;
            $ext = 'pdf';
            $filename = $this->getSafeDownloadFilename($sigDoc, $ext);
            $downloadUrl = route('qr.download', ['token' => $token, 'filename' => $filename], false);
            $currentUserId = (string) (auth()->id() ?? '');
            $isOwner = $currentUserId !== '' && (
                (string) $sigDoc->owner_id === $currentUserId ||
                (string) ($sigDoc->created_by ?? '') === $currentUserId
            );
            return response()->json([
                'valid'        => (bool) $signature->is_valid,
                'type'         => 'signature',
                'is_signed'    => true,
                'is_owner'     => $isOwner,
                'editor_url'   => $isOwner ? route('documents.index', ['open_oo' => $sigDoc->id], false) : null,
                'document'     => [
                    'id'              => $sigDoc->id,
                    'title'           => $sigDoc->title,
                    'document_number' => $sigDoc->document_number,
                    'created_at'      => $sigDoc->created_at?->format('d/m/Y à H:i'),
                ],
                'signers'      => $this->signersForJson($sigDoc),
                'signed_at'    => $signature->signed_at?->format('d/m/Y à H:i'),
                'status'       => $signature->status,
                'download_url' => $downloadUrl,
                'message'      => $signature->is_valid ? 'Document signé authentique.' : 'Signature invalide.',
            ]);
        }

        return response()->json([
            'valid'   => false,
            'message' => 'Aucun document trouvé pour ce code QR.',
        ], 404);
    }

    /**
     * Vérifier un numéro de document généré et retourner un lien de visualisation.
     */
    public function verifyNumber(Request $request)
    {
        $request->validate([
            'document_number' => 'required|string|max:255',
        ]);

        $input = trim((string) $request->input('document_number', ''));
        $normalizedInput = strtoupper(str_replace(' ', '', $input));

        $document = Document::withTrashed()->with(['owner', 'issuingAdministration'])
            ->whereNotNull('document_number')
            ->whereRaw('UPPER(REPLACE(document_number, " ", "")) = ?', [$normalizedInput])
            ->first();

        if (!$document) {
            return response()->json([
                'valid' => false,
                'message' => 'Ce document ne fait pas partie des documents générés.',
            ], 404);
        }

        $isSigned = $this->isSigned($document);

        $ext = $isSigned ? 'pdf' : (pathinfo((string) $document->file_path, PATHINFO_EXTENSION) ?: 'pdf');
        $filename = $this->getSafeDownloadFilename($document, $ext);
        $downloadUrl = route('documents.download', [
            'document' => $document->id,
            'inline' => 1,
            'filename' => $filename,
        ]);

        if (!empty((string) $document->qr_token)) {
            $downloadUrl = route('qr.download', ['token' => (string) $document->qr_token, 'filename' => $filename]);
        }

        $currentUserId = (string) (auth()->id() ?? '');
        $isOwner = $currentUserId !== '' && (
            (string) $document->owner_id === $currentUserId ||
            (string) ($document->created_by ?? '') === $currentUserId
        );

        return response()->json([
            'valid' => true,
            'message' => 'Numéro trouvé.',
            'is_signed' => $isSigned,
            'is_owner' => $isOwner,
            'document' => [
                'id' => $document->id,
                'document_number' => $document->document_number,
                'title' => $document->title,
                'created_at' => $document->created_at?->format('d/m/Y à H:i'),
                'owner' => $document->owner?->name,
                'administration' => $document->issuingAdministration?->name,
            ],
            'signers' => $isSigned ? $this->signersForJson($document) : [],
            'signed_at' => $document->signed_at?->format('d/m/Y à H:i'),
            'download_url' => $downloadUrl,
            'preview_url' => $isOwner ? $downloadUrl : null,
            'editor_url' => $isOwner ? route('documents.index', ['open_oo' => $document->id]) : null,
        ]);
    }
}
