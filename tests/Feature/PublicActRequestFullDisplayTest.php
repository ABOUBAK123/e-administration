<?php

namespace Tests\Feature;

use App\Models\AdministrationProfile;
use App\Models\IssuingAdministration;
use App\Models\RecipientAdministration;
use App\Models\RequestedAct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicActRequestFullDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_applicant_fields_submitted_publicly_are_displayed_in_act_requests_index(): void
    {
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Mairie Test Champs',
            'code' => 'MTC-' . Str::random(4),
            'is_active' => true,
        ]);

        $recipient = RecipientAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Destinataire Test',
            'code' => 'DEST-' . Str::random(4),
            'channel' => 'email',
            'is_active' => true,
        ]);

        $requestedAct = RequestedAct::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'document_name' => 'Extrait de naissance',
            'required_documents' => [],
            'applicant_fields' => [
                ['label' => 'Nom du pere', 'inputType' => 'text'],
                ['label' => 'Nom de la mere', 'inputType' => 'text'],
                ['label' => 'Date de naissance', 'inputType' => 'date'],
                ['label' => 'Lieu de naissance', 'inputType' => 'text'],
                ['label' => 'Sexe', 'inputType' => 'list', 'options' => ['Masculin', 'Feminin']],
            ],
            'is_active' => true,
        ]);

        // Soumission réelle via le flux public (pas d'insertion directe en DB).
        $response = $this->post(route('public.act-requests.store', [
            'administration_id' => $administration->id,
            'requested_act_id' => $requestedAct->id,
        ]), [
            'recipient_administration_id' => $recipient->id,
            'motif' => "Besoin de l'acte pour dossier scolaire",
            'applicant_full_name' => 'Jean Testeur',
            'applicant_email' => 'jean.testeur@example.test',
            'applicant_phone' => '0102030405',
            'extra' => [
                'nom_du_pere' => 'Kouassi Testeur',
                'nom_de_la_mere' => 'Awa Testeur',
                'date_de_naissance' => '2010-05-12',
                'lieu_de_naissance' => 'Abidjan',
                'sexe' => 'Masculin',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $profile = AdministrationProfile::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'name' => 'Direction test',
        ]);

        $agent = User::factory()->create(['profile_id' => $profile->id]);

        $indexResponse = $this->actingAs($agent)->get(route('act-requests.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Nom du pere');
        $indexResponse->assertSee('Kouassi Testeur');
        $indexResponse->assertSee('Nom de la mere');
        $indexResponse->assertSee('Awa Testeur');
        $indexResponse->assertSee('Date de naissance');
        $indexResponse->assertSee('2010-05-12');
        $indexResponse->assertSee('Lieu de naissance');
        $indexResponse->assertSee('Abidjan');
        $indexResponse->assertSee('Sexe');
        $indexResponse->assertSee('Masculin');
    }
}
