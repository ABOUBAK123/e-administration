<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de notification de workflow (étape assignée, validation, signature, approbation,
 * rejet, document signé disponible). Un seul gabarit professionnel pour tous les événements.
 */
class WorkflowNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string                              $subjectLine Objet de l'e-mail
     * @param string                              $headline    Titre affiché dans l'e-mail
     * @param string                              $tone        info | success | danger | warning
     * @param string                              $recipientName
     * @param array<int, string>                  $paragraphs
     * @param array<string, string>               $details     Libellé => valeur (tableau récapitulatif)
     * @param string|null                         $buttonLabel
     * @param string|null                         $buttonUrl
     * @param array<int, array{label:string,url:string}> $links Liens de téléchargement
     * @param string|null                         $note        Remarque en bas de message
     */
    public function __construct(
        public string $subjectLine,
        public string $headline,
        public string $tone,
        public string $recipientName,
        public array $paragraphs = [],
        public array $details = [],
        public ?string $buttonLabel = null,
        public ?string $buttonUrl = null,
        public array $links = [],
        public ?string $note = null
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.workflow-notification');
    }
}
