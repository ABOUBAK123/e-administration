<?php

namespace Tests\Feature;

use App\Models\AdministrationProfile;
use App\Models\AdministrationSmtpSetting;
use App\Models\IssuingAdministration;
use App\Models\RecipientAdministration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSmtpScopeTest extends TestCase
{
    use RefreshDatabase;

    private function createAdministration(string $name = 'Ministère Test'): IssuingAdministration
    {
        return IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'code' => 'MIN-' . Str::random(5),
            'is_active' => true,
        ]);
    }

    private function createRecipient(string $name = 'Destinataire Test'): RecipientAdministration
    {
        return RecipientAdministration::create([
            'name' => $name,
            'code' => 'DEST-' . Str::random(5),
            'channel' => 'email',
            'is_active' => true,
        ]);
    }

    private function scopedAdmin(string $administrationId, string $type = 'emitter'): User
    {
        // resolveAdminScope() ne consulte UserDirectionAssignment/le profil que si
        // l'utilisateur a un profile_id : role='admin' sans profil = super admin non scopé.
        $profile = AdministrationProfile::create([
            'name' => 'Admin scopé',
            'administration_id' => $administrationId,
            'administration_type' => $type,
            'permissions' => ['menuPermissions' => ['dashboard', 'administration.email-notifications']],
        ]);

        return User::factory()->create(['role' => 'admin', 'profile_id' => $profile->id]);
    }

    public function test_super_admin_can_save_smtp_for_any_administration(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin', 'profile_id' => null]);
        $administration = $this->createAdministration();

        $response = $this->actingAs($superAdmin)->postJson(route('admin.smtp.settings.save'), [
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'smtp.example.com',
            'mail_from_address' => 'no-reply@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_scoped_admin_can_save_smtp_for_their_own_administration(): void
    {
        $administration = $this->createAdministration();
        $admin = $this->scopedAdmin($administration->id, 'emitter');

        $response = $this->actingAs($admin)->postJson(route('admin.smtp.settings.save'), [
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'smtp.example.com',
            'mail_from_address' => 'no-reply@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_scoped_admin_cannot_save_smtp_for_another_administration(): void
    {
        $ownAdministration = $this->createAdministration('Ma Ministère');
        $otherAdministration = $this->createAdministration('Autre Ministère');
        $admin = $this->scopedAdmin($ownAdministration->id, 'emitter');

        $response = $this->actingAs($admin)->postJson(route('admin.smtp.settings.save'), [
            'administration_id' => $otherAdministration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'evil.example.com',
            'mail_from_address' => 'evil@example.com',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('administration_smtp_settings', [
            'administration_id' => $otherAdministration->id,
        ]);
    }

    public function test_scoped_admin_cannot_save_smtp_for_a_recipient_administration(): void
    {
        $ownAdministration = $this->createAdministration();
        $recipient = $this->createRecipient();
        $admin = $this->scopedAdmin($ownAdministration->id, 'emitter');

        $response = $this->actingAs($admin)->postJson(route('admin.smtp.settings.save'), [
            'administration_id' => $recipient->id,
            'administration_type' => 'recipient',
            'mail_host' => 'evil.example.com',
            'mail_from_address' => 'evil@example.com',
        ]);

        $response->assertForbidden();
    }

    public function test_scoped_admin_cannot_read_smtp_of_another_administration(): void
    {
        $ownAdministration = $this->createAdministration('Ma Ministère');
        $otherAdministration = $this->createAdministration('Autre Ministère');
        $admin = $this->scopedAdmin($ownAdministration->id, 'emitter');

        AdministrationSmtpSetting::create([
            'administration_id' => $otherAdministration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'secret.example.com',
            'mail_from_address' => 'secret@example.com',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.smtp.settings.get', ['type' => 'emitter', 'id' => $otherAdministration->id]));

        $response->assertForbidden();
    }

    public function test_scoped_admin_cannot_test_smtp_of_another_administration(): void
    {
        $ownAdministration = $this->createAdministration('Ma Ministère');
        $otherAdministration = $this->createAdministration('Autre Ministère');
        $admin = $this->scopedAdmin($ownAdministration->id, 'emitter');

        $response = $this->actingAs($admin)->postJson(route('admin.smtp.test'), [
            'administration_id' => $otherAdministration->id,
            'administration_type' => 'emitter',
        ]);

        $response->assertForbidden();
    }
}
