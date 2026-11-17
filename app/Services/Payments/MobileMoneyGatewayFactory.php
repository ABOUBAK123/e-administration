<?php

namespace App\Services\Payments;

use RuntimeException;

class MobileMoneyGatewayFactory
{
    public static function make(string $provider): MobileMoneyGateway
    {
        return match ($provider) {
            'mtn_money' => app(MtnMomoGateway::class),
            default => throw new RuntimeException(
                "Le déclenchement automatique du paiement n'est pas encore disponible pour le fournisseur \"{$provider}\"."
            ),
        };
    }
}
