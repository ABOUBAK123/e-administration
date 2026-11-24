<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation à signer les documents d'un workflow.
 * Un seul lien : $signatureUrl mène directement à la plateforme de signature (tous les documents du workflow),
 * ou, en repli, à l'application si la plateforme n'a pas pu fournir de lien.
 */
class WorkflowSignatureInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, string> $documentTitles
     */
    public function __construct(
        public string $recipientName,
        public string $creatorName,
        public string $workflowName,
        public array $documentTitles,
        public string $signatureUrl
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Demande de signature : ' . $this->workflowName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workflow-signature-invitation',
        );
    }
}
