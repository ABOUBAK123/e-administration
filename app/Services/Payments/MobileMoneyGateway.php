<?php

namespace App\Services\Payments;

use App\Models\MobileMoneyProviderConfig;

interface MobileMoneyGateway
{
    /**
     * Déclenche une demande de paiement (push USSD / collect) chez le fournisseur.
     * Doit lancer une exception si la demande n'a pas pu être transmise au fournisseur.
     */
    public function initiate(
        MobileMoneyProviderConfig $config,
        string $externalId,
        string $phone,
        float $amount,
        string $note
    ): void;

    /**
     * Interroge le fournisseur pour connaître le statut d'un paiement déjà initié.
     *
     * @return array{status: string, financial_transaction_id: ?string, reason: ?string, raw: array}
     *   status normalisé à l'une des valeurs : pending|successful|failed
     */
    public function checkStatus(MobileMoneyProviderConfig $config, string $externalId): array;
}
