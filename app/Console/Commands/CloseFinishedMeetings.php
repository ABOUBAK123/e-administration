<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Services\MeetingArchive;
use Illuminate\Console\Command;

class CloseFinishedMeetings extends Command
{
    /** Archivages par passage : la génération des PDF consomme de la mémoire. */
    private const ARCHIVES_PER_RUN = 20;

    protected $signature = 'meetings:close-finished';

    protected $description = 'Met à jour le statut des réunions (en cours, terminée) et archive dans la GED les comptes rendus validés.';

    public function handle(MeetingArchive $archive): int
    {
        // toBase() : le changement de statut ne doit pas modifier updated_at (clé de session OnlyOffice).
        $closed = Meeting::whereIn('status', ['planned', 'in_progress'])
            ->where('ends_at', '<=', now())
            ->toBase()->update(['status' => 'closed']);
        $started = Meeting::where('status', 'planned')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->toBase()->update(['status' => 'in_progress']);
        $this->line("Réunions terminées : {$closed} | en cours : {$started}");

        $pending = Meeting::whereNull('archived_at')
            ->whereIn('workflow_status', ['validated', 'published'])
            ->where('status', '!=', 'cancelled')
            ->where('ends_at', '<=', now())
            // Les échecs passent après les réunions jamais tentées.
            ->orderBy('archive_attempted_at')
            ->orderBy('ends_at')
            ->limit(self::ARCHIVES_PER_RUN)
            ->get();

        $done = 0;
        $errors = 0;
        foreach ($pending as $meeting) {
            if (!$archive->isDue($meeting)) {
                continue;
            }
            try {
                $done += $archive->archive($meeting) ? 1 : 0;
            } catch (\RuntimeException $e) {
                $errors++;
                $this->warn($meeting->title . ' : ' . $e->getMessage());
            }
        }
        $this->line("Réunions archivées : {$done} | erreurs : {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
