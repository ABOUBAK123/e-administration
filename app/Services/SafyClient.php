<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client de l'API Safy (Trusted Service + Document Service).
 *
 * Remplace les appels SunnyStamp de SignatureController.
 * Flux : createWorkflow -> uploadDocument -> addSignatureAnnotation -> start
 *        -> getWorkflow (polling) -> downloadSignedDocuments.
 *
 * Toutes les méthodes lèvent une SafyException en cas d'erreur HTTP.
 */
class SafyClient
{
    public function __construct(
        private string $trustedServiceUrl,   // ex: https://trusted-service.safy.ma/
        private string $apiToken,
        private string $spaceId,
        private string $signaturePolicyId,
        private int $timeout = 30,
        private bool $verifySsl = true,
    ) {
        $this->trustedServiceUrl = rtrim($trustedServiceUrl, '/') . '/';
    }

    /** Construit le client depuis config/services.php (clé "safy"). */
    public static function fromConfig(array $overrides = []): self
    {
        $c = array_merge(config('services.safy', []), $overrides);

        return new self(
            (string) ($c['url'] ?? ''),
            (string) ($c['token'] ?? ''),
            (string) ($c['space'] ?? ''),
            (string) ($c['signature_policy'] ?? ''),
            (int) ($c['timeout'] ?? 30),
            (bool) ($c['verify_ssl'] ?? true),
        );
    }

    // ------------------------------------------------------------------
    // 1. Workflow
    // ------------------------------------------------------------------

    /**
     * @param array $signers  [['email'=>, 'firstName'=>, 'lastName'=>, 'phone'=>?], ...]
     * @param array $options  invitationMode, authenticationMode, locale, forceViewDocuments,
     *                        sendDownloadLinkOnFinished, orderedSigners, sendAutomatedInvitations, seal
     * @return array          réponse complète (id, signers[].id, ...)
     */
    public function createWorkflow(string $shortName, array $signers, array $options = []): array
    {
        $defaults = [
            'invitationMode'             => 'noInvitation', // on génère le lien nous-mêmes
            'authenticationMode'         => 'noAuthentication', // pas d'OTP à l'ouverture du lien (l'OTP de signature dépend de la politique)
            'locale'                     => 'fr',
            'forceViewDocuments'         => true,
            'sendDownloadLinkOnFinished' => false,
        ];
        $opt = array_merge($defaults, array_intersect_key($options, $defaults));

        $payload = [
            'space'     => $this->spaceId,
            'shortName' => mb_substr($shortName, 0, 255),
            'signers'   => array_map(function (array $s) use ($opt) {
                return array_filter([
                    'firstName'                  => $s['firstName'] ?? 'Utilisateur',
                    'lastName'                   => $s['lastName'] ?? '-',
                    'email'                      => $s['email'],
                    'phone'                      => $s['phone'] ?? null,
                    'locale'                     => $opt['locale'],
                    'signaturePolicy'            => $s['signaturePolicy'] ?? $this->signaturePolicyId,
                    'invitationMode'             => $opt['invitationMode'],
                    'authenticationMode'         => $opt['authenticationMode'],
                    'forceViewDocuments'         => $opt['forceViewDocuments'],
                    'sendDownloadLinkOnFinished' => $opt['sendDownloadLinkOnFinished'],
                ], fn($v) => $v !== null && $v !== '');
            }, $signers),
        ];

        foreach (['orderedSigners', 'sendAutomatedInvitations', 'seal'] as $k) {
            if (array_key_exists($k, $options)) {
                $payload[$k] = $options[$k];
            }
        }

        return $this->json($this->trusted()->post('api/workflows', $payload), 'create_workflow');
    }

    public function getWorkflow(string $workflowId): array
    {
        return $this->json($this->trusted()->get("api/workflows/{$workflowId}"), 'get_workflow');
    }

    /** Statut global : draft | started | finished (autres valeurs possibles selon Safy). */
    public function getWorkflowStatus(string $workflowId): string
    {
        return (string) ($this->getWorkflow($workflowId)['workflowStatus'] ?? '');
    }

    /** signed | refused | null pour un signataire donné. */
    public function getSignerStatus(string $workflowId, string $signerId): ?string
    {
        foreach ($this->getWorkflow($workflowId)['signers'] ?? [] as $s) {
            if (($s['id'] ?? null) === $signerId) {
                return $s['signatureStatus'] ?? null;
            }
        }
        return null;
    }

    public function start(string $workflowId, ?string $sealKeyPassword = null): array
    {
        $payload = ['workflowStatus' => 'started'];
        if ($sealKeyPassword) {
            $payload['sealKeyPassword'] = $sealKeyPassword;
        }
        return $this->json($this->trusted()->patch("api/workflows/{$workflowId}", $payload), 'start_workflow');
    }

    /** Lien de signature (à utiliser avec invitationMode=noInvitation). */
    public function createSignatureLink(string $workflowId, string $signerId, ?string $redirectUrl = null, ?string $etag = null): ?string
    {
        $body = $redirectUrl ? ['redirectUrl' => $redirectUrl] : (object) [];
        $path = "api/workflows/{$workflowId}/signers/{$signerId}/signatureLinks";

        // If-Match n'est pas exigé en préproduction : on essaie sans, puis avec l'ETag courant si Safy refuse (409/412/428).
        $req = $etag ? $this->trusted()->withHeaders(['If-Match' => $etag]) : $this->trusted();
        $resp = $req->post($path, $body);

        if (in_array($resp->status(), [409, 412, 428], true)) {
            $r = $this->trusted()->get("api/workflows/{$workflowId}");
            if ($r->successful() && ($tag = $r->header('ETag'))) {
                $resp = $this->trusted()->withHeaders(['If-Match' => $tag])->post($path, $body);
            }
        }

        return $this->json($resp, 'signature_link')['signatureLink'] ?? null;
    }

    // ------------------------------------------------------------------
    // 2. Documents (Document Service)
    // ------------------------------------------------------------------

    /** @return array{token:string, url:string} */
    public function createDocumentToken(string $workflowId, bool $readWrite): array
    {
        $data = $this->json(
            $this->trusted()->post("api/workflows/{$workflowId}/documentTokens", ['readWrite' => $readWrite]),
            'document_token'
        );

        return [
            'token' => (string) ($data['tokenValue'] ?? ''),
            'url'   => rtrim((string) ($data['documentServiceUrl'] ?? ''), '/') . '/',
        ];
    }

    /**
     * Upload d'un PDF. Retourne l'ID du document.
     * Le champ multipart est "file" (Postman) ; le guide mentionne "pdfDocument".
     * Si Safy rejette "file", essayer $fieldName = 'pdfDocument'.
     */
    public function uploadDocument(string $workflowId, string $pdfBytes, string $fileName, string $fieldName = 'file'): string
    {
        $tok = $this->createDocumentToken($workflowId, true);

        $resp = $this->http($tok['token'])
            ->attach($fieldName, $pdfBytes, $fileName, ['Content-Type' => 'application/pdf'])
            ->post($tok['url'] . 'api/documents');

        $data = $this->json($resp, 'upload_document');
        $id = $data[0]['id'] ?? null;
        if (!$id) {
            throw new SafyException('upload_document: aucun id dans la réponse');
        }
        return $id;
    }

    /**
     * Zone de signature en points PDF.
     * Coordonnées Safy : x1,y1 = coin haut-gauche ; x2,y2 = coin bas-droit (origine en HAUT à gauche).
     * Confirmé par un test réel.
     */
    public function addSignatureAnnotation(string $documentId, string $signerId, int $page, float $x1, float $y1, float $x2, float $y2): array
    {
        $payload = ['signatureAnnotations' => [[
            'signer' => $signerId,
            'page'   => max(1, $page),
            'x1' => (int) round($x1), 'y1' => (int) round($y1),
            'x2' => (int) round($x2), 'y2' => (int) round($y2),
        ]]];

        return $this->json($this->trusted()->patch("api/documents/{$documentId}", $payload), 'add_annotation');
    }

    /**
     * Convertit une zone en pourcentages (origine en haut à gauche, comme l'UI de l'app)
     * en coordonnées Safy (points PDF, même origine en haut à gauche). Page A4 par défaut.
     */
    public static function percentZoneToAnnotation(array $zone, float $pageW = 595.0, float $pageH = 842.0): array
    {
        $x = (float) ($zone['x'] ?? 0);
        $y = (float) ($zone['y'] ?? 0);
        $w = (float) ($zone['w'] ?? $zone['width'] ?? 20);
        $h = (float) ($zone['h'] ?? $zone['height'] ?? 8);
        $page = (int) ($zone['page'] ?? 1);

        $x1 = ($x / 100) * $pageW;
        $x2 = min($pageW, $x1 + ($w / 100) * $pageW);
        $y1 = ($y / 100) * $pageH;
        $y2 = min($pageH, $y1 + ($h / 100) * $pageH);

        return ['page' => $page <= 0 ? 1 : $page, 'x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2];
    }

    /** Binaire du/des document(s) signé(s) : PDF seul, ou ZIP si plusieurs. */
    public function downloadSignedDocuments(string $workflowId): array
    {
        $tok = $this->createDocumentToken($workflowId, false);
        $resp = $this->http($tok['token'])->get($tok['url'] . 'api/documents');

        if (!$resp->successful()) {
            throw new SafyException('download: HTTP ' . $resp->status() . ' ' . mb_substr($resp->body(), 0, 300));
        }

        return [
            'body'         => $resp->body(),
            'content_type' => $resp->header('Content-Type'),
            'is_zip'       => str_contains((string) $resp->header('Content-Type'), 'zip'),
        ];
    }

    // ------------------------------------------------------------------
    // 3. Sceau (sans workflow)
    // ------------------------------------------------------------------

    public function applySeal(string $sealId, string $pdfBytes, ?array $sealAnnotation = null, ?string $password = null): string
    {
        $params = array_filter(['sealAnnotation' => $sealAnnotation, 'sealKeyPassword' => $password]);

        $req = $this->trusted();
        if ($params) {
            $req = $req->attach('jsonParameters', json_encode($params), null, ['Content-Type' => 'application/json']);
        }
        $resp = $req->attach('pdfDocument', $pdfBytes, 'document.pdf', ['Content-Type' => 'application/pdf'])
            ->post("api/seals/{$sealId}/apply");

        if (!$resp->successful()) {
            throw new SafyException('apply_seal: HTTP ' . $resp->status() . ' ' . mb_substr($resp->body(), 0, 300));
        }
        return $resp->body();
    }

    // ------------------------------------------------------------------
    // Internes
    // ------------------------------------------------------------------

    private function http(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->timeout($this->timeout)
            ->when(!$this->verifySsl, fn($h) => $h->withoutVerifying())
            ->acceptJson();
    }

    private function trusted(): PendingRequest
    {
        return $this->http($this->apiToken)->baseUrl($this->trustedServiceUrl)->asJson();
    }

    private function json($resp, string $step): array
    {
        if (!$resp->successful()) {
            throw new SafyException("{$step}: HTTP {$resp->status()} " . mb_substr($resp->body(), 0, 300), $resp->status());
        }
        return (array) $resp->json();
    }
}
