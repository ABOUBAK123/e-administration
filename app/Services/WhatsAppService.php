<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Envoie un message texte via l'API WhatsApp Cloud (Meta).
     * Nécessite les paramètres whatsapp_api_token et whatsapp_phone_number_id.
     */
    public function sendMessage(string $phone, string $message): bool
    {
        $token = AppSetting::where('key', 'whatsapp_api_token')->value('value');
        $phoneNumberId = AppSetting::where('key', 'whatsapp_phone_number_id')->value('value');

        if (!$token || !$phoneNumberId) {
            Log::warning('WhatsApp OTP: configuration manquante (whatsapp_api_token / whatsapp_phone_number_id).');
            return false;
        }

        $to = $this->normalizePhone($phone);
        if (!$to) {
            Log::warning('WhatsApp OTP: numéro de téléphone invalide.', ['phone' => $phone]);
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('WhatsApp OTP: échec envoi.', ['status' => $response->status(), 'body' => $response->body()]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsApp OTP: exception envoi.', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** Format international sans "+" ni espaces (ex: 2250701020304). */
    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        return strlen($digits) >= 8 ? $digits : null;
    }
}
