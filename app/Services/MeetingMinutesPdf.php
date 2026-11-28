<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Meeting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Version PDF du compte rendu, figée à la validation : conservée hors du dossier public,
 * avec son empreinte pour détecter toute altération.
 */
class MeetingMinutesPdf
{
    private const DISK = 'local';

    /**
     * Génère et enregistre le PDF. Les colonnes sont renseignées sur la réunion,
     * l'appelant l'enregistre.
     *
     * @throws \RuntimeException message destiné à l'utilisateur
     */
    public function freeze(Meeting $meeting): void
    {
        $content = $this->render($meeting);
        $path = 'meetings/minutes/' . $meeting->id . '/compte_rendu_' . now()->format('Ymd_His') . '_' . Str::random(6) . '.pdf';

        if (!Storage::disk(self::DISK)->put($path, $content)) {
            throw new \RuntimeException('le PDF n\'a pas pu être enregistré sur le serveur.');
        }

        $meeting->minutes_pdf_path = $path;
        $meeting->minutes_pdf_sha256 = hash('sha256', $content);
    }

    /**
     * Contenu du PDF figé. Un compte rendu validé avant la mise en place du PDF est figé au premier accès.
     *
     * @throws \RuntimeException PDF absent, altéré ou impossible à produire
     */
    public function content(Meeting $meeting): string
    {
        if (!$meeting->minutes_pdf_path) {
            $this->freeze($meeting);
            $meeting->save();
        }

        $content = Storage::disk(self::DISK)->exists($meeting->minutes_pdf_path)
            ? (string) Storage::disk(self::DISK)->get($meeting->minutes_pdf_path)
            : '';

        if ($content === '' || !hash_equals((string) $meeting->minutes_pdf_sha256, hash('sha256', $content))) {
            Log::error('Compte rendu validé : PDF absent ou altéré', ['meeting_id' => (string) $meeting->id, 'path' => $meeting->minutes_pdf_path]);
            throw new \RuntimeException('le PDF validé du compte rendu est introuvable ou a été altéré. Contactez l\'administrateur.');
        }

        return $content;
    }

    public function fileName(Meeting $meeting): string
    {
        return 'compte_rendu_' . (Str::slug($meeting->title) ?: 'reunion') . '.pdf';
    }

    /** Le contenu libre fait le compte rendu ; s'il est vide, c'est le modèle Word. */
    private function render(Meeting $meeting): string
    {
        if (trim((string) $meeting->minutes_content) !== '') {
            $meeting->loadMissing(['room', 'validatedByUser']);

            return Pdf::loadView('meetings.minutes_pdf', ['meeting' => $meeting])
                ->setOption('isFontSubsettingEnabled', true)
                ->output();
        }

        return $this->convertWordMinutes($meeting);
    }

    private function convertWordMinutes(Meeting $meeting): string
    {
        $path = (string) $meeting->minutes_template;
        $relative = str_starts_with($path, '/storage/') ? ltrim(substr($path, strlen('/storage/')), '/') : '';
        if ($relative === '' || !Storage::disk('public')->exists($relative)) {
            throw new \RuntimeException('le compte rendu est vide. Rédigez-le puis enregistrez une version.');
        }

        if ($this->hasUnfilledVariables(Storage::disk('public')->path($relative))) {
            throw new \RuntimeException('le compte rendu est vide et le modèle Word contient encore des variables {{ }} non remplies.');
        }

        $resolver = app(OnlyOfficeServerResolver::class);
        $server = $resolver->forMeeting($meeting);
        if ($server->url === '') {
            throw new \RuntimeException('OnlyOffice n\'est pas configuré : le modèle Word ne peut pas être converti en PDF.');
        }

        // Même lien temporaire signé que pour l'éditeur : OnlyOffice lit le fichier sans session.
        $expires = now()->addMinutes(15)->timestamp;
        $signedPath = route('meetings.template.file', [
            'meeting' => $meeting->id,
            'expires' => $expires,
            'access'  => hash_hmac('sha256', 'tpl|' . $meeting->id . '|' . $expires, (string) config('app.key')),
        ], false);
        $appUrl = (string) AppSetting::where('key', 'app_public_url')->value('value');
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION) ?: 'docx');

        try {
            $content = $resolver->convertToPdf($server, [
                'async' => false,
                'embeddedfonts' => true,
                'filetype' => $extension,
                'key' => md5($meeting->id . hash_file('sha256', Storage::disk('public')->path($relative)) . $expires),
                'outputtype' => 'pdf',
                'title' => $this->fileName($meeting),
                'url' => $appUrl !== '' ? rtrim($appUrl, '/') . $signedPath : url($signedPath),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Compte rendu : conversion PDF impossible', ['meeting_id' => (string) $meeting->id, 'error' => $e->getMessage()]);
            throw new \RuntimeException('la conversion du modèle Word en PDF par OnlyOffice a échoué. Réessayez ou vérifiez la configuration OnlyOffice.');
        }

        if (!str_starts_with($content, '%PDF')) {
            throw new \RuntimeException('OnlyOffice n\'a pas renvoyé un PDF valide.');
        }

        return $content;
    }

    private function hasUnfilledVariables(string $docxPath): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return false;
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        // Word peut découper une variable en plusieurs fragments : on recolle le texte par paragraphe.
        $text = strip_tags(str_replace('</w:p>', "\n", $xml));

        return (bool) preg_match('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/u', $text);
    }
}
