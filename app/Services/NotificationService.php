<?php

namespace App\Services;

use App\Models\AdministrationSmtpSetting;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Notification générique.
     */
    public static function notify(
        string $recipientId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?string $workflowId = null,
        ?string $executionId = null
    ): void {
        if (trim($recipientId) === '') {
            return;
        }

        Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_id' => $recipientId,
            'type' => self::sanitizeType($type),
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'workflow_id' => $workflowId,
            'execution_id' => $executionId,
            'is_read' => false,
        ]);
    }

    private static function resolveWorkflowSmtpSetting(?string $userId): ?AdministrationSmtpSetting
    {
        $normalizedUserId = trim((string) ($userId ?? ''));
        if ($normalizedUserId === '') {
            return null;
        }

        $assignment = UserDirectionAssignment::query()
            ->where('user_id', $normalizedUserId)
            ->first();

        if ($assignment && $assignment->direction_scope_id) {
            $type = $assignment->direction_scope_type === 'recipient' ? 'recipient' : 'emitter';
            $smtp = AdministrationSmtpSetting::forAdministration((string) $assignment->direction_scope_id, $type);
            if ($smtp) {
                return $smtp;
            }
        }

        $user = User::query()->with('profile')->find($normalizedUserId);
        if ($user?->profile && $user->profile->administration_id) {
            $type = (string) ($user->profile->effective_administration_type ?? $user->profile->administration_type ?? 'emitter');
            $normalizedType = strtolower(trim($type)) === 'recipient' ? 'recipient' : 'emitter';
            $smtp = AdministrationSmtpSetting::forAdministration((string) $user->profile->administration_id, $normalizedType);
            if ($smtp) {
                return $smtp;
            }
        }

        return null;
    }

    private static function applyWorkflowScopedSmtpConfiguration(AdministrationSmtpSetting $smtp): void
    {
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
    }

    private static function configureWorkflowMailerForUserId(?string $userId): ?string
    {
        $smtp = self::resolveWorkflowSmtpSetting($userId);
        if (!$smtp) {
            return 'Aucune configuration SMTP d\'administration n\'a ete trouvee pour l\'utilisateur.';
        }

        if (!$smtp->mail_host || !$smtp->mail_from_address) {
            return 'La configuration SMTP d\'administration est incomplete (hote ou expediteur manquant).';
        }

        self::applyWorkflowScopedSmtpConfiguration($smtp);
        return null;
    }

    private static function sendWorkflowEmailToUser(?string $userId, string $subject, string $body, string $context, ?string $actionUrl = null): void
    {
        $normalizedUserId = trim((string) ($userId ?? ''));
        if ($normalizedUserId === '') {
            return;
        }

        $user = User::query()->whereKey($normalizedUserId)->first();
        if (!$user || !trim((string) ($user->email ?? '')) || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $smtpError = self::configureWorkflowMailerForUserId($normalizedUserId);
        if ($smtpError !== null) {
            Log::error($context, [
                'recipient_user_id' => $normalizedUserId,
                'recipient_email' => $user->email,
                'error' => $smtpError,
            ]);
            return;
        }

        try {
            Mail::raw($body, function ($message) use ($user, $subject): void {
                $message->to($user->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error($context, [
                'recipient_user_id' => $normalizedUserId,
                'recipient_email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function buildSignatureInvitationEmailBody(string $creatorName, string $workflowName, ?string $actionUrl = null): string
    {
        $body = "Bonjour,\n\nVous venez de recevoir une demande de signature sur la plateforme E-administration de la part de {$creatorName} concernant le workflow \"{$workflowName}\".\n\nAfin de signer le(s) document(s) en question, cliquez sur le lien ci-dessous ou recopiez-le dans la barre d'adresse de votre navigateur :\n";

        if (is_string($actionUrl) && trim($actionUrl) !== '') {
            $body .= trim($actionUrl) . "\n";
        }

        return $body . "\n";
    }

    /**
     * Notifie un utilisateur qu'un template lui a ete partage.
     */
    public static function templateShared(object $template, string $recipientId, string $sharedByName): void
    {
        self::notify(
            recipientId: $recipientId,
            type: 'info',
            title: 'Template partage',
            message: sprintf('Le template "%s" vous a ete partage par %s.', $template->name ?? 'Sans nom', $sharedByName),
            actionUrl: route('shared-templates.index')
        );
    }

    /**
     * Notifie le destinataire d'un message chat direct.
     */
    public static function chatMessageReceived(object $message, string $senderName): void
    {
        $recipientId = (string) ($message->recipient_id ?? '');
        $senderId = (string) ($message->sender_id ?? '');
        if ($recipientId === '' || $recipientId === $senderId) {
            return;
        }

        self::notify(
            recipientId: $recipientId,
            type: 'info',
            title: 'Nouveau message',
            message: sprintf('%s vous a envoye un message.', $senderName),
            actionUrl: route('chat.index')
        );
    }

    /**
     * Notifie les assignees des etapes lors de la creation d'un workflow.
     */
    public static function workflowStepsAssigned(object $workflow, iterable $steps, string $actorName): void
    {
        $notified = [];
        foreach ($steps as $step) {
            $assigneeId = (string) ($step->assignee_id ?? '');
            if ($assigneeId === '' || isset($notified[$assigneeId])) {
                continue;
            }

            $notified[$assigneeId] = true;

            self::notify(
                recipientId: $assigneeId,
                type: 'workflow',
                title: 'Etape de workflow assignee',
                message: sprintf('Vous etes assigne a une etape du workflow "%s" par %s.', $workflow->name ?? 'Sans nom', $actorName),
                actionUrl: route('workflows.index') . '#en-cours',
                workflowId: (string) ($workflow->id ?? null)
            );
        }
    }

    /**
     * Notifie l'assigne de la premiere etape a l'execution.
     */
    public static function workflowExecutionStarted(object $workflow, object $firstStep, string $actorName): void
    {
        $assigneeId = (string) ($firstStep->assignee_id ?? '');
        if ($assigneeId === '') {
            return;
        }

        self::notify(
            recipientId: $assigneeId,
            type: 'workflow',
            title: 'Workflow demarre',
            message: sprintf('Le workflow "%s" a ete lance par %s.', $workflow->name ?? 'Sans nom', $actorName),
            actionUrl: route('workflows.index') . '#en-cours',
            workflowId: (string) ($workflow->id ?? null)
        );

        $workflowName = (string) ($workflow->name ?? 'Sans nom');
        $actionUrl = route('workflows.index') . '#en-cours';
        $emailBody = "Bonjour,\n\nLe workflow \"{$workflowName}\" a été lancé par {$actorName}.\n\nVous êtes assigné à la première étape pour traiter la demande.\n";

        if (!empty($firstStep->requires_signature)) {
            $emailBody = self::buildSignatureInvitationEmailBody($actorName, $workflowName, $actionUrl);
            $subject = 'Demande de signature sur la plateforme';
        } else {
            $subject = sprintf('Workflow démarré : %s', $workflowName);
        }

        self::sendWorkflowEmailToUser(
            $assigneeId,
            $subject,
            $emailBody,
            'WorkflowExecutionStarted email failed',
            $actionUrl
        );
    }

    /**
     * Notifie l'assigne de la prochaine etape lors d'une avancee.
     */
    public static function workflowStepAdvanced(object $workflow, int $nextStepOrder, string $actorName): void
    {
        $nextStep = null;
        if (method_exists($workflow, 'steps')) {
            $nextStep = $workflow->steps()->where('order', $nextStepOrder)->first();
        }

        $assigneeId = (string) ($nextStep->assignee_id ?? '');
        if ($assigneeId === '') {
            return;
        }

        self::notify(
            recipientId: $assigneeId,
            type: 'workflow',
            title: 'Etape suivante du workflow',
            message: sprintf('Le workflow "%s" a avance a votre etape par %s.', $workflow->name ?? 'Sans nom', $actorName),
            actionUrl: route('workflows.index') . '#en-cours',
            workflowId: (string) ($workflow->id ?? null)
        );

        $stepName = trim((string) ($nextStep->name ?? '')) !== '' ? $nextStep->name : 'votre étape';
        $workflowName = (string) ($workflow->name ?? 'Sans nom');
        $actionUrl = route('workflows.index') . '#en-cours';
        $emailBody = "Bonjour,\n\nLe workflow \"{$workflowName}\" est maintenant à votre étape \"{$stepName}\".\nIl a été avancé par {$actorName}.\n";

        if (!empty($nextStep->requires_signature)) {
            $emailBody = self::buildSignatureInvitationEmailBody($actorName, $workflowName, $actionUrl);
            $subject = 'Demande de signature sur la plateforme';
        } else {
            $subject = sprintf('Étape du workflow : %s', $workflowName);
        }

        self::sendWorkflowEmailToUser(
            $assigneeId,
            $subject,
            $emailBody,
            'WorkflowStepAdvanced email failed',
            $actionUrl
        );
    }

    public static function workflowCompleted(object $workflow, string $actorName): void
    {
        $creatorId = (string) ($workflow->created_by ?? '');
        if ($creatorId === '') {
            return;
        }

        self::notify(
            recipientId: $creatorId,
            type: 'workflow',
            title: 'Workflow terminé',
            message: sprintf('Le workflow "%s" a ete termine par %s.', $workflow->name ?? 'Sans nom', $actorName),
            actionUrl: route('workflows.index') . '#termine',
            workflowId: (string) ($workflow->id ?? null)
        );

        $workflowName = (string) ($workflow->name ?? 'Sans nom');
        self::sendWorkflowEmailToUser(
            $creatorId,
            sprintf('Workflow terminé : %s', $workflowName),
            "Bonjour,\n\nLe workflow \"{$workflowName}\" est terminé.\nIl a été clôturé par {$actorName}.\n",
            'WorkflowCompleted email failed'
        );
    }

    /**
     * Limite les types aux valeurs enum de la table notifications.
     */
    private static function sanitizeType(string $type): string
    {
        $allowed = ['info', 'validation', 'signature', 'workflow', 'system'];
        $normalized = strtolower(trim($type));

        return in_array($normalized, $allowed, true) ? $normalized : 'info';
    }
}
