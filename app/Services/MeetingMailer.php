<?php

namespace App\Services;

use App\Models\AdministrationSmtpSetting;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * E-mails aux participants (invitation, modification, annulation), envoyés avec le SMTP
 * de l'administration de la réunion.
 */
class MeetingMailer
{
    private const MAX_DATES = 60;

    /** Applique le SMTP de l'administration de la réunion ; renvoie la raison si c'est impossible. */
    public function configureFor(Meeting $meeting): ?string
    {
        $administrationId = trim((string) ($meeting->issuing_administration_id ?? ''));
        $smtp = $administrationId === '' ? null
            : AdministrationSmtpSetting::forAdministration($administrationId, 'emitter')
                ?? AdministrationSmtpSetting::forAdministration($administrationId, 'recipient');

        if (!$smtp) {
            return "Aucune configuration SMTP d'administration n'a ete trouvee pour cette reunion.";
        }
        if (!$smtp->mail_host || !$smtp->mail_from_address) {
            return "La configuration SMTP d'administration est incomplete (hote ou expediteur manquant).";
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
        Mail::purge('smtp');

        return null;
    }

    /** Identifie une personne d'une réunion à l'autre : son compte, sinon son adresse. */
    public static function participantKey(MeetingParticipant $participant): ?string
    {
        if ($participant->user_id) {
            return 'u:' . $participant->user_id;
        }
        $email = mb_strtolower(trim((string) $participant->email));

        return $email === '' ? null : 'e:' . $email;
    }

    /**
     * Destinataires des réunions, chacun avec les seules dates qui le concernent.
     * Les comptes désactivés ou supprimés sont écartés.
     *
     * @param iterable<Meeting> $meetings
     * @return array<string, array{email: string, name: string, meetings: list<Meeting>}> par clé de participant
     */
    public function recipients(iterable $meetings): array
    {
        $recipients = [];
        foreach ($meetings as $meeting) {
            foreach ($meeting->participants()->with('user')->get() as $participant) {
                if ($participant->user_id && (!$participant->user || $participant->user->status !== 'active')) {
                    continue;
                }
                $key = self::participantKey($participant);
                $email = trim((string) ($participant->email ?: $participant->user?->email));
                if ($key === null || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $recipients[$key] ??= ['email' => $email, 'name' => (string) ($participant->full_name ?: $participant->user?->name), 'meetings' => []];
                $recipients[$key]['meetings'][] = $meeting;
            }
        }

        return $recipients;
    }

    public function invite(array $recipients): array
    {
        $result = $this->send($recipients, 'Invitation à la réunion', 'Vous êtes invité(e) à la réunion suivante :');

        foreach ($recipients as $recipient) {
            if (in_array($recipient['email'], $result['delivered'], true)) {
                MeetingParticipant::whereIn('meeting_id', array_map(fn (Meeting $m) => $m->id, $recipient['meetings']))
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($recipient['email'])])
                    ->update(['invitation_status' => 'sent']);
            }
        }

        return $result;
    }

    public function notifyChange(array $recipients): array
    {
        return $this->send($recipients, 'Réunion modifiée', 'La réunion suivante a été modifiée. Voici les informations à jour :');
    }

    public function notifyRemoval(array $recipients): array
    {
        return $this->send($recipients, 'Réunion : participation annulée', 'Vous n\'êtes plus attendu(e) à la réunion suivante :', false);
    }

    public function notifyCancellation(array $recipients, ?string $reason): array
    {
        return $this->send($recipients, 'Réunion annulée', 'La réunion suivante est annulée :', false,
            trim((string) $reason) !== '' ? "Motif : " . trim($reason) : null);
    }

    /**
     * Un message par destinataire.
     *
     * @return array{sent: int, delivered: list<string>, error: string|null}
     */
    private function send(array $recipients, string $subject, string $intro, bool $details = true, ?string $note = null): array
    {
        if ($recipients === []) {
            return ['sent' => 0, 'delivered' => [], 'error' => null];
        }

        $context = reset($recipients)['meetings'][0];
        $smtpError = $this->configureFor($context);
        if ($smtpError !== null) {
            Log::error('E-mail réunion : SMTP indisponible', ['meeting_id' => (string) $context->id, 'error' => $smtpError]);

            return ['sent' => 0, 'delivered' => [], 'error' => $smtpError . ' Configurez le SMTP dans les paramètres administrateur.'];
        }

        @set_time_limit(0);
        $delivered = [];
        $failed = [];
        foreach ($recipients as $recipient) {
            $meeting = $recipient['meetings'][0];
            $body = $this->body($recipient, $intro, $details, $note);
            try {
                Mail::raw($body, fn ($message) => $message->to($recipient['email'])->subject($subject . ' - ' . $meeting->title));
                $delivered[] = $recipient['email'];
            } catch (\Throwable $e) {
                $failed[] = $recipient['email'];
                Log::error('E-mail réunion : échec', ['meeting_id' => (string) $meeting->id, 'recipient' => $recipient['email'], 'error' => $e->getMessage()]);
                // Serveur injoignable ou identifiants refusés : inutile d'essayer tout le monde.
                if ($delivered === [] && count($failed) >= 3) {
                    break;
                }
            }
        }

        $error = null;
        if ($delivered === []) {
            $error = 'Le serveur SMTP de l\'administration a refusé l\'envoi ou est injoignable.';
        } elseif ($failed) {
            $error = 'Échec d\'envoi pour : ' . implode(', ', $failed) . '.';
        }

        return ['sent' => count($delivered), 'delivered' => $delivered, 'error' => $error];
    }

    private function body(array $recipient, string $intro, bool $details, ?string $note): string
    {
        /** @var Meeting $meeting */
        $meeting = $recipient['meetings'][0];
        $meeting->loadMissing(['room', 'organizer']);

        $dates = array_map(function (Meeting $m) {
            $end = $m->ends_at?->isSameDay($m->starts_at) ? $m->ends_at->format('H:i') : $m->ends_at?->format('d/m/Y H:i');

            return $m->starts_at?->format('d/m/Y') . ' de ' . $m->starts_at?->format('H:i') . ' à ' . $end;
        }, $recipient['meetings']);
        if (count($dates) > self::MAX_DATES) {
            $dates = array_merge(array_slice($dates, 0, self::MAX_DATES), ['et ' . (count($dates) - self::MAX_DATES) . ' autre(s) date(s)']);
        }

        $lines = ['Bonjour ' . $recipient['name'] . ',', '', $intro, '', 'Titre : ' . $meeting->title];
        $lines[] = count($dates) === 1 ? 'Date : ' . $dates[0] : "Dates :\n- " . implode("\n- ", $dates);

        if ($details) {
            $lines[] = 'Salle : ' . ($meeting->room?->name ?: 'N/A') . ($meeting->room?->location ? ' (' . $meeting->room->location . ')' : '');
            $lines[] = 'Organisateur : ' . ($meeting->organizer?->name ?: 'N/A');
            if ($meeting->is_virtual && $meeting->meeting_link) {
                $lines[] = 'Lien de visioconférence : ' . $meeting->meeting_link;
            }
            if (trim((string) $meeting->agenda) !== '') {
                array_push($lines, '', 'Ordre du jour :', trim($meeting->agenda));
            }
        }
        if ($note) {
            array_push($lines, '', $note);
        }
        array_push($lines, '', 'Cordialement.');

        return implode("\n", $lines);
    }
}
