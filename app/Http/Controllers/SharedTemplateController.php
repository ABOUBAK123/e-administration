<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Services\Templates\TemplateGenerationCoreService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SharedTemplateController extends Controller
{
    private function allowedSharedTemplateIdsForUser(string $userId): array
    {
        $shareMapRaw = AppSetting::where('key', 'template_share_map')->value('value');
        $shareMap = [];

        if ($shareMapRaw) {
            try {
                $shareMap = json_decode($shareMapRaw, true) ?: [];
            } catch (\Exception $e) {
                $shareMap = [];
            }
        }

        return collect($shareMap)
            ->filter(fn ($users) => in_array($userId, (array) $users, true))
            ->keys()
            ->all();
    }

    /* ══════════════════════════════════════════════════════════
     *  INDEX — liste des templates partagés avec l'utilisateur
     * ══════════════════════════════════════════════════════════ */
    public function index(Request $request)
    {
        $user   = Auth::user();
        $search = $request->get('q', '');

        $allowedIds = $this->allowedSharedTemplateIdsForUser((string) $user->id);

        if (empty($allowedIds)) {
            return view('shared-templates.index', ['templates' => collect(), 'search' => $search]);
        }

        $query = DocumentTemplate::with(['variables', 'administration'])->whereIn('id', $allowedIds);

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $templates = $query->latest()->get();

        // Pour les templates docx sans variables BDD, extraire les {{ }} depuis le XML du fichier
        $templates->each(function ($tpl) {
            $absPath = $tpl->storage_path ? $this->resolveAbsPath($tpl->storage_path) : null;
            if ($tpl->variables->isEmpty() && $absPath && file_exists($absPath)) {
                $ext = strtolower(pathinfo($tpl->storage_path ?: ($tpl->file_name ?? ''), PATHINFO_EXTENSION));
                if (in_array($ext, ['docx', 'xlsx', 'pptx'])) {
                    $tpl->docx_vars = $this->extractVarsFromOfficeFile($absPath);
                } else {
                    $tpl->docx_vars = [];
                }
            } else {
                $tpl->docx_vars = [];
            }
        });

        return view('shared-templates.index', compact('templates', 'search'));
    }

    /* ══════════════════════════════════════════════════════════
     *  GENERATE — génère un document à partir d'un template
     *
     *  Logique identique à l'app Node.js SharedTemplates.tsx :
     *  1. Extraire les [...] du champ `content` (slugifiés)
     *  2. Merger avec les variables BDD (template_variables)
     *  3. Remplacer [original] par la valeur saisie
     *  4. Pour les fichiers Office (docx/xlsx/pptx) : remplacer
     *     aussi dans le XML interne via ZipArchive
     * ══════════════════════════════════════════════════════════ */
    public function generate(Request $request, DocumentTemplate $template)
    {
        $allowedIds = $this->allowedSharedTemplateIdsForUser((string) Auth::id());
        abort_unless(in_array((string) $template->id, array_map('strval', $allowedIds), true), 403);

        $request->validate([
            'values'   => 'nullable|array',
            'values.*' => 'nullable|string|max:5000',
            'output_format' => 'nullable|in:source,pdf',
        ]);

        $coreService = app(TemplateGenerationCoreService::class);
        $template->loadMissing('variables');

        $values = $request->input('values', []);
        $outputFormat = (string) $request->input('output_format', 'pdf');
        $generationWarning = null;

        // Convertir les dates ISO (YYYY-MM-DD) en format français (ex: "29 avril 2026")
        // Les champs <input type="date"> du navigateur renvoient toujours YYYY-MM-DD.
        $values = array_map(function ($val) {
            if (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                try {
                    return \Carbon\Carbon::parse($val)->locale('fr')->isoFormat('D MMMM YYYY');
                } catch (\Throwable $e) {
                    return $val; // garder la valeur originale en cas d'erreur
                }
            }
            return $val;
        }, $values);

        // Livrable A: validation stricte des champs dynamiques requis.
        $coreService->assertRequiredValues($template, $values);

        /* -- Extraction des variables depuis le contenu --------- */
        \Log::info('GENERATE START template=' . $template->id . ' name=' . $template->name);
        \Log::info('GENERATE values_received=' . json_encode($values));
        \Log::info('GENERATE storage_path=' . ($template->storage_path ?: 'NULL'));

        $contentVarMap = $coreService->extractContentVariables($template->content ?? '');

        // Extraire aussi les {{ }} directement du fichier Office
        $ext = strtolower(pathinfo($template->storage_path ?: ($template->file_name ?? ''), PATHINFO_EXTENSION));
        $absTemplatePath = $template->storage_path ? $this->resolveAbsPath($template->storage_path) : null;
        $docxVars = [];
        if (in_array($ext, ['docx', 'xlsx', 'pptx'])
            && $absTemplatePath && file_exists($absTemplatePath))
        {
            $docxVars = $this->extractVarsFromOfficeFile($absTemplatePath);
        }

        /* -- Carte de remplacement : slug => label_original_dans_docx -- */
        $replacements = $coreService->buildReplacementMap($template, $contentVarMap, $docxVars);

        // Sécurité anti-régression : toujours inclure les clés réellement soumises
        // par le formulaire. Cela couvre les cas où la détection interne du template
        // manque une variable (fragmentation XML, historique, variation de label).
        foreach (array_keys($values) as $submittedKey) {
            $submittedKey = trim((string) $submittedKey);
            if ($submittedKey === '' || isset($replacements[$submittedKey])) {
                continue;
            }
            $replacements[$submittedKey] = $submittedKey;
        }

        \Log::info('GENERATE replacements=' . json_encode($replacements));

        // Carte slug => texte EXACT du DOCX (avant slugification + enrichissement IA).
        // Utilisée pour garantir que [nom du demandeur] est remplacé même si le slug
        // stocké en BDD est 'nom_du_demandeur' et le label IA est 'Nom du demandeur'.
        $docxOriginalMap = [];
        foreach ($docxVars as $v) {
            $key   = (string) ($v['key']   ?? '');
            $label = (string) ($v['label'] ?? '');
            if ($key !== '' && $label !== '') {
                $docxOriginalMap[$key] = $label;
            }
        }

        /* -- Remplacement dans le champ content (texte) --------- */
        $content = $template->content ?? '';
        foreach ($replacements as $slug => $original) {
            $val = $values[$slug] ?? '';
            // Supporte les deux syntaxes dans le contenu texte: {{var}} et [var]
            $content = preg_replace(
                '/\{\{\s*' . preg_quote($original, '/') . '\s*\}\}/u',
                $val,
                $content
            );
            $content = preg_replace(
                '/\[' . preg_quote($original, '/') . '\]/u',
                $val,
                $content
            );
            if ($slug !== $original) {
                $content = preg_replace(
                    '/\{\{\s*' . preg_quote($slug, '/') . '\s*\}\}/u',
                    $val,
                    $content
                );
                $content = preg_replace(
                    '/\[' . preg_quote($slug, '/') . '\]/u',
                    $val,
                    $content
                );
            }
            // Texte exact du DOCX (avant slugification/enrichissement IA)
            $docxOrig = $docxOriginalMap[$slug] ?? null;
            if ($docxOrig !== null && $docxOrig !== $original && $docxOrig !== $slug) {
                $content = preg_replace('/\{\{\s*' . preg_quote($docxOrig, '/') . '\s*\}\}/u', $val, $content);
                $content = preg_replace('/\[' . preg_quote($docxOrig, '/') . '\]/u', $val, $content);
            }
            // Slug underscores → espaces ([nom du demandeur] depuis 'nom_du_demandeur')
            $slugSpaces = str_replace('_', ' ', $slug);
            if ($slugSpaces !== $slug && $slugSpaces !== $original && $slugSpaces !== ($docxOrig ?? '')) {
                $content = preg_replace('/\{\{\s*' . preg_quote($slugSpaces, '/') . '\s*\}\}/u', $val, $content);
                $content = preg_replace('/\[' . preg_quote($slugSpaces, '/') . '\]/u', $val, $content);
            }
        }

        /* ══════════════════════════════════════════════════════════
         *  NUMÉROTATION DU DOCUMENT
         *  Source du sub_entity_code : user_direction_assignments
         *  Format : CODE_ADMIN - CODE_ENTITE - 00001 - 2026
         * ══════════════════════════════════════════════════════════ */
        $numbering = $coreService->reserveDocumentNumber($template, Auth::id());
        $docNumber = $numbering['document_number'];
        $subEntityCode = $numbering['sub_entity_code'];
        $issuingAdminId = $numbering['issuing_administration_id'];

        // Fallback: certains templates partages n'ont pas d'administration de delivrance.
        // On conserve la meme codification que le flux standard.
        if (!$docNumber) {
            $currentYear = now()->year;
            $adminCodeFallback = 'ADM';
            $subEntityCode = $subEntityCode ?: 'GEN';

            $counterScope = strtolower(str_replace(' ', '_', (string) $subEntityCode));
            $counterKey = 'doc_counter_shared_' . $counterScope . '_' . $currentYear;

            $seq = DB::transaction(function () use ($counterKey): int {
                $setting = AppSetting::lockForUpdate()->where('key', $counterKey)->first();
                if ($setting) {
                    $next = (int) $setting->value + 1;
                    $setting->update(['value' => (string) $next]);
                    return $next;
                }

                AppSetting::create([
                    'key' => $counterKey,
                    'value' => '1',
                    'description' => 'Compteur documents partages fallback',
                ]);

                return 1;
            });

            $docNumber = sprintf('%s - %s - %05d - %d', $adminCodeFallback, $subEntityCode, $seq, $currentYear);
        }

        /* ══════════════════════════════════════════════════════════
         *  QR CODE — token + URL de vérification
         * ══════════════════════════════════════════════════════════ */
        $qrToken   = Str::random(40);
        $verifyUrl = route('qr.public', ['token' => $qrToken]);
        \Log::info('QR URL générée pour template partagé', ['url' => $verifyUrl]);

        // Position QR prioritaire: paramètre OnlyOffice/API Signature (signature_qr_position)
        // Fallback: anciens paramètres qr_image_* (en mm)
        $qrFromOnlyoffice = false;
        $qrX = 10.0;
        $qrY = 10.0;
        $qrW = 30.0;
        $qrH = 30.0;

        $signatureQrRaw = AppSetting::where('key', 'signature_qr_position')->value('value');
        if ($signatureQrRaw) {
            try {
                $signatureQr = json_decode($signatureQrRaw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($signatureQr)) {
                    $qrX = (float) ($signatureQr['imageX'] ?? $qrX);
                    $qrY = (float) ($signatureQr['imageY'] ?? $qrY);
                    $qrW = (float) ($signatureQr['imageWidth'] ?? $qrW);
                    $qrH = (float) ($signatureQr['imageHeight'] ?? $qrH);
                    $qrFromOnlyoffice = true;
                }
            } catch (\Throwable $e) {
                // fallback sur les paramètres historiques
            }
        }

        if (!$qrFromOnlyoffice) {
            $qrX = (float) (AppSetting::where('key', 'qr_image_x')->value('value') ?? 10);
            $qrY = (float) (AppSetting::where('key', 'qr_image_y')->value('value') ?? 10);
            $qrW = (float) (AppSetting::where('key', 'qr_image_width')->value('value') ?? 30);
            $qrH = (float) (AppSetting::where('key', 'qr_image_height')->value('value') ?? 30);
        }

        // Génération QR PNG dans un fichier temporaire (Dompdf requiert un chemin fichier)
        $qrTempPath = null;
        try {
            $qrResult   = Builder::create()
                ->writer(new PngWriter())
                ->data($verifyUrl)
                ->size(300)
                ->margin(6)
                ->build();
            $qrTempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_' . $qrToken . '.png';
            file_put_contents($qrTempPath, $qrResult->getString());
        } catch (\Throwable $e) {
            \Log::warning('QR generation failed for shared template document', [
                'template_id' => $template->id ?? null,
                'error' => $e->getMessage(),
            ]);
            $qrTempPath = null;
        }

        /* -- Copie + remplacement dans le fichier Office -------- */
        $baseName    = $template->file_name
            ? preg_replace('/\.[^.]+$/', '', $template->file_name)
            : Str::slug($template->name);
        $storagePath = null;
        $sourceStoragePath = null;
        $mimeType    = 'text/plain';
        $ext         = 'txt';

        $absSrcPath = $template->storage_path ? $this->resolveAbsPath($template->storage_path) : null;
        if ($absSrcPath && file_exists($absSrcPath)) {
            $ext      = pathinfo($template->storage_path ?: ($template->file_name ?? 'file.docx'), PATHINFO_EXTENSION) ?: 'docx';
            $destPath = 'documents/' . $baseName . '-' . now()->format('Ymd-His') . '.' . $ext;

            $mimeMap = [
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ];
            $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

            // Copier dans storage/app/public/documents/ pour l'accès web via /storage/
            $absDestPath = Storage::disk('public')->path($destPath);
            if (!is_dir(dirname($absDestPath))) mkdir(dirname($absDestPath), 0755, true);
            copy($absSrcPath, $absDestPath);

            if (in_array($ext, ['docx', 'xlsx', 'pptx'])) {
                $absPath = $absDestPath;

                // Injecter document_number, qr_verify_url, et variables date/responsable automatiques
                $autoValues = $coreService->buildAutoValues(
                    $values,
                    $docNumber,
                    $verifyUrl,
                    Auth::user()->name ?? ''
                );

                \Log::info('GENERATE autoValues=' . json_encode($autoValues));
                \Log::info('GENERATE absSrcPath=' . ($absSrcPath ?: 'NULL') . ' exists=' . (file_exists($absSrcPath) ? 'YES' : 'NO'));
                \Log::info('GENERATE absDestPath=' . $absDestPath . ' exists_after_copy=' . (file_exists($absDestPath) ? 'YES' : 'NO'));

                // Les labels DB ont PRIORITÉ sur les slugs auto (ordre inversé: auto d'abord, DB ensuite)
                // Ex: 'date_du_jour' => 'date du jour' (DB label) écrase 'date_du_jour' => 'date_du_jour' (auto slug)
                $autoReplacements = $coreService->buildAutoReplacements($replacements);
                $this->replaceInOfficeFile($absPath, $autoReplacements, $autoValues, $docxOriginalMap);
                \Log::info('GENERATE replaceInOfficeFile done. docNumber=' . ($docNumber ?? 'NULL') . ' qrTemp=' . ($qrTempPath ?? 'NULL'));

                // Injecter le pied de page Word avec numéro + QR code (docx uniquement)
                if ($ext === 'docx' && $qrTempPath && file_exists($qrTempPath)) {
                    $pageWidthPt  = 595.28; // A4 portrait
                    $pageHeightPt = 841.89; // A4 portrait
                    $mm = 2.8346;

                    if ($qrFromOnlyoffice) {
                        // Coordonnées en points absolus sur A4 (sous-onglet OnlyOffice)
                        $qrWptForDocx = max(20, $qrW);
                        $qrHptForDocx = max(20, $qrH);
                        $qrXptForDocx = $qrX;
                        $qrYptForDocx = $qrY;
                    } else {
                        // Paramètres historiques: marges en mm depuis droite/bas
                        $qrWptForDocx = max(20, $qrW * $mm);
                        $qrHptForDocx = max(20, $qrH * $mm);
                        $qrXptForDocx = $pageWidthPt - ($qrX * $mm) - $qrWptForDocx;
                        $qrYptForDocx = $pageHeightPt - ($qrY * $mm) - $qrHptForDocx;
                    }

                    $this->injectDocxFooterWithQr(
                        $absPath,
                        $docNumber ?? '',
                        $verifyUrl,
                        $qrTempPath,
                        $qrWptForDocx,
                        $qrHptForDocx,
                        $qrXptForDocx,
                        $qrYptForDocx
                    );
                }

                $sourceStoragePath = '/storage/' . $destPath;

                // Les documents Office issus des templates partagés doivent être signés en PDF.
                // On conserve donc la source éditable, mais le document canonique devient le PDF.
                $pdfAbsPath = $this->convertOfficeToPdf($absPath);
                if ($pdfAbsPath && file_exists($pdfAbsPath)) {
                    if ($this->isSuspiciousPdf($pdfAbsPath)) {
                        $storagePath = $sourceStoragePath;
                        $generationWarning = 'La conversion PDF a produit un fichier invalide ou vide. Le document source editable a ete conserve afin d\'eviter un document vierge.';
                        @unlink($pdfAbsPath);
                        \Log::warning('GENERATE suspicious PDF detected, fallback to source', [
                            'template_id' => $template->id ?? null,
                            'pdf_path' => $pdfAbsPath,
                        ]);
                    } else {
                        $pdfDestPath = 'documents/' . pathinfo($destPath, PATHINFO_FILENAME) . '.pdf';
                        Storage::disk('public')->put($pdfDestPath, file_get_contents($pdfAbsPath));

                        $storagePath = '/storage/' . $pdfDestPath;
                        $mimeType = 'application/pdf';
                        $ext = 'pdf';

                        @unlink($pdfAbsPath);

                        if ($outputFormat !== 'pdf') {
                            $generationWarning = 'Le template Office a ete converti automatiquement en PDF afin de permettre le circuit de signature. La source editable a ete conservee dans l\'historique des versions.';
                        }
                    }
                } else {
                    $storagePath = $sourceStoragePath;
                    $generationWarning = 'La conversion PDF n\'a pas pu etre effectuee. Installez LibreOffice sur le serveur pour obtenir un PDF signable depuis les templates Office.';
                }
            }

            // Nettoyage QR temp
            if ($qrTempPath && file_exists($qrTempPath)) {
                @unlink($qrTempPath);
                $qrTempPath = null;
            }

            if (!$storagePath) {
                $storagePath = '/storage/' . $destPath;
            }
        } else {
            // Génération PDF depuis le contenu texte
            if (trim((string) $content) === '') {
                $message = 'Le template ne contient aucun contenu exploitable. Rechargez le fichier source du template ou editez son contenu avant generation.';

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 422);
                }

                return back()->with('error', $message);
            }

            $ext      = 'pdf';
            $mimeType = 'application/pdf';
            $destPath = 'documents/' . $baseName . '-' . now()->format('Ymd-His') . '.pdf';

            $htmlContent = nl2br(e($content));

            // HTML simple — sans position:fixed (non supporté par Dompdf)
            // Le numéro + footer sont injectés via canvas après le rendu
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body        { font-family: DejaVu Sans, sans-serif; font-size: 12pt; line-height: 1.6;
                color: #1a1a1a; margin: 40pt 40pt 60pt 40pt; }
  h1          { font-size: 16pt; color: #2453d6; border-bottom: 2pt solid #2453d6;
                padding-bottom: 6pt; margin-bottom: 16pt; }
  .meta       { color: #888; font-size: 9pt; margin-bottom: 24pt; }
  .docnum     { font-size: 9pt; font-weight: bold; color: #2453d6; margin-bottom: 4pt; }
  .content    { font-size: 11pt; }
</style></head><body>
<h1>' . e($template->name) . '</h1>
' . ($docNumber ? '<div class="docnum">N&#176; : ' . e($docNumber) . '</div>' : '') . '
<div class="meta">G&#233;n&#233;r&#233; le ' . now()->format('d/m/Y \à H:i') . '</div>
<div class="content">' . $htmlContent . '</div>
</body></html>';

            $options = new Options();
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'dejavu sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // ── Injection du pied de page + QR via canvas Dompdf ───────────
            // A4 portrait : 595.28 x 841.89 points (1mm = 2.8346pt)
            $canvas  = $dompdf->getCanvas();
            $pw      = $canvas->get_width();   // ~595
            $ph      = $canvas->get_height();  // ~842
            $mm      = 2.8346;

            if ($qrFromOnlyoffice) {
                // Paramétrage OnlyOffice/API signature: coordonnées en points depuis le coin haut-gauche
                $qrWpt  = max(20, $qrW);
                $qrHpt  = max(20, $qrH);
                $qrXpt  = $qrX;
                $qrYpt  = $qrY;
            } else {
                // Paramétrage historique: marges en mm depuis droite/bas
                $qrWpt  = $qrW * $mm;
                $qrHpt  = $qrH * $mm;
                $qrXpt  = $pw - ($qrX * $mm) - $qrWpt;
                $qrYpt  = $ph - ($qrY * $mm) - $qrHpt;
            }

            // Clamp pour garantir que le QR reste visible dans la page
            $qrXpt = max(0, min($pw - $qrWpt, $qrXpt));
            $qrYpt = max(0, min($ph - $qrHpt, $qrYpt));

            $tmpQr   = $qrTempPath;
            $docNum  = $docNumber;
            $genDate = now()->format('d/m/Y H:i');

            $canvas->page_script(
                function (int $pageNumber, int $pageCount, $canvas, $fontMetrics)
                    use ($tmpQr, $qrXpt, $qrYpt, $qrWpt, $qrHpt, $ph, $pw, $docNum, $genDate)
                {
                    $fontNormal = $fontMetrics->getFont('DejaVu Sans', 'normal');
                    $gray  = [0.55, 0.55, 0.55];
                    $blue  = [0.14, 0.32, 0.84];

                    // Ligne de séparation du pied de page
                    $footerY = $ph - 40;
                    $canvas->line(28, $footerY, $pw - 28, $footerY, [0.8, 0.8, 0.8], 0.5);

                    // Numéro de document (gauche)
                    if ($docNum) {
                        $canvas->text(28, $footerY + 5, 'N\u00b0 : ' . $docNum, $fontNormal, 7.5, $blue);
                    }

                    // Texte vérification (gauche, 2e ligne)
                    $canvas->text(28, $footerY + 16, 'Authenticit\u00e9 v\u00e9rifiable par scan du QR code', $fontNormal, 7, $gray);

                    // Numéro de page (droite)
                    $pageTxt = 'Page ' . $pageNumber . ' / ' . $pageCount;
                    $tw = $fontMetrics->getTextWidth($pageTxt, $fontNormal, 7);
                    $canvas->text($pw - 28 - $tw, $footerY + 16, $pageTxt, $fontNormal, 7, $gray);

                    // QR code image
                    if ($tmpQr && file_exists($tmpQr)) {
                        $canvas->image($tmpQr, $qrXpt, $qrYpt, $qrWpt, $qrHpt);
                    }
                }
            );

            $pdfContent = $dompdf->output();

            // Nettoyage du fichier QR temporaire
            if ($qrTempPath && file_exists($qrTempPath)) {
                @unlink($qrTempPath);
                $qrTempPath = null;
            }

            Storage::disk('public')->put($destPath, $pdfContent);
            $storagePath = '/storage/' . $destPath;
        }

        /* -- Création en base ----------------------------------- */
        $fileName = $baseName . '-' . now()->format('Ymd-His') . '.' . $ext;
        $docId    = (string) Str::uuid();
        $title    = ($docNumber ? '[' . $docNumber . '] ' : '') . $template->name . ' — ' . now()->format('d/m/Y H:i');

        $description = 'Généré depuis : ' . $template->name;
        if ($sourceStoragePath && $sourceStoragePath !== $storagePath) {
            $description .= ' (source editable conservee dans les versions)';
        }

        $document = Document::create([
            'id'                     => $docId,
            'title'                  => $title,
            'description'            => $description,
            'file_path'              => $storagePath,
            'final_file_path'        => $storagePath,
            'file_size'              => $storagePath ? Storage::disk('public')->size(ltrim(str_replace('/storage/', '', $storagePath), '/')) : 0,
            'mime_type'              => $mimeType,
            'status'                 => 'active',
            'owner_id'               => Auth::id(),
            'created_by'             => Auth::id(),
            'document_number'        => $docNumber,
            'sub_entity_code'        => $subEntityCode,
            'qr_token'               => $qrToken,
            'issuing_administration_id' => $issuingAdminId,
        ]);

        if ($sourceStoragePath && $sourceStoragePath !== $storagePath) {
            try {
                DocumentVersion::create([
                    'document_id' => $docId,
                    'version'     => 1,
                    'file_path'   => $sourceStoragePath,
                    'creator_id'  => Auth::id(),
                    'change_log'  => 'Source editable generee depuis template : ' . $template->name,
                ]);
                \Log::info('GENERATE DocumentVersion v1 created', ['doc_id' => $docId, 'path' => $sourceStoragePath]);
            } catch (\Throwable $e) {
                \Log::error('GENERATE DocumentVersion v1 failed', ['doc_id' => $docId, 'error' => $e->getMessage()]);
            }

            try {
                DocumentVersion::create([
                    'document_id' => $docId,
                    'version'     => 2,
                    'file_path'   => $storagePath,
                    'creator_id'  => Auth::id(),
                    'change_log'  => 'Version PDF signable generee depuis template : ' . $template->name,
                ]);
                \Log::info('GENERATE DocumentVersion v2 created', ['doc_id' => $docId, 'path' => $storagePath]);
            } catch (\Throwable $e) {
                \Log::error('GENERATE DocumentVersion v2 failed', ['doc_id' => $docId, 'error' => $e->getMessage()]);
            }
        } else {
            try {
                DocumentVersion::create([
                    'document_id' => $docId,
                    'version'     => 1,
                    'file_path'   => $storagePath,
                    'creator_id'  => Auth::id(),
                    'change_log'  => 'Génération depuis template : ' . $template->name,
                ]);
                \Log::info('GENERATE DocumentVersion v1 created (single)', ['doc_id' => $docId, 'path' => $storagePath]);
            } catch (\Throwable $e) {
                \Log::error('GENERATE DocumentVersion v1 (single) failed', ['doc_id' => $docId, 'error' => $e->getMessage()]);
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'           => true,
                'document_id'       => $docId,
                'title'             => $title,
                'document_number'   => $docNumber,
                'qr_token'          => $qrToken,
                'verify_url'        => $verifyUrl,
                'file_path'         => $storagePath,
                'source_file_path'  => $sourceStoragePath,
                'generated_content' => $content,
                'message'           => 'Document généré et enregistré dans Mes Documents.',
                'warning'           => $generationWarning,
            ]);
        }

        return redirect()->route('documents.index')->with('success', 'Document généré avec succès !');
    }

    /* ══════════════════════════════════════════════════════════
     *  HELPERS PRIVÉS
     * ══════════════════════════════════════════════════════════ */

    /**
     * Résout le chemin absolu d'un fichier template quel que soit son emplacement.
     * - "images/templates/xxx.docx" → public_path("images/templates/xxx.docx")
     * - "documents/xxx.docx"        → storage/app/public/documents/xxx.docx
     * - "templates/xxx.docx"        → storage/app/public/templates/xxx.docx
     */
    private function resolveAbsPath(string $storagePath): string
    {
        if (str_starts_with($storagePath, 'images/')) {
            return public_path($storagePath);
        }
        return Storage::disk('public')->path($storagePath);
    }

    /**
     * Lit une valeur entière depuis AppSetting avec fallback et bornes de sécurité.
     */
    private function getIntAppSetting(string $key, int $default, int $min, int $max): int
    {
        try {
            $raw = AppSetting::where('key', $key)->value('value');
            if ($raw === null || $raw === '') {
                return $default;
            }

            if (!is_numeric($raw)) {
                return $default;
            }

            $value = (int) $raw;
            if ($value < $min || $value > $max) {
                return $default;
            }

            return $value;
        } catch (\Throwable $e) {
            \Log::warning('getIntAppSetting fallback used', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    /**
     * Tente la conversion d'un fichier Office (docx/xlsx/pptx) en PDF via LibreOffice.
     * Retourne le chemin absolu du PDF généré, ou null si échec / binaire absent.
     */
    /**
     * Convertit un fichier Office (docx/xlsx/pptx) en PDF via l'API OnlyOffice ConvertService.
     * Retourne le chemin absolu local du PDF produit, ou null en cas d'échec.
     */
    private function convertOfficeToPdf(string $absOfficePath): ?string
    {
        // ── 1. Essai LibreOffice local si disponible ──────────────────────────
        foreach (['soffice', 'libreoffice'] as $bin) {
            $which = null;
            @exec('where ' . $bin . ' 2>NUL', $out, $rc);   // Windows
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
                    $pdfFile = $tmpOutDir . DIRECTORY_SEPARATOR
                        . pathinfo($absOfficePath, PATHINFO_FILENAME) . '.pdf';
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

        // ── 2. Fallback : API OnlyOffice ConvertService ───────────────────────
        try {
            $ooUrl    = rtrim((string) \App\Models\AppSetting::where('key', 'onlyoffice_server_url')->value('value'), '/');
            $ooSecret = (string) \App\Models\AppSetting::where('key', 'onlyoffice_secret')->value('value');
            $appUrl   = rtrim((string) \App\Models\AppSetting::where('key', 'app_public_url')->value('value'), '/');

            if (empty($ooUrl)) {
                return null; // OnlyOffice non configuré
            }

            // Publier le fichier source temporairement dans storage/public/tmp-convert/
            $tmpDir  = 'tmp-convert';
            $tmpName = uniqid('conv_', true) . '.' . pathinfo($absOfficePath, PATHINFO_EXTENSION);
            \Illuminate\Support\Facades\Storage::disk('public')->put($tmpDir . '/' . $tmpName, file_get_contents($absOfficePath));
            $fileUrl = $appUrl . '/storage/' . $tmpDir . '/' . $tmpName;

            $ext      = strtolower(pathinfo($absOfficePath, PATHINFO_EXTENSION));
            $convKey  = md5($tmpName . time());

            $payload = [
                'async'        => false,
                'embeddedfonts'=> true,
                'filetype'     => $ext,
                'key'          => $convKey,
                'outputtype'   => 'pdf',
                'title'        => pathinfo($absOfficePath, PATHINFO_FILENAME) . '.pdf',
                'url'          => $fileUrl,
            ];

            // Générer le JWT si secret configuré
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
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);

            // Supprimer le fichier source temporaire
            \Illuminate\Support\Facades\Storage::disk('public')->delete($tmpDir . '/' . $tmpName);

            if (!$body) {
                return null;
            }

            $json = json_decode($body, true);
            $pdfUrl = $json['fileUrl'] ?? null;

            if (!$pdfUrl) {
                return null;
            }

            // Télécharger le PDF converti
            $pdfContent = @file_get_contents($pdfUrl);
            if (!$pdfContent) {
                // Essai avec curl (si SSL ou auth nécessaire)
                $ch2 = curl_init($pdfUrl);
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $pdfContent = curl_exec($ch2);
                curl_close($ch2);
            }

            if (!$pdfContent) {
                return null;
            }

            $localPdf = storage_path('app/tmp/pdf-convert/'
                . pathinfo($absOfficePath, PATHINFO_FILENAME) . '_oo.pdf');
            @mkdir(dirname($localPdf), 0755, true);
            file_put_contents($localPdf, $pdfContent);

            return $localPdf;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('convertOfficeToPdf (OO fallback) failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Detecte les PDF tres probablement invalides/vides produits par un convertisseur.
     */
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
        if (!is_string($head) || strpos($head, '%PDF-') !== 0) {
            return true;
        }

        return false;
    }

    /**
     * Extrait les variables {{ }} depuis le XML interne d'un fichier Office (docx/xlsx/pptx).
     * Retourne un tableau [ ['key' => slug, 'label' => originalName], ... ]
     */
    private function extractVarsFromOfficeFile(string $absFilePath): array
    {
        if (!class_exists('ZipArchive') || !file_exists($absFilePath)) return [];

        $zip = new \ZipArchive();
        if ($zip->open($absFilePath) !== true) return [];

        $found = []; // slug => original
        $numFiles = $zip->numFiles;

        for ($i = 0; $i < $numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!preg_match('/\.xml$/i', $name)) continue;
            if (preg_match('#\[Content_Types\]|_rels/#', $name)) continue;

            $xml = $zip->getFromIndex($i);
            if ($xml === false) continue;

            // Défragmenter les runs Word avant extraction (cas fréquent OnlyOffice)
            $isWordContent = preg_match('#word/(document|header|footer|endnote|footnote)#i', $name);
            $normalizedXml = $isWordContent ? $this->defragmentRuns($xml) : $xml;

            // Supprimer les balises XML + décoder les entités pour retrouver {{ }}
            $text = html_entity_decode(strip_tags($normalizedXml), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Support des deux syntaxes : {{variable}} (ancien) et [variable] (nouveau)
            preg_match_all('/(?:\{\s*\{)\s*([^{}]+?)\s*(?:\}\s*\})/u', $text, $m1);
            preg_match_all('/\[([^\[\]]+?)\]/u', $text, $m2);
            foreach (array_merge($m1[1], $m2[1]) as $original) {
                $original = trim($original);
                if (!$original) continue;
                $slug = $this->slugify($original);
                if ($slug && !isset($found[$slug])) {
                    $found[$slug] = $original;
                }
            }
        }

        $zip->close();

        $result = [];
        foreach ($found as $slug => $original) {
            $result[] = [
                'key'         => $slug,
                'label'       => $original,
                'field_type'  => 'text',
                'required'    => false,
                'placeholder' => '',
                'default_value' => '',
                'options'     => [],
            ];
        }
        return $result;
    }

    /**
     * Slugifie un nom de variable — MÊME logique que le JS de l'app Node.js :
     *
     *   slugify("N'DJOMON Ohouo Landry Marius")
     *   => "n_djomon_ohouo_landry_marius"
     *
     * Étapes : translittération ASCII → minuscules → ' → _ → non-alnum → _ → trim _
     */
    private function slugify(string $text): string
    {
        // Translittération (supprime accents, ligatures…)
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text  = ($ascii !== false && $ascii !== '') ? $ascii : $text;


        $text = strtolower($text);
        $text = str_replace("'", '_', $text);          // apostrophe → _
        $text = preg_replace('/[^a-z0-9]+/', '_', $text); // tout le reste → _
        $text = trim($text, '_');

        return $text ?: 'var';
    }

    /**
     * Extrait toutes les variables [...] d'un contenu texte.
     * Retourne un tableau [ slug => originalName ] (dédupliqué, ordre de première apparition).
     *
     * Exemple : "Bonjour [N'DJOMON Landry], le [DATE]."
     *   => ['n_djomon_landry' => "N'DJOMON Landry", 'date' => 'DATE']
     */
    private function extractContentVars(string $content): array
    {
        if (!$content) return [];

        // Support des deux syntaxes : {{variable}} (ancien) et [variable] (nouveau)
        preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $content, $m1);
        preg_match_all('/\[([^\[\]]+?)\]/', $content, $m2);

        $vars = [];
        foreach (array_merge($m1[1], $m2[1]) as $match) {
            $original = trim($match);
            if ($original === '') continue;
            $slug     = $this->slugify($original);
            if (!isset($vars[$slug])) {
                $vars[$slug] = $original;
            }
        }
        return $vars;
    }

    /**
     * Injecte un pied de page dans un fichier .docx avec :
     * - Le numéro de document (texte gauche)
     * - Le QR code (image droite, 2cm x 2cm)
     * - L'URL de vérification (texte centré)
     *
     * Fonctionne en manipulant le ZIP du docx directement.
     * N'écrase pas un footer existant — ajoute un nouveau footer "default".
     */
    private function injectDocxFooterWithQr(
        string $absFilePath,
        string $docNumber,
        string $verifyUrl,
        string $qrPngPath,
        float $qrWidthPt = 56.7,
        float $qrHeightPt = 56.7,
        ?float $qrXpt = null,
        ?float $qrYpt = null
    ): void
    {
        if (!class_exists('ZipArchive') || !file_exists($qrPngPath)) return;

        $zip = new \ZipArchive();
        if ($zip->open($absFilePath) !== true) return;

        // Lire les fichiers existants
        $docXml       = $zip->getFromName('word/document.xml');
        $contentTypes = $zip->getFromName('[Content_Types].xml');

        if ($docXml === false || $contentTypes === false) {
            $zip->close();
            return;
        }

        // Créer un _rels minimal si absent (DOCX simple sans relations)
        $docRelsXml = $zip->getFromName('word/_rels/document.xml.rels');
        if ($docRelsXml === false) {
            $docRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '</Relationships>';
        }

        // Ajouter un <w:sectPr> minimal si absent (requis pour la référence footer)
        if (strpos($docXml, '<w:sectPr') === false) {
            $docXml = str_replace('</w:body>', '<w:sectPr/></w:body>', $docXml);
        }

        $qrPngBytes  = file_get_contents($qrPngPath);
        $footerRelId = 'rIdFtrE-Admin1';
        $imgRelId    = 'rIdQrFtrImg1';
        $footerFile  = 'word/footer_eadmin.xml';
        $footerRels  = 'word/_rels/footer_eadmin.xml.rels';
        $mediaFile   = 'word/media/qr_eadmin.png';

        // 1. Ajouter l'image QR dans le ZIP
        $zip->addFromString($mediaFile, $qrPngBytes);

        // 2. Créer le fichier de relations du footer
        $footerRelsContent = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="' . $imgRelId . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
            . ' Target="media/qr_eadmin.png"/>'
            . '</Relationships>';
        $zip->addFromString($footerRels, $footerRelsContent);

        // 3. Construire le footer XML Word
        // Taille image en EMU (1pt = 12700 EMU)
        $cx  = (int) max(12700, round($qrWidthPt * 12700));
        $cy  = (int) max(12700, round($qrHeightPt * 12700));
        $num = htmlspecialchars($docNumber, ENT_XML1, 'UTF-8');
        $url = htmlspecialchars($verifyUrl,  ENT_XML1, 'UTF-8');

        // Par défaut, conserver le mode historique (inline dans la ligne de footer).
        // Si des coordonnées X/Y sont fournies, utiliser un ancrage absolu sur la page.
        if ($qrXpt !== null && $qrYpt !== null) {
            $pageWidthPt  = 595.28; // A4 portrait
            $pageHeightPt = 841.89; // A4 portrait

            $xPt = max(0, min($pageWidthPt  - $qrWidthPt,  $qrXpt));
            $yPt = max(0, min($pageHeightPt - $qrHeightPt, $qrYpt));

            $xEmu = (int) round($xPt * 12700);
            $yEmu = (int) round($yPt * 12700);

            $qrDrawingXml =
                '<w:r><w:rPr/>' .
                '<w:drawing>' .
                '<wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="251659264" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">' .
                '<wp:simplePos x="0" y="0"/>' .
                '<wp:positionH relativeFrom="page"><wp:posOffset>' . $xEmu . '</wp:posOffset></wp:positionH>' .
                '<wp:positionV relativeFrom="page"><wp:posOffset>' . $yEmu . '</wp:posOffset></wp:positionV>' .
                '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>' .
                '<wp:effectExtent l="0" t="0" r="0" b="0"/>' .
                '<wp:wrapNone/>' .
                '<wp:docPr id="101" name="QR-eAdmin"/>' .
                '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>' .
                '<a:graphic>' .
                '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">' .
                '<pic:pic>' .
                '<pic:nvPicPr>' .
                '<pic:cNvPr id="0" name="QR-eAdmin"/>' .
                '<pic:cNvPicPr><a:picLocks noChangeAspect="1"/></pic:cNvPicPr>' .
                '</pic:nvPicPr>' .
                '<pic:blipFill>' .
                '<a:blip r:embed="' . $imgRelId . '"/>' .
                '<a:stretch><a:fillRect/></a:stretch>' .
                '</pic:blipFill>' .
                '<pic:spPr>' .
                '<a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>' .
                '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>' .
                '</pic:spPr>' .
                '</pic:pic>' .
                '</a:graphicData>' .
                '</a:graphic>' .
                '</wp:anchor>' .
                '</w:drawing>' .
                '</w:r>';
        } else {
            $qrDrawingXml =
                '<w:r><w:rPr/>' .
                '<w:drawing>' .
                '<wp:inline distT="0" distB="0" distL="0" distR="0">' .
                '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>' .
                '<wp:effectExtent l="0" t="0" r="0" b="0"/>' .
                '<wp:docPr id="101" name="QR-eAdmin"/>' .
                '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>' .
                '<a:graphic>' .
                '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">' .
                '<pic:pic>' .
                '<pic:nvPicPr>' .
                '<pic:cNvPr id="0" name="QR-eAdmin"/>' .
                '<pic:cNvPicPr><a:picLocks noChangeAspect="1"/></pic:cNvPicPr>' .
                '</pic:nvPicPr>' .
                '<pic:blipFill>' .
                '<a:blip r:embed="' . $imgRelId . '"/>' .
                '<a:stretch><a:fillRect/></a:stretch>' .
                '</pic:blipFill>' .
                '<pic:spPr>' .
                '<a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>' .
                '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>' .
                '</pic:spPr>' .
                '</pic:pic>' .
                '</a:graphicData>' .
                '</a:graphic>' .
                '</wp:inline>' .
                '</w:drawing>' .
                '</w:r>';
        }

        $footerXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'

            // Ligne séparatrice (bordure haut du paragraphe)
            . '<w:p>'
            . '<w:pPr>'
            . '<w:pBdr><w:top w:val="single" w:sz="4" w:space="1" w:color="CCCCCC"/></w:pBdr>'
            . '<w:tabs><w:tab w:val="center" w:pos="4680"/><w:tab w:val="right" w:pos="9360"/></w:tabs>'
            . '</w:pPr>'
            // Numéro (gauche)
            . '<w:r><w:rPr><w:sz w:val="16"/><w:color w:val="2453D6"/><w:b/></w:rPr>'
            . '<w:t xml:space="preserve">N\xc2\xb0\xc2\xa0: ' . $num . '</w:t></w:r>'
            // Tab → centre
            . '<w:r><w:tab/></w:r>'
            // Texte vérification (centre)
            . '<w:r><w:rPr><w:sz w:val="14"/><w:color w:val="888888"/></w:rPr>'
            . '<w:t>Authenticit\xc3\xa9 v\xc3\xa9rifiable par QR code</w:t></w:r>'
            // Tab → droite
            . '<w:r><w:tab/></w:r>'
            // QR code image (inline historique ou anchor absolu)
            . $qrDrawingXml
            . '</w:p>'
            . '</w:ftr>';

        $zip->addFromString($footerFile, $footerXml);

        // 4. Ajouter la relation footer dans document.xml.rels
        $docRelsXml = str_replace(
            '</Relationships>',
            '<Relationship Id="' . $footerRelId . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer"'
            . ' Target="footer_eadmin.xml"/>'
            . '</Relationships>',
            $docRelsXml
        );
        $zip->addFromString('word/_rels/document.xml.rels', $docRelsXml);

        // 5. Ajouter la référence footer dans sectPr du document.xml
        // IMPORTANT: injecter dans le DERNIER <w:sectPr> (le sectPr principal du corps du document,
        // pas les sectPr des sauts de section à l'intérieur du texte).
        $footerRef = '<w:footerReference w:type="default" r:id="' . $footerRelId . '"/>';
        if (strpos($docXml, 'w:footerReference') === false) {
            // Trouver la DERNIÈRE occurrence de </w:sectPr> ou <w:sectPr ... />
            $lastClose = strrpos($docXml, '</w:sectPr>');
            if ($lastClose !== false) {
                // Insérer footerRef juste avant le dernier </w:sectPr>
                $docXml = substr($docXml, 0, $lastClose) . $footerRef . substr($docXml, $lastClose);
            } else {
                // Pas de </w:sectPr> : chercher un self-closing <w:sectPr ... /> et l'expanser
                $expanded = preg_replace('/<w:sectPr([^>]*)\/>/s', '<w:sectPr$1>' . $footerRef . '</w:sectPr>', $docXml, 1, $cnt);
                if ($cnt > 0 && is_string($expanded)) {
                    $docXml = $expanded;
                } else {
                    // Dernier recours : insérer avant </w:body>
                    $docXml = str_replace('</w:body>', '<w:sectPr>' . $footerRef . '</w:sectPr></w:body>', $docXml);
                }
            }
        } else {
            // Remplacer le footer default existant pour garantir l'affichage du QR
            // → remplacer dans le dernier footerReference default
            $pattern = '/<w:footerReference\s+[^>]*w:type="default"[^>]*\/>/';
            preg_match_all($pattern, $docXml, $allMatches, PREG_OFFSET_CAPTURE);
            if (!empty($allMatches[0])) {
                $last = end($allMatches[0]);
                $docXml = substr($docXml, 0, $last[1]) . $footerRef . substr($docXml, $last[1] + strlen($last[0]));
            } else {
                // Injecter avant le dernier </w:sectPr>
                $lastClose = strrpos($docXml, '</w:sectPr>');
                if ($lastClose !== false) {
                    $docXml = substr($docXml, 0, $lastClose) . $footerRef . substr($docXml, $lastClose);
                }
            }
        }
        $zip->addFromString('word/document.xml', $docXml);

        // 6. Déclarer le footer dans [Content_Types].xml
        if (strpos($contentTypes, 'footer_eadmin.xml') === false) {
            $contentTypes = str_replace(
                '</Types>',
                '<Override PartName="/word/footer_eadmin.xml"'
                . ' ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
                . '</Types>',
                $contentTypes
            );
            $zip->addFromString('[Content_Types].xml', $contentTypes);
        }

        $zip->close();
    }

    /**
     * Remplace les [...] dans le XML interne d'un fichier Office (docx/xlsx/pptx).
     * Les fichiers Office sont des archives ZIP contenant des fichiers XML.
     *
     * IMPORTANT : Dans Word, une variable comme [NOM] peut être fragmentée en
     * plusieurs "runs" XML (<w:r>). Ce code gère le cas simple où le placeholder
     * est entier dans un seul run. Pour les cas complexes, OnlyOffice garantit
     * l'intégrité des runs lors de la saisie directe.
     */
    private function replaceInOfficeFile(string $absFilePath, array $replacements, array $values, array $docxOriginalMap = []): void
    {
        if (!class_exists('ZipArchive')) return;

        \Log::info('replaceInOfficeFile START file=' . $absFilePath . ' exists=' . (file_exists($absFilePath) ? 'YES' : 'NO'));
        \Log::info('replaceInOfficeFile replacements_count=' . count($replacements) . ' values_count=' . count($values));

        $zip = new \ZipArchive();
        if ($zip->open($absFilePath, \ZipArchive::CREATE) !== true) {
            \Log::error('replaceInOfficeFile FAILED to open ZIP: ' . $absFilePath);
            return;
        }

        $numFiles = $zip->numFiles;
        $toUpdate = [];
        $canonicalValueMap = $this->buildCanonicalValueMap($replacements, $values, $docxOriginalMap);

        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            if (!preg_match('/\.xml$/i', $name)) continue;
            if (preg_match('#\[Content_Types\]|_rels/#', $name)) continue;

            $xmlContent = $zip->getFromIndex($i);
            if ($xmlContent === false) continue;

            // ── ÉTAPE 1 : défragmenter les runs dans chaque paragraphe ──────
            // Appliquer sur tout fichier XML Word pouvant contenir {{ }} :
            // document.xml, header1.xml, footer1.xml, etc.
            // On évite les fichiers de styles/settings/relations qui n'ont pas de runs.
            $isWordContent = preg_match('#word/(document|header|footer|endnote|footnote)#i', $name);
            if ($isWordContent) {
                $newContent = $this->defragmentRuns($xmlContent, $name);
                if (!is_string($newContent) || $newContent === '') {
                    \Log::warning('replaceInOfficeFile defragmentRuns returned invalid content, fallback to original XML', [
                        'file' => $name,
                        'preg_error' => function_exists('preg_last_error_msg') ? preg_last_error_msg() : preg_last_error(),
                    ]);
                    $newContent = $xmlContent;
                }
            } else {
                $newContent = $xmlContent;
            }

            // ── ÉTAPE 2 : remplacer [variable] ET {{variable}} dans le XML défragmenté ──
            foreach ($replacements as $slug => $original) {
                $val = htmlspecialchars($values[$slug] ?? '', ENT_XML1, 'UTF-8');

                // Syntaxe nouvelle : [original]  — insensible à la casse (iu)
                $newContent = $this->safePregReplace(
                    '/\[' . preg_quote($original, '/') . '\]/iu',
                    $val,
                    $newContent,
                    'replaceInOfficeFile:[' . $name . '] original=' . $original
                );
                // Syntaxe ancienne : {{original}} — insensible à la casse (iu)
                $newContent = $this->safePregReplace(
                    '/\{\{\s*' . preg_quote($original, '/') . '\s*\}\}/iu',
                    $val,
                    $newContent,
                    'replaceInOfficeFile:[' . $name . '] original_curly=' . $original
                );
                if ($slug !== $original) {
                    // Syntaxe nouvelle avec slug : [slug] — insensible à la casse (iu)
                    $newContent = $this->safePregReplace(
                        '/\[' . preg_quote($slug, '/') . '\]/iu',
                        $val,
                        $newContent,
                        'replaceInOfficeFile:[' . $name . '] slug=' . $slug
                    );
                    // Syntaxe ancienne avec slug : {{slug}} — insensible à la casse (iu)
                    $newContent = $this->safePregReplace(
                        '/\{\{\s*' . preg_quote($slug, '/') . '\s*\}\}/iu',
                        $val,
                        $newContent,
                        'replaceInOfficeFile:[' . $name . '] slug_curly=' . $slug
                    );
                }

                // ── Texte EXACT du DOCX (avant slugification) ─────────────
                // Ex : slug='nom_du_demandeur', docxOrig='nom du demandeur'
                //      label IA='Nom du demandeur' → aucune des deux ne correspond.
                // On essaie donc aussi le texte exact extrait du fichier.
                $docxOrig = $docxOriginalMap[$slug] ?? null;
                if ($docxOrig !== null && $docxOrig !== $original && $docxOrig !== $slug) {
                    $newContent = $this->safePregReplace(
                        '/\[' . preg_quote($docxOrig, '/') . '\]/iu',
                        $val,
                        $newContent,
                        'replaceInOfficeFile:[' . $name . '] docx_orig=' . $docxOrig
                    );
                    $newContent = $this->safePregReplace(
                        '/\{\{\s*' . preg_quote($docxOrig, '/') . '\s*\}\}/iu',
                        $val,
                        $newContent,
                        'replaceInOfficeFile:[' . $name . '] docx_orig_curly=' . $docxOrig
                    );
                }

                // ── Slug avec underscores → espaces ───────────────────────
                // Couvre [nom du demandeur] depuis le slug 'nom_du_demandeur'.
                $slugSpaces = str_replace('_', ' ', $slug);
                if ($slugSpaces !== $slug && $slugSpaces !== $original && $slugSpaces !== ($docxOrig ?? '')) {
                    $newContent = $this->safePregReplace(
                        '/\[' . preg_quote($slugSpaces, '/') . '\]/iu',
                        $val,
                        $newContent,
                        'replaceInOfficeFile:[' . $name . '] slug_spaces=' . $slugSpaces
                    );
                    $newContent = $this->safePregReplace(
                        '/\{\{\s*' . preg_quote($slugSpaces, '/') . '\s*\}\}/iu',
                        $val,
                        $newContent,
                        'replaceInOfficeFile:[' . $name . '] slug_spaces_curly=' . $slugSpaces
                    );
                }

                // ── Matching tolérant (accents/apostrophes/espaces) ───────
                // Couvre les variantes fréquentes de templates Word:
                // - universite / université
                // - l universite / l'université / l’université
                // - séparateurs multiples (espaces, _, -)
                $candidates = array_values(array_unique(array_filter([
                    (string) $original,
                    (string) $slug,
                    (string) ($docxOrig ?? ''),
                    (string) $slugSpaces,
                ], static fn ($v) => trim((string) $v) !== '')));

                foreach ($candidates as $candidate) {
                    $loose = $this->buildLooseTokenPattern($candidate);
                    if ($loose === '') {
                        continue;
                    }

                    // Tolère des décorations autour du token dans le template:
                    // {{(MATRICULE_1)}}, {{« INTITULE »}}, etc.
                    $decor = "(?:<[^>]+>|[\\s\\x{00A0}\\x{00AB}\\x{00BB}\"'()«»]|&nbsp;|&#160;)*";

                    $newContent = $this->safePregReplace(
                        '~\[\s*' . $decor . $loose . $decor . '\s*\]~iu',
                        $val,
                        $newContent,
                        'replaceInOfficeFile:[' . $name . '] loose_square=' . $slug
                    );
                    $newContent = $this->safePregReplace(
                        '~\{\{\s*' . $decor . $loose . $decor . '\s*\}\}~iu',
                        $val,
                        $newContent,
                        'replaceInOfficeFile:[' . $name . '] loose_curly=' . $slug
                    );
                }
            }

            // Fallback final: remplace tout placeholder restant via une clé canonique
            // (tolère guillemets, parenthèses, apostrophes typographiques et petites fautes).
            try {
                $newContent = $this->replaceRemainingPlaceholdersByCanonicalLookup(
                    $newContent,
                    $canonicalValueMap,
                    $name
                );
            } catch (\Throwable $e) {
                // Ne jamais bloquer la génération sur ce fallback de confort.
                \Log::warning('replaceInOfficeFile canonical fallback skipped in ' . $name
                    . ' error=' . $e->getMessage());
            }

            if ($newContent !== $xmlContent) {
                $toUpdate[$name] = $newContent;
            }
        }

        foreach ($toUpdate as $name => $newContent) {
            $zip->addFromString($name, $newContent);
        }

        \Log::info('replaceInOfficeFile files_updated=' . count($toUpdate) . ' keys=' . implode(',', array_keys($toUpdate)));

        // ── Diagnostic post-remplacement : placeholders encore présents ────────
        // Relit les fichiers XML mis à jour et signale tout {{ }} ou [ ] restant.
        $zip->close();

        // Rouvrir en lecture seule pour vérifier le résultat
        $zip2 = new \ZipArchive();
        if ($zip2->open($absFilePath) === true) {
            for ($i = 0, $n2 = $zip2->numFiles; $i < $n2; $i++) {
                $stat2 = $zip2->statIndex($i);
                $name2 = $stat2['name'];
                if (!preg_match('/\.xml$/i', $name2)) continue;
                if (preg_match('#\[Content_Types\]|_rels/#', $name2)) continue;
                $xml2 = $zip2->getFromIndex($i);
                if ($xml2 === false) continue;
                // Extraire le texte lisible (balises <w:t>)
                preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/u', $xml2, $tM);
                $plain2 = implode('', $tM[1]);
                $remaining = [];
                if (preg_match_all('/\{\{[^}]{1,120}\}\}/u', $plain2, $rm)) {
                    $remaining = array_merge($remaining, $rm[0]);
                }
                if (preg_match_all('/\[[^\[\]]{1,120}\]/u', $plain2, $rm)) {
                    $remaining = array_merge($remaining, $rm[0]);
                }
                if (!empty($remaining)) {
                    \Log::warning('replaceInOfficeFile REMAINING placeholders in ' . $name2
                        . ': ' . implode(' | ', array_unique($remaining)));
                }
            }
            $zip2->close();
        }
    }

    /**
     * preg_replace defensif: en cas d'erreur PCRE, conserve le sujet inchange.
     */
    private function safePregReplace(string $pattern, string $replacement, string $subject, string $context): string
    {
        $out = preg_replace($pattern, $replacement, $subject);
        if ($out === null) {
            \Log::warning('safePregReplace failed, preserving XML content', [
                'context' => $context,
                'preg_error' => function_exists('preg_last_error_msg') ? preg_last_error_msg() : preg_last_error(),
            ]);
            return $subject;
        }

        return $out;
    }

    /**
     * Construit une table canonique token => valeur à injecter.
     */
    private function buildCanonicalValueMap(array $replacements, array $values, array $docxOriginalMap = []): array
    {
        $map = [];

        foreach ($replacements as $slug => $original) {
            $val = htmlspecialchars((string) ($values[$slug] ?? ''), ENT_XML1, 'UTF-8');
            if ($val === '') {
                continue;
            }

            $candidates = array_values(array_unique(array_filter([
                (string) $slug,
                (string) $original,
                (string) ($docxOriginalMap[$slug] ?? ''),
                str_replace('_', ' ', (string) $slug),
            ], static fn ($v) => trim((string) $v) !== '')));

            foreach ($candidates as $candidate) {
                $canon = $this->canonicalizePlaceholderToken($candidate);
                if ($canon !== '' && !isset($map[$canon])) {
                    $map[$canon] = $val;
                }
            }
        }

        // Ajoute aussi directement les clés soumises, utile si un slug n'est pas dans $replacements.
        foreach ($values as $k => $v) {
            $val = htmlspecialchars((string) $v, ENT_XML1, 'UTF-8');
            if ($val === '') {
                continue;
            }
            $canon = $this->canonicalizePlaceholderToken((string) $k);
            if ($canon !== '' && !isset($map[$canon])) {
                $map[$canon] = $val;
            }
        }

        return $map;
    }

    /**
     * Remplace les placeholders restants {{...}} et [...] via lookup canonique.
     */
    private function replaceRemainingPlaceholdersByCanonicalLookup(string $xml, array $canonicalValueMap, string $xmlName): string
    {
        if (empty($canonicalValueMap)) {
            return $xml;
        }

        $replaceCb = function (array $m) use ($canonicalValueMap, $xmlName) {
            $rawInner = (string) ($m[1] ?? '');
            $canon = $this->canonicalizePlaceholderToken($rawInner);

            if ($canon !== '' && isset($canonicalValueMap[$canon])) {
                return $canonicalValueMap[$canon];
            }

            // Tolérance fautes de frappe légères (ex: financemant/financement, intutile/intitule)
            if ($canon !== '') {
                // Garde-fou perf et robustesse.
                if (strlen($canon) > 180) {
                    return $m[0];
                }

                $best = null;
                $bestDist = 99;
                foreach (array_keys($canonicalValueMap) as $candidateCanon) {
                    if (strlen($candidateCanon) > 180) {
                        continue;
                    }
                    $dist = levenshtein($canon, $candidateCanon);
                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $best = $candidateCanon;
                    }
                }

                $maxDist = max(1, (int) floor(strlen($canon) * 0.18));
                if ($best !== null && $bestDist <= $maxDist) {
                    \Log::info('replaceInOfficeFile FUZZY match in ' . $xmlName
                        . ' token=' . $rawInner . ' canon=' . $canon . ' -> ' . $best
                        . ' (dist=' . $bestDist . ')');
                    return $canonicalValueMap[$best];
                }
            }

            return $m[0];
        };

        $xml2 = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/u', $replaceCb, $xml);
        if ($xml2 === null) {
            \Log::warning('replaceInOfficeFile canonical {{}} regex failed in ' . $xmlName
                . ' preg_error=' . preg_last_error_msg());
            $xml2 = $xml;
        }

        $xml3 = preg_replace_callback('/\[\s*([^\[\]]*?)\s*\]/u', $replaceCb, $xml2);
        if ($xml3 === null) {
            \Log::warning('replaceInOfficeFile canonical [] regex failed in ' . $xmlName
                . ' preg_error=' . preg_last_error_msg());
            $xml3 = $xml2;
        }

        return $xml3;
    }

    /**
     * Canonicalise un token de placeholder pour matcher les variantes typographiques.
     */
    private function canonicalizePlaceholderToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        // Retirer décorations fréquentes autour du contenu.
        $token = preg_replace('/^[\s\x{00AB}\x{00BB}"\'"()\[\]{}]+|[\s\x{00AB}\x{00BB}"\'"()\[\]{}]+$/u', '', $token) ?? $token;
        $token = str_replace(["’", "‘", "`", "´"], "'", $token);

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $token);
        $token = ($ascii !== false && $ascii !== '') ? $ascii : $token;
        $token = strtolower($token);

        $token = preg_replace('/[^a-z0-9]+/u', '_', $token) ?? $token;
        $token = trim($token, '_');

        return $token;
    }

    /**
     * Construit un motif regex souple pour faire correspondre un token de variable
        * malgré les accents, apostrophes typographiques, séparateurs variés
        * et fragmentations XML Word (balises intercalées entre caractères).
     */
    private function buildLooseTokenPattern(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $token);
        $normalized = $ascii !== false && $ascii !== '' ? $ascii : $token;

        $parts = preg_split('//u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        $xmlBridge = '(?:<[^>]+>|[\s\x{00A0}]|&nbsp;|&#160;)*';
        $xmlSep = '(?:<[^>]+>|[\s\x{00A0}_\-\x27’]|&nbsp;|&#160;)+';

        foreach ($parts as $char) {
            $lower = mb_strtolower($char, 'UTF-8');

            // Séparateurs souples entre mots.
            if (preg_match("/[\\s_\\-'’]/u", $char)) {
                $out .= $xmlSep;
                continue;
            }

            switch ($lower) {
                case 'a': $out .= '[aàáâäãå]' . $xmlBridge; break;
                case 'c': $out .= '[cç]' . $xmlBridge; break;
                case 'e': $out .= '[eèéêë]' . $xmlBridge; break;
                case 'i': $out .= '[iìíîï]' . $xmlBridge; break;
                case 'n': $out .= '[nñ]' . $xmlBridge; break;
                case 'o': $out .= '[oòóôöõ]' . $xmlBridge; break;
                case 'u': $out .= '[uùúûü]' . $xmlBridge; break;
                case 'y': $out .= '[yýÿ]' . $xmlBridge; break;
                default:
                    $out .= preg_quote($char, '~') . $xmlBridge;
                    break;
            }
        }

        return $out;
    }

    /**
     * Défragmente les runs Word dans chaque paragraphe <w:p>.
     *
     * Problème : Word peut stocker [VAR] sur plusieurs runs :
     *   <w:r><w:t>[</w:t></w:r><w:r><w:t>VAR</w:t></w:r><w:r><w:t>]</w:t></w:r>
     *
     * Solution : pour chaque paragraphe, si le texte concaténé contient [variable],
     * on regroupe tous les textes dans un seul <w:r> avec le rPr du premier run.
     * Les paragraphes sans [variable] ne sont pas touchés.
     * Le texte XML brut est conservé tel quel (pas de decode/re-encode).
     */
    private function defragmentRuns(string $xml, string $xmlName = ''): string
    {
        $maxXmlBytesForRegex = $this->getIntAppSetting(
            'template_defrag_max_xml_bytes',
            1200000,
            200000,
            20000000
        );

        $maxParagraphBytesForRegex = $this->getIntAppSetting(
            'template_defrag_max_paragraph_bytes',
            45000,
            2000,
            500000
        );

        // Mode safe: pour les très gros XML, on contourne totalement la défragmentation regex
        // afin d'éviter backtracking/catastrophic regex et tout risque de vidage du document.
        if (strlen($xml) > $maxXmlBytesForRegex) {
            \Log::info('defragmentRuns skipped for large XML (safe mode)', [
                'file' => $xmlName,
                'xml_bytes' => strlen($xml),
                'threshold' => $maxXmlBytesForRegex,
            ]);
            return $xml;
        }

        $result = preg_replace_callback(
            '/<w:p[ >].*?<\/w:p>/s',
            function (array $match) use ($maxParagraphBytesForRegex) {
                $para = $match[0];

                // Mode safe: ne jamais défragmenter les paragraphes trop gros.
                // Sur des blocs massifs, les regex peuvent devenir instables/ coûteuses.
                if (strlen($para) > $maxParagraphBytesForRegex) {
                    return $para;
                }

                // Ne jamais toucher les paragraphes complexes: ils peuvent contenir
                // des objets/ancres/champs que la reconstruction d'un unique <w:r>
                // ferait disparaître (zone, QR, numérotation, etc.).
                // Note: <w:proofErr> est exclu car c'est un simple marqueur orthographique
                // sans contenu — il fragmente souvent les variables {{…}} entre runs.
                if (preg_match(
                    '/<(w:(drawing|pict|object|tbl|hyperlink|bookmarkStart|bookmarkEnd|fldSimple|instrText|fldChar|sdt|smartTag|tab|br|cr)|mc:AlternateContent)\b/i',
                    $para
                )) {
                    return $para;
                }

                // Défense supplémentaire: on ne réécrit que les paragraphes qui ne
                // contiennent que pPr + runs texte (aucune autre structure).
                $skeleton = $para;
                $skeleton = preg_replace('/^<w:p[^>]*>|<\/w:p>$/s', '', $skeleton);
                $skeleton = preg_replace('/<w:pPr>.*?<\/w:pPr>/s', '', $skeleton);
                $skeleton = preg_replace('/<w:r[ >].*?<\/w:r>/s', '', $skeleton);
                // Les <w:proofErr> n'ont pas de contenu texte, on les ignore dans le squelette
                $skeleton = preg_replace('/<w:proofErr[^>]*\/>/s', '', $skeleton);
                if ($skeleton === null || trim(strip_tags($skeleton)) !== '') {
                    return $para;
                }

                // Extraire le texte brut XML de tous les <w:t> (sans décoder les entités)
                preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $para, $texts);
                $fullText = implode('', $texts[1]);

                // Rien à faire s'il n'y a qu'un seul noeud texte
                if (count($texts[0]) < 2) {
                    return $para;
                }

                // Si pas de [variable] ni {{variable}} dans ce paragraphe → ne rien toucher
                if (!preg_match('/\[[^\[\]]+\]/', $fullText) && strpos($fullText, '{{') === false) {
                    return $para;
                }

                // Réécrire uniquement quand un placeholder est effectivement fragmenté
                // sur plusieurs runs (<w:t>...</w:t><w:t>...).
                if (!preg_match('/(\[|\{\{)[\s\S]*?<\/w:t>[\s\S]*?<w:t[^>]*>[\s\S]*?(\]|\}\})/s', $para)) {
                    return $para;
                }

                // Récupérer le rPr du premier run (pour conserver le formatage)
                $firstRpr = '';
                if (preg_match('/<w:r[ >].*?(<w:rPr>.*?<\/w:rPr>)/s', $para, $rprMatch)) {
                    $firstRpr = $rprMatch[1];
                }

                // Extraire le pPr (propriétés du paragraphe) si présent
                $pPr = '';
                if (preg_match('/<w:pPr>.*?<\/w:pPr>/s', $para, $pPrMatch)) {
                    $pPr = $pPrMatch[0];
                }

                // Extraire le tag ouvrant <w:p ...>
                preg_match('/^<w:p[^>]*>/', $para, $openTag);
                $open = $openTag[0] ?? '<w:p>';

                // Conserver les attributs du premier <w:t> (ex: xml:space="preserve").
                $firstTextAttrs = ' xml:space="preserve"';
                if (preg_match('/<w:t([^>]*)>/', $para, $tAttrMatch)) {
                    $attrs = trim((string) ($tAttrMatch[1] ?? ''));
                    $firstTextAttrs = $attrs !== '' ? ' ' . $attrs : '';
                }

                // Reconstruire le paragraphe : pPr + un seul run avec tout le texte brut
                return $open
                    . $pPr
                    . '<w:r>'
                    . $firstRpr
                    . '<w:t' . $firstTextAttrs . '>' . $fullText . '</w:t>'
                    . '</w:r>'
                    . '</w:p>';
            },
            $xml
        );

        if (!is_string($result)) {
            \Log::warning('defragmentRuns regex failed, preserving original XML', [
                'preg_error' => function_exists('preg_last_error_msg') ? preg_last_error_msg() : preg_last_error(),
            ]);
            return $xml;
        }

        return $result;
    }
}

