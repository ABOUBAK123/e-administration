<?php

namespace Tests\Feature;

use App\Models\ActRequestSubmission;
use App\Models\AdministrationProfile;
use App\Models\AdministrationSmtpSetting;
use App\Models\Courrier;
use App\Models\IssuingAdministration;
use App\Models\Notification;
use App\Models\RequestedAct;
use App\Models\SubEntity;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourrierPendingImputationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createAdministrationWithSubEntity(): array
    {
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère Test ' . Str::random(6),
            'code' => 'MIN-' . Str::random(6),
            'is_active' => true,
        ]);

        $subEntity = SubEntity::create([
            'id' => (string) Str::uuid(),
            'scope_type' => 'emitter',
            'scope_id' => $administration->id,
            'name' => 'Direction Test',
            'code' => 'DIR-TEST-' . Str::random(4),
            'is_active' => true,
        ]);

        return [$administration, $subEntity];
    }

    private function createResponsibleUser(IssuingAdministration $administration, SubEntity $subEntity): User
    {
        $profile = AdministrationProfile::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'name' => 'DIRECTEUR',
            'permissions' => ['menuPermissions' => ['courrier.imputation', 'courrier.en-traitement']],
        ]);

        $responsible = User::factory()->create([
            'profile_id' => $profile->id,
            'email' => 'responsable@example.test',
        ]);

        UserDirectionAssignment::create([
            'id' => (string) Str::uuid(),
            'user_id' => $responsible->id,
            'direction_scope_type' => 'emitter',
            'direction_scope_id' => $administration->id,
            'sub_entity_code' => $subEntity->code,
            'direction_label' => $subEntity->name,
        ]);

        return $responsible;
    }

    private function createAgentUser(IssuingAdministration $administration, SubEntity $subEntity): User
    {
        $profile = AdministrationProfile::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'name' => 'AGENT COURRIER',
            'permissions' => ['menuPermissions' => ['courrier.enregistrement']],
        ]);

        $agent = User::factory()->create(['profile_id' => $profile->id]);

        UserDirectionAssignment::create([
            'id' => (string) Str::uuid(),
            'user_id' => $agent->id,
            'direction_scope_type' => 'emitter',
            'direction_scope_id' => $administration->id,
            'sub_entity_code' => $subEntity->code,
            'direction_label' => $subEntity->name,
        ]);

        return $agent;
    }

    public function test_registering_courrier_arrive_notifies_and_emails_sub_entity_responsible(): void
    {
        Mail::fake();
        Log::spy();

        [$administration, $subEntity] = $this->createAdministrationWithSubEntity();
        $responsible = $this->createResponsibleUser($administration, $subEntity);
        $agent = $this->createAgentUser($administration, $subEntity);

        AdministrationSmtpSetting::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'smtp.example.test',
            'mail_port' => 587,
            'mail_username' => 'no-reply@example.test',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'no-reply@example.test',
            'mail_from_name' => 'E-Administration',
        ]);

        $response = $this->actingAs($agent)->post(route('courrier.store'), [
            'type_courrier' => 'arrive',
            'objet' => 'Courrier test imputation',
            'urgence' => 'normale',
            'date_emission' => now()->toDateString(),
            'expediteur' => 'Expéditeur Test',
        ]);

        $response->assertRedirect(route('courrier.enregistrement', ['type_courrier' => 'arrive']));

        $courrier = Courrier::where('objet', 'Courrier test imputation')->firstOrFail();
        $this->assertSame('en_attente', $courrier->statut);
        $this->assertSame(strtoupper($subEntity->code), $courrier->sub_entity_code);

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $responsible->id,
            'title' => "Courrier en attente d'imputation",
        ]);

        // La configuration SMTP de l'administration doit avoir été appliquée
        // (reuse de la configuration existante) avant l'envoi de l'email.
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));

        // Aucune erreur ne doit avoir été journalisée: l'envoi a pu être configuré correctement.
        Log::shouldNotHaveReceived('error');
    }

    public function test_registering_courrier_arrive_logs_explicit_error_when_smtp_configuration_missing(): void
    {
        Mail::fake();
        Log::spy();

        [$administration, $subEntity] = $this->createAdministrationWithSubEntity();
        $this->createResponsibleUser($administration, $subEntity);
        $agent = $this->createAgentUser($administration, $subEntity);

        // Volontairement: aucune AdministrationSmtpSetting créée pour cette administration.

        $response = $this->actingAs($agent)->post(route('courrier.store'), [
            'type_courrier' => 'arrive',
            'objet' => 'Courrier sans SMTP',
            'urgence' => 'normale',
            'date_emission' => now()->toDateString(),
            'expediteur' => 'Expéditeur Test',
        ]);

        $response->assertRedirect(route('courrier.enregistrement', ['type_courrier' => 'arrive']));

        // La notification en base doit tout de même être créée...
        $this->assertDatabaseHas('notifications', [
            'title' => "Courrier en attente d'imputation",
        ]);

        // ...mais l'échec de configuration SMTP doit être journalisé explicitement (pas de swallow).
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, "configuration SMTP indisponible")
                    && array_key_exists('error', $context);
            });
    }

    public function test_act_status_change_email_uses_administration_smtp_configuration(): void
    {
        Mail::fake();
        Log::spy();

        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Administration acte',
            'code' => 'ACT-' . Str::random(5),
            'is_active' => true,
        ]);

        AdministrationSmtpSetting::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'smtp.actes.test',
            'mail_port' => 587,
            'mail_username' => 'actes@example.test',
            'mail_password' => 'secret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'no-reply@example.test',
            'mail_from_name' => 'Administration actes',
        ]);

        $requestedAct = RequestedAct::create([
            'id' => (string) Str::uuid(),
            'administration_id' => $administration->id,
            'direction_code' => 'DIR-01',
            'document_name' => 'Acte test',
            'is_active' => true,
        ]);

        $submission = ActRequestSubmission::create([
            'id' => (string) Str::uuid(),
            'requested_act_id' => $requestedAct->id,
            'emitter_administration_id' => $administration->id,
            'direction_code' => 'DIR-01',
            'requested_document_name' => 'Acte test',
            'applicant_full_name' => 'Demandeur Test',
            'applicant_email' => 'demandeur@example.test',
            'status' => 'pending',
        ]);

        $submission->update(['status' => 'in_progress']);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.actes.test', config('mail.mailers.smtp.host'));
        Log::shouldNotHaveReceived('error');
    }
}
