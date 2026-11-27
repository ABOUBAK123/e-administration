<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Meeting;
use App\Models\OnlyOfficeServerConfig;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Choisit le serveur OnlyOffice : celui de l'administration s'il est configuré et actif, sinon le serveur global.
 *
 * Le choix dépend de la ressource ouverte (document, modèle, réunion) et non de la personne qui l'ouvre :
 * tous les coéditeurs d'un même fichier doivent passer par le même serveur, sans quoi ils ouvriraient
 * des sessions séparées qui s'écraseraient à l'enregistrement.
 */
class OnlyOfficeServerResolver
{
    public function global(): OnlyOfficeServer
    {
        $settings = AppSetting::whereIn('key', ['onlyoffice_server_url', 'onlyoffice_secret', 'onlyoffice_disable_cert'])
            ->pluck('value', 'key');

        return new OnlyOfficeServer(
            $this->cleanUrl($settings['onlyoffice_server_url'] ?? ''),
            (string) ($settings['onlyoffice_secret'] ?? ''),
            filter_var($settings['onlyoffice_disable_cert'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /** @param string|null $type 'emitter' ou 'recipient' ; null si inconnu */
    public function forAdministration(?string $administrationId, ?string $type = null): OnlyOfficeServer
    {
        if (trim((string) $administrationId) !== '') {
            try {
                $config = OnlyOfficeServerConfig::query()
                    ->where('administration_id', $administrationId)
                    ->when($type, fn ($query) => $query->where('administration_type', $type))
                    ->where('is_active', true)
                    ->first();

                if ($config && $this->cleanUrl($config->server_url) !== '') {
                    return new OnlyOfficeServer(
                        $this->cleanUrl($config->server_url),
                        (string) $config->secret,
                        (bool) $config->disable_cert,
                        true,
                    );
                }
            } catch (QueryException $e) {
                // Migration pas encore exécutée : le serveur global reste utilisable.
                Log::warning('OnlyOffice : configuration par administration indisponible', ['message' => $e->getMessage()]);
            }
        }

        return $this->global();
    }

    public function forUser(?User $user): OnlyOfficeServer
    {
        [$administrationId, $type] = $this->administrationOf($user);

        return $this->forAdministration($administrationId, $type);
    }

    /** Administration émettrice du document, à défaut celle de son auteur, à défaut la destinataire. */
    public function forDocument(Document $document): OnlyOfficeServer
    {
        if (trim((string) $document->issuing_administration_id) !== '') {
            return $this->forAdministration($document->issuing_administration_id, 'emitter');
        }

        $authorId = $document->created_by ?: $document->owner_id;
        [$administrationId, $type] = $this->administrationOf($authorId ? User::find($authorId) : null);
        if ($administrationId !== null) {
            return $this->forAdministration($administrationId, $type);
        }

        return $this->forAdministration($document->recipient_administration_id, 'recipient');
    }

    public function forTemplate(DocumentTemplate $template): OnlyOfficeServer
    {
        return $this->forAdministration($template->administration_id);
    }

    public function forMeeting(Meeting $meeting): OnlyOfficeServer
    {
        if (trim((string) $meeting->issuing_administration_id) !== '') {
            return $this->forAdministration($meeting->issuing_administration_id, 'emitter');
        }

        return $this->forUser($meeting->organizer_id ? User::find($meeting->organizer_id) : null);
    }

    /**
     * Un fichier renvoyé par un callback n'est téléchargé que s'il provient du serveur de la ressource,
     * ou du serveur global (session ouverte avant l'ajout d'un serveur à l'administration).
     */
    public function acceptsDownloadFrom(OnlyOfficeServer $server, string $fileUrl): bool
    {
        if (!in_array(strtolower((string) parse_url($fileUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return false;
        }

        // Aucun serveur configuré : aucune origine n'est digne de confiance.
        $allowed = array_filter(array_map(
            fn (string $url) => strtolower((string) parse_url($url, PHP_URL_HOST)),
            [$server->url, $this->global()->url],
        ));

        return in_array(strtolower((string) parse_url($fileUrl, PHP_URL_HOST)), $allowed, true);
    }

    /**
     * Télécharge un fichier produit par OnlyOffice : origine autorisée (redirections comprises)
     * et certificat vérifié, sauf si la vérification est désactivée pour ce serveur.
     *
     * @throws \Throwable origine refusée, certificat invalide, erreur HTTP ou fichier vide
     */
    public function download(OnlyOfficeServer $server, string $url): string
    {
        $this->assertAllowed($server, $url);
        $response = $this->client($server, 60)->get($url);

        if (!$response->successful() || $response->body() === '') {
            throw new \RuntimeException('Téléchargement impossible (HTTP ' . $response->status() . ')');
        }

        return $response->body();
    }

    /**
     * Fait convertir un fichier par le service de conversion et renvoie le contenu du PDF.
     *
     * @param array $payload requête ConvertService (filetype, outputtype, key, url…)
     *
     * @throws \Throwable serveur injoignable ou non fiable, conversion refusée, PDF non téléchargeable
     */
    public function convertToPdf(OnlyOfficeServer $server, array $payload): string
    {
        $request = $this->client($server, 120)->acceptJson()->asJson();
        if ($server->secret !== '') {
            $request = $request->withToken($this->jwt(['payload' => $payload], $server->secret));
        }

        $response = $request->post($server->url . '/ConvertService.ashx', $payload);
        if (!$response->successful()) {
            throw new \RuntimeException('Conversion refusée (HTTP ' . $response->status() . ')');
        }

        // Réponse JSON, ou XML pour les anciennes versions.
        $data = $response->json();
        if (!is_array($data)) {
            $xml = @simplexml_load_string($response->body());
            $data = $xml ? ['fileUrl' => (string) $xml->FileUrl, 'error' => (string) $xml->Error] : [];
        }

        if ((int) ($data['error'] ?? 0) !== 0 || empty($data['fileUrl'])) {
            throw new \RuntimeException('Conversion impossible (erreur ' . ($data['error'] ?? 'inconnue') . ')');
        }

        return $this->download($server, (string) $data['fileUrl']);
    }

    public function jwt(array $payload, string $secret): string
    {
        $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $unsigned = $encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])) . '.' . $encode(json_encode($payload));

        return $unsigned . '.' . $encode(hash_hmac('sha256', $unsigned, $secret, true));
    }

    private function client(OnlyOfficeServer $server, int $timeout): PendingRequest
    {
        $client = Http::timeout($timeout)->withOptions(['allow_redirects' => [
            'max'         => 3,
            'on_redirect' => fn ($request, $response, $uri) => $this->assertAllowed($server, (string) $uri),
        ]]);

        return $server->disableCert ? $client->withoutVerifying() : $client;
    }

    private function assertAllowed(OnlyOfficeServer $server, string $url): void
    {
        if (!$this->acceptsDownloadFrom($server, $url)) {
            throw new \RuntimeException('Origine non autorisée : ' . (parse_url($url, PHP_URL_HOST) ?: 'inconnue'));
        }
    }

    /** @return array{0: string|null, 1: string|null} [id, type] */
    private function administrationOf(?User $user): array
    {
        if (!$user) {
            return [null, null];
        }

        $assignment = UserDirectionAssignment::where('user_id', $user->id)
            ->whereNotNull('direction_scope_id')
            ->where('direction_scope_id', '!=', '')
            ->orderBy('created_at')
            ->first();
        if ($assignment) {
            return [(string) $assignment->direction_scope_id, $assignment->direction_scope_type === 'recipient' ? 'recipient' : 'emitter'];
        }

        $profile = $user->profile;
        if ($profile && $profile->administration_id) {
            $type = $profile->effective_administration_type ?? $profile->administration_type;

            return [(string) $profile->administration_id, $type === 'recipient' ? 'recipient' : 'emitter'];
        }

        return [null, null];
    }

    private function cleanUrl(?string $url): string
    {
        return rtrim((string) preg_replace('/\s+/', '', (string) $url), '/');
    }
}
