<?php

namespace Tests\Feature;

use App\Models\ActRequestSubmission;
use App\Models\AdministrationProfile;
use App\Models\IssuingAdministration;
use App\Models\RequestedAct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActRequestDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_displays_all_applicant_fields_completed_by_the_requester(): void
    {
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère test',
            'code' => 'MIN-TEST',
            'is_active' => true,
        ]);

        $profile = AdministrationProfile::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'name' => 'Direction test',
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'full_name' => 'Agent test',
            'name' => 'Agent test',
            'email' => 'agent@example.test',
            'password' => bcrypt('secret123'),
            'profile_id' => $profile->id,
            'role' => 'admin',
            'status' => 'active',
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
                    'options' => ['Côte d’Ivoire', 'Mali'],
                ],
                [
                    'label' => 'Ville',
                    'inputType' => 'text',
                ],
            ],
            'is_active' => true,
        ]);

        ActRequestSubmission::create([
            'id' => (string) Str::uuid(),
            'tracking_number' => 'DACT-202608-123456',
            'tracking_token' => 'token-1234567890',
            'requested_act_id' => $requestedAct->id,
            'emitter_administration_id' => $administration->id,
            'direction_code' => 'DIR-TEST',
            'requested_document_name' => 'Carte d’identité',
            'applicant_full_name' => 'Aminata Kone',
            'applicant_email' => 'aminata@example.test',
            'applicant_phone' => '01020304',
            'applicant_payload' => [
                'pays' => 'Côte d’Ivoire',
                'ville' => 'Abidjan',
                '_note' => 'À compléter',
            ],
            'status' => 'pending',
        ]);

        $this->actingAs($user); 

        $response = $this->get(route('act-requests.index'));

        $response->assertOk();
        $response->assertSee('Pays');
        $response->assertSee('Côte d’Ivoire');
        $response->assertSee('Ville');
        $response->assertSee('Abidjan');
    }
}
