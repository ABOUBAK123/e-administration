<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation à signer un ou plusieurs documents d'un workflow.
 * Chaque document pointe directement vers la plateforme de signature ; le lien de l'application
 * ($fallbackUrl) n'est utilisé que si la plateforme n'a pas pu fournir de lien.
 */
class WorkflowSignatureInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, array{title:string, url:?string}> $documents
     */
    public function __construct(
        public string $recipientName,
        public string $creatorName,
        public string $workflowName,
        public array $documents,
        public string $fallbackUrl
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
