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

    /** SMTP de l'administration à laquelle l'utilisateur est rattaché. */
    public static function resolveWorkflowSmtpSetting(?string $userId): ?AdministrationSmtpSetting
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

        // Le gestionnaire de courrier met les transports en cache : on le purge pour que
        // la configuration SMTP de cette administration soit réellement utilisée.
        Mail::purge('smtp');
    }

    /**
     * Applique le SMTP de l'administration de l'utilisateur. Sans configuration (ou incomplète),
     * on garde le courrier par défaut de l'application (.env) au lieu de ne rien envoyer.
     */
    private static function configureWorkflowMailerForUserId(?string $userId): ?string
    {
        $smtp = self::resolveWorkflowSmtpSetting($userId);
        if (!$smtp || !$smtp->mail_host || !$smtp->mail_from_address) {
            Log::notice('Workflow e-mail: SMTP d\'administration absent ou incomplet, courrier par défaut utilisé.', [
                'user_id' => $userId,
                'mailer'  => config('mail.default'),
            ]);
            return null;
        }

        self::applyWorkflowScopedSmtpConfiguration($smtp);
        return null;
    }

    // ── Réglages de l'étape « Notifications » du workflow ────────────────────

    /** Nettoie la configuration reçue du formulaire (adresses valides, événements booléens). */
    public static function sanitizeNotificationConfig($raw): array
    {
        if (!is_array($raw) || empty($raw['enabled'])) {
            return ['enabled' => false];
        }

        $clean = function ($value): array {
            $list = is_array($value) ? $value : preg_split('/[,;\s]+/', (string) $value);
            $list = array_map(fn ($e) => strtolower(trim((string) $e)), $list ?: []);
            return array_values(array_unique(array_filter($list, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
        };

        $stages = [];
        foreach (['onValidationStep', 'onSignatureStep', 'onApproved', 'onRejected', 'onCompleted'] as $key) {
            $stages[$key] = !empty($raw['stages'][$key]);
        }

        return [
            'enabled'          => true,
            'emails'           => $clean($raw['emails'] ?? []),
            'cc'               => $clean($raw['cc'] ?? []),
            'stages'           => $stages,
            'sendDownloadLink' => !empty($raw['sendDownloadLink']),
        ];
    }

    private static function notificationConfigFor(object $workflow): ?array
    {
        $executions = \App\Models\WorkflowExecution::query()->where('workflow_id', $workflow->id)->get(['id', 'step_data']);
        foreach ($executions as $execution) {
            $cfg = is_array($execution->step_data) ? ($execution->step_data['notification_config'] ?? null) : null;
            if (is_array($cfg) && !empty($cfg['enabled'])) {
                return $cfg;
            }
        }
        return null;
    }

    /** Liens sécurisés (7 jours) vers les documents signés du workflow. */
    private static function signedDocumentLinks(object $workflow): array
    {
        $links = [];
        $executions = \App\Models\WorkflowExecution::query()
            ->where('workflow_id', $workflow->id)
            ->whereNotNull('document_id')
            ->with('document')
            ->get();

        foreach ($executions as $execution) {
            $doc = $execution->document;
            if (!$doc || empty($doc->signed_file_path)) {
                continue;
            }
            $links[] = [
                'label' => (string) ($doc->title ?: 'Document signé'),
                'url'   => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'workflows.signed-download', now()->addDays(7), ['executionId' => $execution->id]
                ),
            ];
        }
        return $links;
    }

    /**
     * Envoie l'e-mail « parties prenantes » (destinataires To/Cc de l'étape Notifications) si l'événement est activé.
     * Dédoublonné 60 s par workflow/événement/étape (plusieurs documents = plusieurs exécutions).
     */
    private static function notifyStakeholders(object $workflow, string $stageKey, \Closure $buildMail, string $dedupeKey = ''): void
    {
        try {
            $cfg = self::notificationConfigFor($workflow);
            if (!$cfg || empty($cfg['stages'][$stageKey])) {
                return;
            }

            $to = array_values($cfg['emails'] ?? []);
            $cc = array_values($cfg['cc'] ?? []);
            if (empty($to)) {
                $to = $cc;
                $cc = [];
            }
            if (empty($to)) {
                return;
            }

            if (!\Illuminate\Support\Facades\Cache::add("wf-stage-mail:{$workflow->id}:{$stageKey}:{$dedupeKey}", 1, 60)) {
                return;
            }

            $mailable = $buildMail($cfg);
            if (!$mailable) {
                return;
            }

            self::configureWorkflowMailerForUserId((string) ($workflow->created_by ?? ''));

            $pending = Mail::to($to);
            if (!empty($cc)) {
                $pending->cc($cc);
            }
            $pending->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Workflow e-mail parties prenantes échoué', [
                'workflow_id' => $workflow->id ?? null,
                'stage'       => $stageKey,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private static function userDisplayName(?string $userId): string
    {
        $user = $userId ? User::query()->find($userId) : null;
        return (string) ($user?->full_name ?: $user?->name ?: 'Non renseigné');
    }

    private static function stageMail(
        string $subject, string $headline, string $tone, string $recipient,
        array $paragraphs, array $details, ?string $buttonLabel = null, ?string $buttonUrl = null,
        array $links = [], ?string $note = null
    ): \App\Mail\WorkflowNotificationMail {
        return new \App\Mail\WorkflowNotificationMail(
            $subject, $headline, $tone, $recipient, $paragraphs, $details, $buttonLabel, $buttonUrl, $links, $note
        );
    }

    /**
     * E-mail HTML d'invitation à signer, avec un lien direct vers la plateforme de signature
     * pour chaque document en attente (repli sur le lien de l'application si indisponible).
     */
    private static function buildSignatureInvitationMailable(object $workflow, object $step, string $assigneeId, string $actorName, string $fallbackUrl): ?\App\Mail\WorkflowSignatureInvitationMail
    {
        $user = User::query()->whereKey($assigneeId)->first();
        if (!$user) {
            return null;
        }

        // UN seul lien de signature pour tous les documents du workflow à cette étape.
        $titles = [];
        $signatureUrl = null;
        try {
            $executions = \App\Models\WorkflowExecution::query()
                ->where('workflow_id', $workflow->id)
                ->where('status', 'in_progress')
                ->where('current_step', (int) ($step->order ?? 1))
                ->whereNotNull('document_id')
                ->with('document')
                ->get()
                ->filter(fn ($e) => $e->document);

            foreach ($executions as $execution) {
                $titles[] = (string) ($execution->document->title ?? 'Document');
            }

            if ($executions->isNotEmpty()) {
                $signatureUrl = app(\App\Http\Controllers\SignatureController::class)
                    ->prepareWorkflowSignatureLink($executions, $user, (string) ($workflow->name ?? ''));

                // Échec de génération : lien signé qui créera le lien Safy au clic et redirigera vers la plateforme
                // (jamais le lien de l'application).
                if (!$signatureUrl) {
                    $signatureUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                        'signatures.direct',
                        now()->addDays(7),
                        ['executionId' => $executions->first()->id, 'userId' => $user->id]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Invitation signature: lien direct indisponible', ['workflow_id' => $workflow->id ?? null, 'error' => $e->getMessage()]);
        }

        return new \App\Mail\WorkflowSignatureInvitationMail(
            (string) ($user->full_name ?: $user->name ?: $user->email),
            $actorName,
            (string) ($workflow->name ?? 'Sans nom'),
            $titles,
            $signatureUrl ?: $fallbackUrl
        );
    }

    private static function sendWorkflowEmailToUser(?string $userId, string $subject, string $body, string $context, ?string $actionUrl = null, ?\Illuminate\Mail\Mailable $mailable = null): void
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
            if ($mailable) {
                Mail::to($user->email)->send($mailable);
            } else {
                Mail::raw($body, function ($message) use ($user, $subject): void {
                    $message->to($user->email)->subject($subject);
                });
            }
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
        $stepName = trim((string) ($firstStep->name ?? '')) !== '' ? (string) $firstStep->name : 'Première étape';
        $assigneeName = self::userDisplayName($assigneeId);
        $isSignature = !empty($firstStep->requires_signature);

        $emailBody = "Bonjour,\n\nLe workflow \"{$workflowName}\" a été lancé par {$actorName}.\n\nVous êtes assigné à la première étape pour traiter la demande.\n";

        $mailable = null;
        if ($isSignature) {
            $emailBody = self::buildSignatureInvitationEmailBody($actorName, $workflowName, $actionUrl);
            $subject = 'Demande de signature : ' . $workflowName;
            $mailable = self::buildSignatureInvitationMailable($workflow, $firstStep, $assigneeId, $actorName, $actionUrl);
        } else {
            $subject = sprintf('Workflow démarré : %s', $workflowName);
            $mailable = self::stageMail(
                $subject,
                'Une validation vous est demandée',
                'warning',
                $assigneeName,
                [
                    "Le workflow « {$workflowName} » vient d'être lancé par {$actorName}.",
                    'Vous êtes assigné à la première étape. Merci de l\'examiner et de donner suite dans les meilleurs délais.',
                ],
                ['Workflow' => $workflowName, 'Étape' => $stepName, 'Lancé par' => $actorName, 'Date' => now()->format('d/m/Y à H:i')],
                'Ouvrir mes workflows',
                $actionUrl
            );
        }

        self::sendWorkflowEmailToUser(
            $assigneeId,
            $subject,
            $emailBody,
            'WorkflowExecutionStarted email failed',
            $actionUrl,
            $mailable
        );

        // Parties prenantes (destinataires de l'étape « Notifications »).
        self::notifyStakeholders(
            $workflow,
            $isSignature ? 'onSignatureStep' : 'onValidationStep',
            fn () => self::stageMail(
                ($isSignature ? 'Étape de signature en cours : ' : 'Étape de validation en cours : ') . $workflowName,
                $isSignature ? 'Étape de signature en cours' : 'Étape de validation en cours',
                'info',
                'Madame, Monsieur',
                ["Le workflow « {$workflowName} » vient d'être lancé par {$actorName}."],
                ['Workflow' => $workflowName, 'Étape' => $stepName, 'Assigné à' => $assigneeName, 'Date' => now()->format('d/m/Y à H:i')],
                'Suivre le workflow',
                $actionUrl
            ),
            'step' . (int) ($firstStep->order ?? 1)
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

        $stepName = trim((string) ($nextStep->name ?? '')) !== '' ? (string) $nextStep->name : 'Votre étape';
        $workflowName = (string) ($workflow->name ?? 'Sans nom');
        $actionUrl = route('workflows.index') . '#en-cours';
        $assigneeName = self::userDisplayName($assigneeId);
        $isSignature = !empty($nextStep->requires_signature);
        $emailBody = "Bonjour,\n\nLe workflow \"{$workflowName}\" est maintenant à votre étape \"{$stepName}\".\nIl a été avancé par {$actorName}.\n";

        $mailable = null;
        if ($isSignature) {
            $emailBody = self::buildSignatureInvitationEmailBody($actorName, $workflowName, $actionUrl);
            $subject = 'Demande de signature : ' . $workflowName;
            $mailable = self::buildSignatureInvitationMailable($workflow, $nextStep, $assigneeId, $actorName, $actionUrl);
        } else {
            $subject = sprintf('Étape du workflow : %s', $workflowName);
            $mailable = self::stageMail(
                $subject,
                'Le workflow est arrivé à votre étape',
                'warning',
                $assigneeName,
                [
                    "Le workflow « {$workflowName} » vient d'arriver à votre étape, après l'action de {$actorName}.",
                    'Merci de l\'examiner et de donner suite dans les meilleurs délais.',
                ],
                ['Workflow' => $workflowName, 'Étape' => $stepName, 'Transmis par' => $actorName, 'Date' => now()->format('d/m/Y à H:i')],
                'Ouvrir mes workflows',
                $actionUrl
            );
        }

        self::sendWorkflowEmailToUser(
            $assigneeId,
            $subject,
            $emailBody,
            'WorkflowStepAdvanced email failed',
            $actionUrl,
            $mailable
        );

        // Parties prenantes : étape en cours (validation ou signature).
        self::notifyStakeholders(
            $workflow,
            $isSignature ? 'onSignatureStep' : 'onValidationStep',
            fn () => self::stageMail(
                ($isSignature ? 'Étape de signature en cours : ' : 'Étape de validation en cours : ') . $workflowName,
                $isSignature ? 'Étape de signature en cours' : 'Étape de validation en cours',
                'info',
                'Madame, Monsieur',
                ["Le workflow « {$workflowName} » est passé à l'étape suivante, après l'action de {$actorName}."],
                ['Workflow' => $workflowName, 'Étape' => $stepName, 'Assigné à' => $assigneeName, 'Date' => now()->format('d/m/Y à H:i')],
                'Suivre le workflow',
                $actionUrl
            ),
            'step' . $nextStepOrder
        );

        // Parties prenantes : toutes les validations sont terminées (la suite est une signature).
        try {
            $allSteps = $workflow->steps()->orderBy('order')->get();
            $previous = $allSteps->firstWhere('order', $nextStepOrder - 1);
            $validationsLeft = $allSteps->filter(fn ($s) => (int) $s->order >= $nextStepOrder && empty($s->requires_signature))->count();
            if ($previous && empty($previous->requires_signature) && $validationsLeft === 0) {
                self::notifyStakeholders(
                    $workflow,
                    'onApproved',
                    fn () => self::stageMail(
                        'Workflow approuvé : ' . $workflowName,
                        'Le workflow a été approuvé',
                        'success',
                        'Madame, Monsieur',
                        ["Toutes les étapes de validation du workflow « {$workflowName} » ont été approuvées.", 'Il passe maintenant à l\'étape de signature.'],
                        ['Workflow' => $workflowName, 'Dernière validation par' => $actorName, 'Date' => now()->format('d/m/Y à H:i')],
                        'Suivre le workflow',
                        $actionUrl
                    ),
                    'validations'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Workflow approbation: notification impossible', ['workflow_id' => $workflow->id ?? null, 'error' => $e->getMessage()]);
        }
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
        $actionUrl = route('workflows.index') . '#termine';
        $creatorName = self::userDisplayName($creatorId);

        self::sendWorkflowEmailToUser(
            $creatorId,
            sprintf('Workflow terminé : %s', $workflowName),
            "Bonjour,\n\nLe workflow \"{$workflowName}\" est terminé.\nIl a été clôturé par {$actorName}.\n",
            'WorkflowCompleted email failed',
            $actionUrl,
            self::stageMail(
                sprintf('Workflow terminé : %s', $workflowName),
                'Votre workflow est terminé',
                'success',
                $creatorName,
                ["Le workflow « {$workflowName} » est arrivé au terme de son circuit.", 'Le ou les documents signés sont disponibles dans l\'application.'],
                ['Workflow' => $workflowName, 'Clôturé par' => $actorName, 'Date' => now()->format('d/m/Y à H:i')],
                'Voir le workflow',
                $actionUrl
            )
        );

        // Parties prenantes : circuit sans signature = « approuvé » ; puis « document signé disponible ».
        try {
            $hasSignatureStep = $workflow->steps()->where('requires_signature', true)->exists();
            if (!$hasSignatureStep) {
                self::notifyStakeholders(
                    $workflow,
                    'onApproved',
                    fn () => self::stageMail(
                        'Workflow approuvé : ' . $workflowName,
                        'Le workflow a été approuvé',
                        'success',
                        'Madame, Monsieur',
                        ["Toutes les étapes du workflow « {$workflowName} » ont été approuvées."],
                        ['Workflow' => $workflowName, 'Clôturé par' => $actorName, 'Date' => now()->format('d/m/Y à H:i')],
                        'Voir le workflow',
                        $actionUrl
                    ),
                    'completed'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Workflow approbation: notification impossible', ['workflow_id' => $workflow->id ?? null, 'error' => $e->getMessage()]);
        }

        self::notifyStakeholders(
            $workflow,
            'onCompleted',
            function (array $cfg) use ($workflow, $workflowName, $actorName, $actionUrl) {
                $links = !empty($cfg['sendDownloadLink']) ? self::signedDocumentLinks($workflow) : [];
                return self::stageMail(
                    'Document signé disponible : ' . $workflowName,
                    count($links) > 1 ? 'Les documents signés sont disponibles' : 'Le document signé est disponible',
                    'success',
                    'Madame, Monsieur',
                    ["Le circuit du workflow « {$workflowName} » est terminé : le ou les documents signés sont disponibles."],
                    ['Workflow' => $workflowName, 'Clôturé par' => $actorName, 'Date' => now()->format('d/m/Y à H:i')],
                    'Voir le workflow',
                    $actionUrl,
                    $links
                );
            },
            'completed'
        );
    }

    /**
     * Workflow rejeté : e-mail au créateur et aux parties prenantes (si l'événement est activé).
     */
    public static function workflowRejected(object $workflow, string $actorName, ?object $execution = null, string $reason = '', ?string $stepName = null): void
    {
        $workflowName = (string) ($workflow->name ?? 'Sans nom');
        $actionUrl = route('workflows.index') . '#rejete';
        $reason = trim($reason);
        $details = ['Workflow' => $workflowName, 'Rejeté par' => $actorName];
        if ($stepName) {
            $details['Étape'] = $stepName;
        }
        if ($reason !== '') {
            $details['Motif'] = $reason;
        }
        $details['Date'] = now()->format('d/m/Y à H:i');

        $creatorId = (string) ($workflow->created_by ?? '');
        if ($creatorId !== '') {
            self::sendWorkflowEmailToUser(
                $creatorId,
                'Workflow rejeté : ' . $workflowName,
                "Bonjour,\n\nLe workflow \"{$workflowName}\" a été rejeté par {$actorName}.\n",
                'WorkflowRejected email failed',
                $actionUrl,
                self::stageMail(
                    'Workflow rejeté : ' . $workflowName,
                    'Votre workflow a été rejeté',
                    'danger',
                    self::userDisplayName($creatorId),
                    ["Le workflow « {$workflowName} » a été rejeté par {$actorName}.", 'Vous pouvez consulter le motif et relancer un nouveau workflow si nécessaire.'],
                    $details,
                    'Voir le workflow',
                    $actionUrl
                )
            );
        }

        self::notifyStakeholders(
            $workflow,
            'onRejected',
            fn () => self::stageMail(
                'Workflow rejeté : ' . $workflowName,
                'Le workflow a été rejeté',
                'danger',
                'Madame, Monsieur',
                ["Le workflow « {$workflowName} » a été rejeté par {$actorName}."],
                $details,
                'Voir le workflow',
                $actionUrl
            ),
            'rejected' . ($execution->id ?? '')
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
