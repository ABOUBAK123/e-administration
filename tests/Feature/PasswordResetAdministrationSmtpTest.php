<?php

namespace Tests\Feature;

use App\Models\AdministrationProfile;
use App\Models\AdministrationSmtpSetting;
use App\Models\IssuingAdministration;
use App\Models\User;
use App\Models\UserDirectionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetAdministrationSmtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
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

    public function test_password_reset_uses_the_smtp_config_of_the_user_direction_assignment(): void
    {
        $administration = $this->createAdministration();

        AdministrationSmtpSetting::create([
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'smtp.admin-test.example',
            'mail_port' => 2525,
            'mail_username' => 'admin-smtp-user',
            'mail_password' => 'secret',
            'mail_from_address' => 'no-reply@admin-test.example',
            'mail_from_name' => 'Ministère Test',
        ]);

        $user = User::factory()->create();
        UserDirectionAssignment::create([
            'user_id' => $user->id,
            'direction_scope_type' => 'emitter',
            'direction_scope_id' => $administration->id,
            'direction_label' => $administration->name,
        ]);

        $user->sendPasswordResetNotification('some-token');

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.admin-test.example', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('no-reply@admin-test.example', config('mail.from.address'));
        $this->assertSame('Ministère Test', config('mail.from.name'));
    }

    public function test_password_reset_uses_the_smtp_config_of_the_profile_administration_as_fallback(): void
    {
        $administration = $this->createAdministration();

        AdministrationSmtpSetting::create([
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'smtp.profile-fallback.example',
            'mail_from_address' => 'no-reply@profile-fallback.example',
        ]);

        $profile = AdministrationProfile::create([
            'name' => 'Profil Ministère',
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'permissions' => ['menuPermissions' => ['dashboard']],
        ]);

        $user = User::factory()->create(['profile_id' => $profile->id]);

        $user->sendPasswordResetNotification('some-token');

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.profile-fallback.example', config('mail.mailers.smtp.host'));
    }

    public function test_password_reset_falls_back_to_global_mailer_when_no_administration_smtp_found(): void
    {
        $originalDefault = config('mail.default');

        $user = User::factory()->create(['profile_id' => null]);

        $user->sendPasswordResetNotification('some-token');

        $this->assertSame($originalDefault, config('mail.default'));
    }
}
