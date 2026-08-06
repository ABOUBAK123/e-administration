<?php

namespace App\Console\Commands;

use App\Services\MeetingReminderService;
use Illuminate\Console\Command;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';

    protected $description = "Envoie les rappels/alertes automatiques du module Reunions : "
        . "rappel de reunion a venir, relance validateur, alerte echeance de traitement.";

    public function handle(MeetingReminderService $service): int
    {
        $hasError = false;

        $this->info('Rappels de reunions a venir...');
        $reminderHours = (int) config('meetings.reminder_hours_before_meeting', 24);
        $stats = $service->sendUpcomingMeetingReminders($reminderHours);
        $this->line("  Traites: {$stats['processed']} | Envoyes: {$stats['sent']} | Sans destinataire: {$stats['skipped']} | Erreurs: {$stats['errors']}");
        if ($stats['errors'] > 0) {
            $hasError = true;
        }

        $this->info('Relances validateurs (comptes rendus en attente de validation)...');
        $staleHours = (int) config('meetings.validation_stale_hours', 48);
        $reminderIntervalHours = (int) config('meetings.validation_reminder_interval_hours', 48);
        $stats = $service->sendValidationFollowUps($staleHours, $reminderIntervalHours);
        $this->line("  Traites: {$stats['processed']} | Envoyes: {$stats['sent']} | Sans destinataire: {$stats['skipped']} | Erreurs: {$stats['errors']}");
        if ($stats['errors'] > 0) {
            $hasError = true;
        }

        $this->info("Alertes d'echeance de traitement approchant...");
        $deadlineHours = (int) config('meetings.deadline_alert_hours_before', 24);
        $stats = $service->sendDeadlineAlerts($deadlineHours);
        $this->line("  Traites: {$stats['processed']} | Envoyes: {$stats['sent']} | Sans destinataire: {$stats['skipped']} | Erreurs: {$stats['errors']}");
        if ($stats['errors'] > 0) {
            $hasError = true;
        }

        if ($hasError) {
            $this->error('Des erreurs sont survenues lors de l\'envoi de certains rappels. Voir storage/logs/laravel.log.');
            return self::FAILURE;
        }

        $this->info('Termine.');
        return self::SUCCESS;
    }
}
