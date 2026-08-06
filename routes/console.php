<?php

use App\Models\DocumentTemplate;
use App\Models\SignatureProviderConfig;
use App\Models\WorkflowExecution;
use App\Services\TemplateOfficeTextExtractor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rappels/alertes automatiques du module Reunions : rappel de reunion a
// venir, relance validateur, alerte echeance de traitement. La commande
// est idempotente (marque les entites deja traitees), donc une execution
// horaire suffit meme si le scheduler redemarre.
Schedule::command('meetings:send-reminders')
    ->hourly()
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::error('La commande meetings:send-reminders a echoue.');
    });

Artisan::command('templates:backfill-content
    {--dry-run : Affiche les templates qui seront repares sans ecrire en base}
    {--limit=200 : Nombre max de templates a traiter}
    {--template-id= : Traite un template precis}', function () {
    $limit = max(1, (int) $this->option('limit'));
    $templateId = trim((string) $this->option('template-id'));
    $dryRun = (bool) $this->option('dry-run');
    $officeTypes = ['docx', 'xlsx', 'pptx'];
    $extractor = app(TemplateOfficeTextExtractor::class);

    $query = DocumentTemplate::query()
        ->whereIn('file_type', $officeTypes)
        ->whereNotNull('storage_path')
        ->where(function ($subQuery) {
            $subQuery->whereNull('content')
                ->orWhere('content', '');
        })
        ->orderBy('created_at');

    if ($templateId !== '') {
        $query->where('id', $templateId);
    }

    $templates = $query->limit($limit)->get();
    if ($templates->isEmpty()) {
        $this->info('Aucun template a rattraper.');
        return 0;
    }

    $this->info('Templates a analyser: ' . $templates->count() . ($dryRun ? ' (dry-run)' : ''));

    $updated = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($templates as $template) {
        $storagePath = (string) $template->storage_path;
        $absPath = str_starts_with($storagePath, 'images/')
            ? public_path($storagePath)
            : Storage::disk('public')->path($storagePath);

        if (!is_file($absPath)) {
            $skipped++;
            $this->warn("[SKIP] {$template->id} source introuvable: {$storagePath}");
            continue;
        }

        try {
            $content = $extractor->extract($absPath);
            if (trim($content) === '') {
                $failed++;
                $this->error("[FAIL] {$template->id} contenu non extractible");
                continue;
            }

            if ($dryRun) {
                $updated++;
                $preview = function_exists('mb_substr') ? mb_substr($content, 0, 80) : substr($content, 0, 80);
                $this->line("[DRY] {$template->id} => {$preview}");
                continue;
            }

            $template->forceFill(['content' => $content])->save();
            $updated++;
            $this->info("[OK] {$template->id} contenu renseigne");
        } catch (\Throwable $e) {
            $failed++;
            $this->error("[FAIL] {$template->id} exception: {$e->getMessage()}");
            Log::error('Template content backfill failed', [
                'template_id' => $template->id,
                'storage_path' => $storagePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    $this->newLine();
    $this->info("Termine. OK={$updated}, FAIL={$failed}, SKIP={$skipped}");

    return $failed > 0 ? 2 : 0;
})->purpose('Renseigne le contenu de secours des anciens templates Office');

Artisan::command('signatures:backfill-signed-docs
    {--dry-run : Affiche les exécutions à traiter sans télécharger}
    {--limit=50 : Nombre max d\'exécutions à traiter}
    {--execution-id= : Traiter une exécution précise}', function () {
    $normalizeEndpoint = static function (string $endpoint): string {
        $endpoint = rtrim($endpoint, '/');
        if (str_ends_with($endpoint, '/api')) {
            $endpoint = substr($endpoint, 0, -4);
        }
        return $endpoint;
    };

    $cfg = SignatureProviderConfig::query()
        ->where('is_active', true)
        ->first();

    if (!$cfg) {
        $this->error('Aucune configuration de signature active.');
        return 1;
    }

    $endpoint = $normalizeEndpoint((string) $cfg->endpoint);
    $token = (string) ($cfg->api_key ?? $cfg->api_token ?? '');
    $verifySSL = (bool) ($cfg->verify_ssl ?? true);
    $limit = max(1, (int) $this->option('limit'));
    $executionId = trim((string) $this->option('execution-id'));
    $dryRun = (bool) $this->option('dry-run');

    if ($token === '') {
        $this->error('Token API manquant dans signature_provider_configs.');
        return 1;
    }

    $query = WorkflowExecution::query()
        ->with('document')
        ->where('status', 'completed')
        ->whereNotNull('platform_workflow_id')
        ->whereHas('document', function ($q) {
            $q->whereNull('signed_file_path');
        })
        ->orderByDesc('started_at');

    if ($executionId !== '') {
        $query->where('id', $executionId);
    }

    $executions = $query->limit($limit)->get();
    if ($executions->isEmpty()) {
        $this->info('Aucune exécution à rattraper.');
        return 0;
    }

    $this->info('Exécutions à traiter: ' . $executions->count() . ($dryRun ? ' (dry-run)' : ''));

    $ok = 0;
    $failed = 0;
    $skipped = 0;

    foreach ($executions as $execution) {
        $document = $execution->document;
        if (!$document) {
            $skipped++;
            $this->warn("[SKIP] {$execution->id} document introuvable");
            continue;
        }

        $url = "{$endpoint}/api/workflows/{$execution->platform_workflow_id}/downloadDocuments";

        try {
            $resp = Http::withToken($token)
                ->timeout(90)
                ->when(!$verifySSL, fn($h) => $h->withoutVerifying())
                ->get($url);

            if (!$resp->successful()) {
                $failed++;
                $this->error("[FAIL] {$execution->id} HTTP {$resp->status()}");
                Log::warning('Backfill signed docs: HTTP error', [
                    'execution_id' => $execution->id,
                    'workflow_id' => $execution->platform_workflow_id,
                    'status' => $resp->status(),
                    'body_excerpt' => substr((string) $resp->body(), 0, 300),
                ]);
                continue;
            }

            $pdfContent = (string) $resp->body();
            if ($pdfContent === '' || strlen($pdfContent) < 100) {
                $failed++;
                $this->error("[FAIL] {$execution->id} contenu PDF invalide");
                continue;
            }

            $originalName = pathinfo((string) ($document->file_path ?? 'document.pdf'), PATHINFO_FILENAME);
            $filename = 'signed_' . $originalName . '_backfill_' . now()->format('Ymd_His') . '_' . substr((string) $execution->id, 0, 8) . '.pdf';
            $storagePath = 'signed_documents/' . $filename;

            if ($dryRun) {
                $ok++;
                $this->line("[DRY] {$execution->id} => {$storagePath}");
                continue;
            }

            Storage::disk('public')->makeDirectory('signed_documents');
            Storage::disk('public')->put($storagePath, $pdfContent);

            $document->update([
                'signed_file_path' => $storagePath,
                'status' => 'signed',
                'signed_at' => $document->signed_at ?? now(),
            ]);

            $ok++;
            $this->info("[OK] {$execution->id} => {$storagePath}");
        } catch (\Throwable $e) {
            $failed++;
            $this->error("[FAIL] {$execution->id} exception: {$e->getMessage()}");
            Log::error('Backfill signed docs: exception', [
                'execution_id' => $execution->id,
                'workflow_id' => $execution->platform_workflow_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    $this->newLine();
    $this->info("Terminé. OK={$ok}, FAIL={$failed}, SKIP={$skipped}");

    return $failed > 0 ? 2 : 0;
})->purpose('Rattrape les PDF signés manquants pour les exécutions completed');
