<?php

namespace App\Services\Payments;

use App\Models\MobileMoneyTransaction;
use Illuminate\Support\Facades\Log;

/**
 * Applique le résultat d'un statut de paiement (venant du webhook fournisseur
 * ou d'une vérification active) à la transaction et à la demande d'acte liée.
 * Idempotent : rejouer le même statut ne déclenche pas deux fois les effets de bord.
 */
class PaymentStatusUpdater
{
    public function apply(MobileMoneyTransaction $transaction, array $statusData): void
    {
        if ($transaction->status !== 'pending') {
            // Déjà finalisée (successful/failed) : on ignore les événements tardifs/doublons.
            return;
        }

        $status = $statusData['status'] ?? 'pending';
        if (!in_array($status, ['successful', 'failed'], true)) {
            return;
        }

        $transaction->update([
            'status' => $status,
            'financial_transaction_id' => $statusData['financial_transaction_id'] ?? null,
            'reason' => $statusData['reason'] ?? null,
            'raw_response' => $statusData['raw'] ?? null,
        ]);

        $submission = $transaction->submission;
        if (!$submission) {
            Log::warning('PaymentStatusUpdater: demande liée introuvable.', [
                'transaction_id' => (string) $transaction->id,
            ]);
            return;
        }

        if ($status === 'successful') {
            $submission->update([
                'status' => 'pending',
                'mobile_money_transaction_id' => $transaction->id,
                'paid_at' => now(),
            ]);
        } else {
            $submission->update([
                'status' => 'payment_failed',
                'mobile_money_transaction_id' => $transaction->id,
            ]);
        }
    }
}
