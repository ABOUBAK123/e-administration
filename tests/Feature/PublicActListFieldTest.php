<?php

namespace Tests\Feature;

use App\Models\IssuingAdministration;
use App\Models\RequestedAct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicActListFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_accepts_only_configured_list_values(): void
    {
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère test',
            'code' => 'MIN-TEST',
            'is_active' => true,
        ]);

        $requestedAct = RequestedAct::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'document_name' => 'Carte d’identité',
            'required_documents' => [],
            'applicant_fields' => [
                [
                    'label' => 'Pays',
                    'inputType' => 'list',
                    'options' => ['Côte d’Ivoire', 'Mali', 'Burkina Faso'],
                ],
            ],
            'is_active' => true,
        ]);

        $validResponse = $this->post(route('public.act-requests.store', [$administration->id, $requestedAct->id]), [
            'applicant_full_name' => 'Aminata Kone',
            'applicant_email' => 'aminata@example.test',
            'motif' => 'Demande de carte',
            'extra' => ['pays' => 'Côte d’Ivoire'],
        ]);

        $validResponse->assertRedirect(route('public.act-requests.create', [$administration->id, $requestedAct->id]));

        $this->assertDatabaseHas('act_request_submissions', [
            'requested_act_id' => $requestedAct->id,
        ]);

        $submission = \App\Models\ActRequestSubmission::query()->where('requested_act_id', $requestedAct->id)->firstOrFail();
        $this->assertSame('Côte d’Ivoire', $submission->applicant_payload['pays'] ?? null);

        $invalidResponse = $this->from(route('public.act-requests.create', [$administration->id, $requestedAct->id]))
            ->post(route('public.act-requests.store', [$administration->id, $requestedAct->id]), [
                'applicant_full_name' => 'Aminata Kone',
                'applicant_email' => 'aminata@example.test',
                'motif' => 'Demande de carte',
                'extra' => ['pays' => 'Option interdite'],
            ]);

        $invalidResponse->assertSessionHasErrors('extra.pays');
    }
}
