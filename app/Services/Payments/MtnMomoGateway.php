<?php

namespace App\Services\Payments;

use App\Models\MobileMoneyProviderConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Intégration MTN Mobile Money (Collection / RequestToPay).
 * Doc : https://momodeveloper.mtn.com/api-documentation
 */
class MtnMomoGateway implements MobileMoneyGateway
{
    public function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $national = str_starts_with($digits, '225') ? substr($digits, 3) : $digits;
        $national = ltrim($national, '0');

        return '225' . $national;
    }

    private function baseUrl(MobileMoneyProviderConfig $config): string
    {
        return rtrim((string) $config->endpoint, '/');
    }

    private function http(MobileMoneyProviderConfig $config)
    {
        return Http::when(!$config->verify_ssl, fn ($h) => $h->withoutVerifying())
            ->timeout(30);
    }

    private function getAccessToken(MobileMoneyProviderConfig $config): string
    {
        $cacheKey = 'mtn_momo_token_' . $config->id;

        return Cache::remember($cacheKey, 2700, function () use ($config) {
            $response = $this->http($config)
                ->withBasicAuth((string) $config->merchant_id, (string) $config->api_secret)
                ->withHeaders(['Ocp-Apim-Subscription-Key' => (string) $config->api_key])
                ->asForm()
                ->post($this->baseUrl($config) . '/collection/token/');

            if (!$response->successful() || empty($response->json('access_token'))) {
                throw new RuntimeException(
                    'MTN MoMo: impossible d\'obtenir un token d\'accès (' . $response->status() . ') ' . $response->body()
                );
            }

            return $response->json('access_token');
        });
    }

    public function initiate(
        MobileMoneyProviderConfig $config,
        string $externalId,
        string $phone,
        float $amount,
        string $note
    ): void {
        $token = $this->getAccessToken($config);

        $response = $this->http($config)
            ->withToken($token)
            ->withHeaders([
                'X-Reference-Id' => $externalId,
                'X-Target-Environment' => (string) ($config->environment ?: 'sandbox'),
                'Ocp-Apim-Subscription-Key' => (string) $config->api_key,
                'Content-Type' => 'application/json',
            ])
            ->post($this->baseUrl($config) . '/collection/v1_0/requesttopay', [
                'amount' => (string) $amount,
                'currency' => (string) ($config->currency ?: 'XOF'),
                'externalId' => $externalId,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->normalizePhone($phone),
                ],
                'payerMessage' => Str::limit($note, 160, ''),
                'payeeNote' => Str::limit($note, 160, ''),
            ]);

        if ($response->status() !== 202) {
            throw new RuntimeException(
                'MTN MoMo: échec de la demande de paiement (' . $response->status() . ') ' . $response->body()
            );
        }
    }

    public function checkStatus(MobileMoneyProviderConfig $config, string $externalId): array
    {
        $token = $this->getAccessToken($config);

        $response = $this->http($config)
            ->withToken($token)
            ->withHeaders([
                'X-Target-Environment' => (string) ($config->environment ?: 'sandbox'),
                'Ocp-Apim-Subscription-Key' => (string) $config->api_key,
            ])
            ->get($this->baseUrl($config) . '/collection/v1_0/requesttopay/' . $externalId);

        if (!$response->successful()) {
            throw new RuntimeException(
                'MTN MoMo: échec de la vérification du statut (' . $response->status() . ') ' . $response->body()
            );
        }

        $data = $response->json() ?? [];
        $status = strtolower((string) ($data['status'] ?? 'pending'));

        return [
            'status' => in_array($status, ['successful', 'failed'], true) ? $status : 'pending',
            'financial_transaction_id' => $data['financialTransactionId'] ?? null,
            'reason' => is_array($data['reason'] ?? null) ? ($data['reason']['message'] ?? null) : ($data['reason'] ?? null),
            'raw' => $data,
        ];
    }

    /**
     * Aide de provisioning sandbox : crée un API User + API Key MTN MoMo de test
     * à partir de la seule Subscription Key (Ocp-Apim-Subscription-Key).
     *
     * @return array{api_user: string, api_key: string}
     */
    public function provisionSandboxUser(string $baseUrl, string $subscriptionKey, string $callbackHost): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $apiUser = (string) Str::uuid();

        $createUserResponse = Http::timeout(30)
            ->withHeaders([
                'X-Reference-Id' => $apiUser,
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
                'Content-Type' => 'application/json',
            ])
            ->post($baseUrl . '/v1_0/apiuser', [
                'providerCallbackHost' => $callbackHost,
            ]);

        if ($createUserResponse->status() !== 201) {
            throw new RuntimeException(
                'MTN MoMo: échec de la création de l\'API User sandbox (' . $createUserResponse->status() . ') ' . $createUserResponse->body()
            );
        }

        $createKeyResponse = Http::timeout(30)
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
            ->post($baseUrl . '/v1_0/apiuser/' . $apiUser . '/apikey');

        if (!$createKeyResponse->successful() || empty($createKeyResponse->json('apiKey'))) {
            throw new RuntimeException(
                'MTN MoMo: échec de la génération de l\'API Key sandbox (' . $createKeyResponse->status() . ') ' . $createKeyResponse->body()
            );
        }

        return [
            'api_user' => $apiUser,
            'api_key' => (string) $createKeyResponse->json('apiKey'),
        ];
    }
}
