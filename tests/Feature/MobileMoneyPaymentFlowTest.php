<?php

namespace Tests\Feature;

use App\Models\ActRequestSubmission;
use App\Models\IssuingAdministration;
use App\Models\MobileMoneyProviderConfig;
use App\Models\MobileMoneyTransaction;
use App\Models\RequestedAct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileMoneyPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Neutralise le vrai secret présent dans .env local : chaque test fixe
        // explicitement le secret (ou son absence) qu'il veut exercer.
        config(['services.mobile_money.webhook_secret' => null]);
    }

    private function createAdministration(): IssuingAdministration
    {
        return IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère Test',
            'code' => 'MIN-' . Str::random(5),
            'is_active' => true,
        ]);
    }

    private function createPaidAct(IssuingAdministration $administration): RequestedAct
    {
        return RequestedAct::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'document_name' => 'Extrait de naissance',
            'required_documents' => [],
            'applicant_fields' => [],
            'is_active' => true,
            'is_paid' => true,
            'amount' => 2000,
        ]);
    }

    private function createMtnConfig(IssuingAdministration $administration): MobileMoneyProviderConfig
    {
        return MobileMoneyProviderConfig::create([
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'provider' => 'mtn_money',
            'is_active' => true,
            'endpoint' => 'https://sandbox.momodeveloper.mtn.com',
            'environment' => 'sandbox',
            'currency' => 'EUR',
            'api_key' => 'sub-key',
            'api_secret' => 'api-key-secret',
            'merchant_id' => (string) Str::uuid(),
            'verify_ssl' => true,
        ]);
    }

    private function submitPublicRequest(IssuingAdministration $administration, RequestedAct $act): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('public.act-requests.store', [$administration->id, $act->id]), [
            'applicant_full_name' => 'Jean Test',
            'applicant_email' => 'jean@example.com',
            'applicant_phone' => '0708091011',
            'nni' => '1234567890123',
        ]);
    }

    public function test_paid_act_submission_starts_awaiting_payment_and_redirects_to_payment_page(): void
    {
        $administration = $this->createAdministration();
        $act = $this->createPaidAct($administration);

        $response = $this->submitPublicRequest($administration, $act);

        $submission = ActRequestSubmission::where('requested_act_id', $act->id)->firstOrFail();
        $this->assertSame('awaiting_payment', $submission->status);
        $response->assertRedirect(route('public.act-requests.payment', $submission->tracking_token));
    }

    public function test_unpaid_act_submission_behaves_exactly_as_before(): void
    {
        $administration = $this->createAdministration();
        $act = RequestedAct::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'document_name' => 'Certificat de résidence',
            'required_documents' => [],
            'applicant_fields' => [],
            'is_active' => true,
            'is_paid' => false,
        ]);

        $response = $this->submitPublicRequest($administration, $act);

        $submission = ActRequestSubmission::where('requested_act_id', $act->id)->firstOrFail();
        $this->assertSame('pending', $submission->status);
        $response->assertRedirect(route('public.act-requests.create', [$administration->id, $act->id]));
    }

    public function test_payment_page_lists_active_providers_for_the_administration(): void
    {
        $administration = $this->createAdministration();
        $act = $this->createPaidAct($administration);
        $this->createMtnConfig($administration);

        $this->submitPublicRequest($administration, $act);
        $submission = ActRequestSubmission::where('requested_act_id', $act->id)->firstOrFail();

        $response = $this->get(route('public.act-requests.payment', $submission->tracking_token));

        $response->assertOk();
        $response->assertSee('MTN Mobile Money');
    }

    public function test_initiate_payment_success_creates_pending_transaction(): void
    {
        Http::fake([
            '*/collection/token/' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
            '*/collection/v1_0/requesttopay' => Http::response('', 202),
        ]);

        $administration = $this->createAdministration();
        $act = $this->createPaidAct($administration);
        $config = $this->createMtnConfig($administration);

        $this->submitPublicRequest($administration, $act);
        $submission = ActRequestSubmission::where('requested_act_id', $act->id)->firstOrFail();

        $response = $this->postJson(route('public.act-requests.payment.initiate', $submission->tracking_token), [
            'provider_config_id' => $config->id,
            'phone' => '0708091011',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $this->assertDatabaseHas('mobile_money_transactions', [
            'act_request_submission_id' => $submission->id,
            'status' => 'pending',
            'phone_number' => '0708091011',
        ]);

        $submission->refresh();
        $this->assertSame('awaiting_payment', $submission->status);
    }

    public function test_initiate_payment_failure_returns_error_without_breaking_submission(): void
    {
        Http::fake([
            '*/collection/token/' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $administration = $this->createAdministration();
        $act = $this->createPaidAct($administration);
        $config = $this->createMtnConfig($administration);

        $this->submitPublicRequest($administration, $act);
        $submission = ActRequestSubmission::where('requested_act_id', $act->id)->firstOrFail();

        $response = $this->postJson(route('public.act-requests.payment.initiate', $submission->tracking_token), [
            'provider_config_id' => $config->id,
            'phone' => '0708091011',
        ]);

        $response->assertStatus(502);
        $response->assertJson(['ok' => false]);

        $submission->refresh();
        $this->assertSame('awaiting_payment', $submission->status, 'A network/API failure must not lock the submission out of retrying.');
    }

    public function test_webhook_confirms_payment_and_unblocks_submission(): void
    {
        $administration = $this->createAdministration();
        $act = $this->createPaidAct($administration);
        $config = $this->createMtnConfig($administration);

        $this->submitPublicRequest($administration, $act);
        $submission = ActRequestSubmission::where('requested_act_id', $act->id)->firstOrFail();

        $transaction = MobileMoneyTransaction::create([
            'act_request_submission_id' => $submission->id,
            'mobile_money_provider_config_id' => $config->id,
            'provider' => 'mtn_money',
            'external_id' => (string) Str::uuid(),
            'phone_number' => '0708091011',
            'amount' => 2000,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $response = $this->postJson(route('mobile-money.callback', 'mtn'), [
            'referenceId' => $transaction->external_id,
            'status' => 'SUCCESSFUL',
            'financialTransactionId' => 'FT123456',
        ]);

        $response->assertOk();

        $transaction->refresh();
        $submission->refresh();

        $this->assertSame('successful', $transaction->status);
        $this->assertSame('FT123456', $transaction->financial_transaction_id);
        $this->assertSame('pending', $submission->status);
        $this->assertNotNull($submission->paid_at);
        $this->assertSame($transaction->id, $submission->mobile_money_transaction_id);
    }

    public function test_webhook_failure_marks_submission_as_payment_failed(): void
    {
        $administration = $this->createAdministration();
        $act = $this->createPaidAct($administration);
        $config = $this->createMtnConfig($administration);

        $this->submitPublicRequest($administration, $act);
        $submission = ActRequestSubmission::where('requested_act_id', $act->id)->firstOrFail();

        $transaction = MobileMoneyTransaction::create([
            'act_request_submission_id' => $submission->id,
            'mobile_money_provider_config_id' => $config->id,
            'provider' => 'mtn_money',
            'external_id' => (string) Str::uuid(),
            'phone_number' => '0708091011',
            'amount' => 2000,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $this->postJson(route('mobile-money.callback', 'mtn'), [
            'referenceId' => $transaction->external_id,
            'status' => 'FAILED',
            'reason' => 'Payer rejected the transaction.',
        ])->assertOk();

        $submission->refresh();
        $this->assertSame('payment_failed', $submission->status);
    }

    public function test_webhook_rejects_invalid_token_when_secret_is_configured(): void
    {
        config(['services.mobile_money.webhook_secret' => 'correct-secret']);

        $response = $this->postJson(route('mobile-money.callback', 'mtn') . '?token=wrong-secret', [
            'referenceId' => 'whatever',
            'status' => 'SUCCESSFUL',
        ]);

        $response->assertStatus(401);
    }

    public function test_normalize_phone_formats_ivorian_local_number(): void
    {
        $gateway = new \App\Services\Payments\MtnMomoGateway();

        $this->assertSame('225708091011', $gateway->normalizePhone('0708091011'));
        $this->assertSame('225708091011', $gateway->normalizePhone('+225 07 08 09 10 11'));
        $this->assertSame('225708091011', $gateway->normalizePhone('225708091011'));
    }
}
