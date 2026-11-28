<?php

namespace App\Services;

use App\Models\AdministrationSmtpSetting;
use App\Models\Meeting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Rappels et alertes automatiques pour le module Reunions.
 *
 * Ce service est concu pour etre invoque depuis une commande planifiee
 * (voir App\Console\Commands\SendMeetingReminders) et non depuis une requete
 * HTTP : il ne depend donc d'aucun contexte utilisateur/session et parcourt
 * l'ensemble des reunions, toutes administrations confondues.
 */
class MeetingReminderService
{
    /**
     * Les rappels partent d'une tâche planifiée, sans utilisateur connecté : on prend le SMTP de
     * l'administration de l'utilisateur qui a créé la réunion (l'organisateur), à défaut celui de
     * l'administration de la réunion.
     */
    private function resolveMeetingSmtpSetting(Meeting $meeting): ?AdministrationSmtpSetting
    {
        $complete = fn (?AdministrationSmtpSetting $smtp) => $smtp && $smtp->mail_host && $smtp->mail_from_address ? $smtp : null;

        $smtp = $complete(NotificationService::resolveWorkflowSmtpSetting((string) $meeting->organizer_id));
        if ($smtp) {
            return $smtp;
        }

        $administrationId = trim((string) ($meeting->issuing_administration_id ?? ''));
        if ($administrationId === '') {
            return null;
        }

        return $complete(AdministrationSmtpSetting::forAdministration($administrationId, 'emitter'))
            ?? $complete(AdministrationSmtpSetting::forAdministration($administrationId, 'recipient'));
    }

    /** @throws \RuntimeException aucune configuration SMTP utilisable */
    private function configureMeetingMailerFor(Meeting $meeting): void
    {
        $smtp = $this->resolveMeetingSmtpSetting($meeting);
        if (!$smtp) {
            throw new \RuntimeException("Aucune configuration SMTP complete pour l'administration de l'organisateur.");
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $smtp->mail_host,
            'mail.mailers.smtp.port' => $smtp->mail_port ?? 587,
            'mail.mailers.smtp.username' => $smtp->mail_username,
            'mail.mailers.smtp.password' => $smtp->mail_password,
            'mail.mailers.smtp.encryption' => $smtp->mail_encryption ?: null,
            'mail.mailers.smtp.timeout' => 10,
            'mail.from.address' => $smtp->mail_from_address,
            'mail.from.name' => $smtp->mail_from_name ?? config('app.name'),
        ]);

        // Plusieurs administrations sont traitées à la suite : sans purge, le premier SMTP resterait utilisé.
        Mail::purge('smtp');
    }

    /**
     * Envoie un rappel par email aux participants invites pour les reunions
     * qui debutent dans les prochaines $hoursBefore heures et qui n'ont pas
     * deja ete rappelees.
     *
     * @return array{processed:int,sent:int,skipped:int,errors:int}
     */
    public function sendUpcomingMeetingReminders(int $hoursBefore): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'errors' => 0];

        $meetings = Meeting::query()
            ->whereNull('reminder_sent_at')
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addHours($hoursBefore))
            ->with(['participants.user', 'room', 'organizer'])
            ->get();

        foreach ($meetings as $meeting) {
            $stats['processed']++;

            try {
                $emails = $this->collectParticipantEmails($meeting);

                if ($emails->isEmpty()) {
                    Log::warning('Rappel reunion non envoye : aucun destinataire valide.', [
                        'meeting_id' => $meeting->id,
                        'meeting_title' => $meeting->title,
                    ]);
                    $meeting->reminder_sent_at = now();
                    $meeting->save();
                    $stats['skipped']++;
                    continue;
                }

                $this->dispatchMeetingReminderEmail($meeting, $emails);

                $meeting->reminder_sent_at = now();
                $meeting->save();
                $stats['sent']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('Echec envoi rappel reunion.', [
                    'meeting_id' => $meeting->id,
                    'meeting_title' => $meeting->title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Relance le validateur si le compte rendu reste "en validation"
     * au-dela du seuil configure, en respectant un intervalle minimum
     * entre deux relances.
     *
     * @return array{processed:int,sent:int,skipped:int,errors:int}
     */
    public function sendValidationFollowUps(int $staleHours, int $reminderIntervalHours): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'errors' => 0];

        $meetings = Meeting::query()
            ->where('workflow_status', 'in_validation')
            ->whereNotNull('validation_requested_at')
            ->where('validation_requested_at', '<=', now()->subHours($staleHours))
            ->where(function ($query) use ($reminderIntervalHours) {
                $query->whereNull('validation_reminder_sent_at')
                    ->orWhere('validation_reminder_sent_at', '<=', now()->subHours($reminderIntervalHours));
            })
            ->with(['validator', 'minutesWriter'])
            ->get();

        foreach ($meetings as $meeting) {
            $stats['processed']++;

            try {
                $validatorEmail = trim((string) ($meeting->validator?->email ?? ''));
                if ($validatorEmail === '' || !filter_var($validatorEmail, FILTER_VALIDATE_EMAIL)) {
                    Log::warning('Relance validation non envoyee : validateur sans email valide.', [
                        'meeting_id' => $meeting->id,
                        'meeting_title' => $meeting->title,
                    ]);
                    $stats['skipped']++;
                    continue;
                }

                $this->dispatchValidationFollowUpEmail($meeting, $validatorEmail);

                $meeting->validation_reminder_sent_at = now();
                $meeting->save();
                $stats['sent']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('Echec envoi relance validation reunion.', [
                    'meeting_id' => $meeting->id,
                    'meeting_title' => $meeting->title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Alerte l'organisateur (et le redacteur) lorsque l'echeance de
     * traitement (processing_deadline) approche et que le compte rendu
     * n'est pas encore publie.
     *
     * @return array{processed:int,sent:int,skipped:int,errors:int}
     */
    public function sendDeadlineAlerts(int $hoursBefore): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'errors' => 0];

        $meetings = Meeting::query()
            ->whereNotNull('processing_deadline')
            ->where('workflow_status', '!=', 'published')
            ->where('processing_deadline', '>', now())
            ->where('processing_deadline', '<=', now()->addHours($hoursBefore))
            ->whereNull('deadline_alert_sent_at')
            ->with(['organizer', 'minutesWriter'])
            ->get();

        foreach ($meetings as $meeting) {
            $stats['processed']++;

            try {
                $recipients = collect([
                    trim((string) ($meeting->organizer?->email ?? '')),
                    trim((string) ($meeting->minutesWriter?->email ?? '')),
                ])
                    ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
                    ->unique()
                    ->values();

                if ($recipients->isEmpty()) {
                    Log::warning('Alerte echeance non envoyee : aucun destinataire valide.', [
                        'meeting_id' => $meeting->id,
                        'meeting_title' => $meeting->title,
                    ]);
                    $meeting->deadline_alert_sent_at = now();
                    $meeting->save();
                    $stats['skipped']++;
                    continue;
                }

                $this->dispatchDeadlineAlertEmail($meeting, $recipients);

                $meeting->deadline_alert_sent_at = now();
                $meeting->save();
                $stats['sent']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('Echec envoi alerte echeance reunion.', [
                    'meeting_id' => $meeting->id,
                    'meeting_title' => $meeting->title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function collectParticipantEmails(Meeting $meeting): \Illuminate\Support\Collection
    {
        $emails = collect();

        foreach ($meeting->participants as $participant) {
            $email = trim((string) ($participant->email ?: $participant->user?->email ?: ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails->push($email);
            }
        }

        return $emails->unique()->values();
    }

    private function dispatchMeetingReminderEmail(Meeting $meeting, \Illuminate\Support\Collection $emails): void
    {
        $subject = 'Rappel - Reunion "' . $meeting->title . '" a venir';
        $body = "Bonjour,\n\n"
            . "Ceci est un rappel automatique concernant la reunion suivante :\n"
            . "- Titre : {$meeting->title}\n"
            . "- Date : " . (string) optional($meeting->starts_at)->format('d/m/Y H:i') . "\n"
            . "- Salle : " . (string) ($meeting->room?->name ?: 'N/A') . "\n"
            . "- Organisateur : " . (string) ($meeting->organizer?->name ?: 'N/A') . "\n";

        if ($meeting->is_virtual && !empty($meeting->meeting_link)) {
            $body .= "- Lien de visioconference : {$meeting->meeting_link}\n";
        }

        $body .= "\nMerci de vous organiser en consequence.\n\n"
            . "Cordialement.";

        $this->configureMeetingMailerFor($meeting);

        Mail::raw($body, function ($message) use ($emails, $subject) {
            $to = $emails->first();
            $bcc = $emails->slice(1)->all();

            $message->to($to)->subject($subject);
            if (!empty($bcc)) {
                $message->bcc($bcc);
            }
        });
    }

    private function dispatchValidationFollowUpEmail(Meeting $meeting, string $validatorEmail): void
    {
        $showUrl = route('meetings.show', $meeting);
        $writerName = (string) ($meeting->minutesWriter?->name ?: 'Redacteur');
        $waitingSince = (string) optional($meeting->validation_requested_at)->format('d/m/Y H:i');
        $subject = 'Relance - Validation en attente - Compte rendu reunion: ' . $meeting->title;
        $body = "Bonjour,\n\n"
            . "Le compte rendu de la reunion suivante attend toujours votre validation :\n"
            . "- Titre : {$meeting->title}\n"
            . "- Date : " . (string) optional($meeting->starts_at)->format('d/m/Y H:i') . "\n"
            . "- Redacteur : {$writerName}\n"
            . "- En attente de validation depuis : {$waitingSince}\n\n"
            . "Merci de valider ou de renvoyer pour correction dans les meilleurs delais :\n"
            . "{$showUrl}\n\n"
            . "Cordialement.";

        $this->configureMeetingMailerFor($meeting);

        Mail::raw($body, function ($message) use ($validatorEmail, $subject) {
            $message->to($validatorEmail)->subject($subject);
        });
    }

    private function dispatchDeadlineAlertEmail(Meeting $meeting, \Illuminate\Support\Collection $recipients): void
    {
        $showUrl = route('meetings.show', $meeting);
        $deadline = (string) optional($meeting->processing_deadline)->format('d/m/Y H:i');
        $subject = 'Alerte - Echeance proche sans compte rendu publie: ' . $meeting->title;
        $body = "Bonjour,\n\n"
            . "L'echeance de traitement du compte rendu de la reunion suivante approche et le compte rendu n'est pas encore publie :\n"
            . "- Titre : {$meeting->title}\n"
            . "- Date de la reunion : " . (string) optional($meeting->starts_at)->format('d/m/Y H:i') . "\n"
            . "- Echeance de traitement : {$deadline}\n"
            . "- Statut actuel : " . (string) ($meeting->workflow_status ?? 'draft') . "\n\n"
            . "Merci de finaliser et publier le compte rendu avant l'echeance :\n"
            . "{$showUrl}\n\n"
            . "Cordialement.";

        $this->configureMeetingMailerFor($meeting);

        Mail::raw($body, function ($message) use ($recipients, $subject) {
            $to = $recipients->first();
            $cc = $recipients->slice(1)->all();

            $message->to($to)->subject($subject);
            if (!empty($cc)) {
                $message->cc($cc);
            }
        });
    }
}
