<?php

namespace App\Http\Controllers;

use App\Models\MobileMoneyProviderConfig;
use App\Models\MobileMoneyTransaction;
use App\Services\Payments\MobileMoneyGatewayFactory;
use App\Services\Payments\PaymentStatusUpdater;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileMoneyController extends Controller
{
    /**
     * Statut public d'une transaction (interrogé en polling par la page de paiement).
     * Ne relance une vérification active chez le fournisseur que si aucune confirmation
     * (webhook) n'est arrivée depuis quelques secondes — évite de spammer l'API externe.
     */
    public function status(MobileMoneyTransaction $transaction)
    {
        if ($transaction->status === 'pending' && $transaction->updated_at->diffInSeconds(now()) >= 5) {
            $this->refreshFromGateway($transaction);
            $transaction->refresh();
        }

        return response()->json([
            'status' => $transaction->status,
            'reason' => $transaction->status === 'failed' ? $transaction->reason : null,
        ]);
    }

    private function refreshFromGateway(MobileMoneyTransaction $transaction): void
    {
        $config = $transaction->providerConfig;
        if (!$config) {
            return;
        }

        try {
            $gateway = MobileMoneyGatewayFactory::make($transaction->provider);
            $statusData = $gateway->checkStatus($config, $transaction->external_id);
            app(PaymentStatusUpdater::class)->apply($transaction, $statusData);
        } catch (\Throwable $e) {
            // Erreur réseau/API : on reste en pending, le citoyen continue simplement à patienter.
            Log::warning('MobileMoneyController::status - vérification active échouée', [
                'transaction_id' => (string) $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Webhook entrant du fournisseur (ex: MTN MoMo) confirmant/rejetant un paiement.
     * Répond toujours 200 pour éviter les tentatives de re-livraison du fournisseur.
     */
    public function callback(Request $request, string $provider)
    {
        $referenceId = (string) ($request->input('referenceId') ?? $request->input('externalId') ?? '');

        if ($referenceId === '') {
            Log::warning('MobileMoneyController::callback - referenceId manquant', [
                'provider' => $provider,
                'payload' => $request->all(),
            ]);
            return response()->json(['ok' => true]);
        }

        $transaction = MobileMoneyTransaction::where('external_id', $referenceId)->first();
        if (!$transaction) {
            Log::warning('MobileMoneyController::callback - transaction introuvable', [
                'provider' => $provider,
                'reference_id' => $referenceId,
            ]);
            return response()->json(['ok' => true]);
        }

        $status = strtolower((string) ($request->input('status') ?? 'pending'));
        $reason = $request->input('reason');

        app(PaymentStatusUpdater::class)->apply($transaction, [
            'status' => in_array($status, ['successful', 'failed'], true) ? $status : 'pending',
            'financial_transaction_id' => $request->input('financialTransactionId'),
            'reason' => is_array($reason) ? ($reason['message'] ?? null) : $reason,
            'raw' => $request->all(),
        ]);

        return response()->json(['ok' => true]);
    }
}
