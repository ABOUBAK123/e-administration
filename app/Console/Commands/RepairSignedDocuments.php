<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\WorkflowExecution;
use App\Services\SignedDocumentArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Répare les documents signés enregistrés sous forme d'archive ZIP avec l'extension .pdf.
 * L'archive contient les PDF signés : aucun appel à la plateforme de signature n'est nécessaire.
 */
class RepairSignedDocuments extends Command
{
    protected $signature = 'signatures:repair
        {--dry-run : Affiche ce qui serait fait sans rien modifier}
        {--from-archives : Refait l\'extraction à partir des archives conservées par une réparation précédente}';

    protected $description = 'Répare les documents signés illisibles (archive ZIP enregistrée en .pdf)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');
        $counts = ['repares' => 0, 'echecs' => 0, 'sains' => 0];

        foreach (Document::withTrashed()->whereNotNull('signed_file_path')->where('signed_file_path', '!=', '')->cursor() as $document) {
            $path = ltrim(str_replace('/storage/', '', (string) $document->signed_file_path), '/');
            $label = "{$document->id} « {$document->title} »";

            if (!$disk->exists($path)) {
                $this->warn("Fichier absent : {$label} ({$path})");
                $counts['echecs']++;
                continue;
            }

            $content = (string) $disk->get($path);
            $backup = 'signed_archives/' . basename($path) . '.zip';
            $current = null;
            if ($this->option('from-archives') && Storage::disk('local')->exists($backup)) {
                $current = $content;
                $content = (string) Storage::disk('local')->get($backup);
            }
            if (SignedDocumentArchive::isPdf($content) && !SignedDocumentArchive::isZip($content)) {
                $counts['sains']++;
                continue;
            }
            if (!SignedDocumentArchive::isZip($content)) {
                $this->warn("Ni PDF ni ZIP : {$label}, début : " . bin2hex(substr($content, 0, 16)));
                $counts['echecs']++;
                continue;
            }

            $index = WorkflowExecution::query()->where('document_id', $document->id)->get()
                ->map(fn ($e) => $e->step_data['platform_doc_index'] ?? null)
                ->first(fn ($i) => is_int($i));
            $entries = SignedDocumentArchive::pdfEntries($content);
            $pdf = SignedDocumentArchive::pdfForDocument($entries, (string) $document->id, $index, (string) $document->title);

            if ($pdf === null || !SignedDocumentArchive::isPdf($pdf)) {
                $this->warn("PDF introuvable dans l'archive : {$label}, contenu : " . implode(', ', array_keys($entries)));
                $counts['echecs']++;
                continue;
            }

            if ($current !== null && $current === $pdf) {
                $counts['sains']++;
                continue;
            }

            if (!$dryRun) {
                // L'archive d'origine est conservée hors du dossier public.
                Storage::disk('local')->put($backup, $content);
                $disk->put($path, $pdf);

                $mainPath = ltrim(str_replace('/storage/', '', (string) $document->file_path), '/');
                if ($mainPath === $path) {
                    $document->update(['file_size' => strlen($pdf)]);
                }
            }

            $this->info(($dryRun ? 'À réparer : ' : 'Réparé : ') . $label);
            $counts['repares']++;
        }

        $this->line(sprintf(
            '%d %s, %d en échec, %d déjà lisibles.',
            $counts['repares'], $dryRun ? 'à réparer' : 'réparé(s)', $counts['echecs'], $counts['sains']
        ));

        return $counts['echecs'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
