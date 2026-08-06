<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Meeting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Génère une proposition de compte rendu de réunion à partir d'un
 * enregistrement audio, via l'API gratuite Google Gemini (clé API
 * obtenue sur https://aistudio.google.com/app/apikey).
 *
 * Le modèle transcrit l'audio et produit directement un compte rendu
 * structuré en une seule requête (Gemini accepte l'audio en entrée
 * multimodale). Le texte retourné est une PROPOSITION à relire et
 * corriger par le rédacteur avant enregistrement définitif.
 */
class MeetingAiMinutesService
{
    private const MAX_AUDIO_BYTES = 20 * 1024 * 1024; // 20 Mo (limite raisonnable pour l'envoi inline)

    private const ALLOWED_MIME_PREFIXES = ['audio/', 'video/webm'];

    /**
     * @throws \RuntimeException si la configuration est incomplète, si le
     *                           fichier est invalide, ou si l'appel à l'API échoue.
     */
    public function generateFromAudio(UploadedFile $audio, Meeting $meeting): string
    {
        $apiKey = $this->resolveSetting('gemini_api_key', (string) config('services.gemini.api_key', ''));
        if ($apiKey === '') {
            throw new \RuntimeException(
                'La clé API Gemini n\'est pas configurée. ' .
                'Obtenez une clé gratuite sur https://aistudio.google.com/app/apikey puis renseignez-la dans ' .
                'Administration > Intelligence Artificielle (ou la variable GEMINI_API_KEY du fichier .env).'
            );
        }

        if ($audio->getSize() === false || $audio->getSize() > self::MAX_AUDIO_BYTES) {
            throw new \RuntimeException('L\'enregistrement audio dépasse la taille maximale autorisée (20 Mo).');
        }

        $mimeType = (string) ($audio->getMimeType() ?: 'audio/webm');
        $isAllowed = false;
        foreach (self::ALLOWED_MIME_PREFIXES as $prefix) {
            if (str_starts_with($mimeType, $prefix)) {
                $isAllowed = true;
                break;
            }
        }
        if (!$isAllowed) {
            throw new \RuntimeException("Format audio non pris en charge ({$mimeType}).");
        }

        $audioData = file_get_contents($audio->getRealPath());
        if ($audioData === false) {
            throw new \RuntimeException('Impossible de lire le fichier audio enregistré.');
        }

        $model = $this->resolveSetting('gemini_model', (string) config('services.gemini.model', 'gemini-2.0-flash'));
        $timeout = (int) $this->resolveSetting('gemini_timeout', (string) config('services.gemini.timeout', 120));
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $prompt = $this->buildPrompt($meeting);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($endpoint . '?key=' . $apiKey, [
                    'contents' => [[
                        'parts' => [
                            ['text' => $prompt],
                            ['inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => base64_encode($audioData),
                            ]],
                        ],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.3,
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('MeetingAiMinutesService: échec réseau lors de l\'appel Gemini.', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Impossible de contacter le service IA (Gemini) : ' . $e->getMessage(), previous: $e);
        }

        if (!$response->successful()) {
            $errorMessage = (string) ($response->json('error.message') ?: $response->body());
            Log::error('MeetingAiMinutesService: réponse Gemini en erreur.', [
                'meeting_id' => $meeting->id,
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);
            throw new \RuntimeException("Le service IA a retourné une erreur (HTTP {$response->status()}) : {$errorMessage}");
        }

        $text = (string) $response->json('candidates.0.content.parts.0.text', '');
        $text = trim($text);

        if ($text === '') {
            Log::error('MeetingAiMinutesService: réponse Gemini vide ou inattendue.', [
                'meeting_id' => $meeting->id,
                'raw' => $response->json(),
            ]);
            throw new \RuntimeException('Le service IA n\'a retourné aucun contenu exploitable. Réessayez ou vérifiez la qualité de l\'enregistrement.');
        }

        return $text;
    }

    /**
     * Renvoie la valeur configurée en base (Administration > Intelligence Artificielle)
     * si elle est renseignée, sinon retombe sur la valeur fournie (config/.env).
     */
    private function resolveSetting(string $key, string $fallback): string
    {
        $value = AppSetting::where('key', $key)->value('value');

        return ($value !== null && trim($value) !== '') ? trim($value) : $fallback;
    }

    private function buildPrompt(Meeting $meeting): string
    {
        $title = (string) $meeting->title;
        $date = (string) optional($meeting->starts_at)->format('d/m/Y H:i');

        return <<<PROMPT
Tu es un assistant qui rédige des comptes rendus de réunion en français administratif.
Écoute attentivement cet enregistrement audio de la réunion "{$title}" (date : {$date}) et
transcris-le fidèlement, puis propose un compte rendu structuré avec les sections suivantes :

1. Points abordés (résumé synthétique par thème)
2. Décisions prises
3. Actions à suivre (avec responsable et échéance si mentionnés)

Réponds uniquement avec le texte du compte rendu structuré (pas de préambule, pas de balises Markdown superflues).
Si l'audio est inaudible ou vide, indique clairement "Aucun contenu exploitable détecté dans l'enregistrement."
PROMPT;
    }
}
