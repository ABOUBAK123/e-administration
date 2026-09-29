<?php

namespace Tests\Feature;

use App\Mail\AccountCreatedMail;
use App\Models\AdministrationProfile;
use App\Models\AdministrationSmtpSetting;
use App\Models\IssuingAdministration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountCreatedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_user_account_sends_the_professional_html_welcome_email(): void
    {
        Mail::fake();

        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère Test',
            'code' => 'MIN-' . Str::random(5),
            'is_active' => true,
        ]);

        AdministrationSmtpSetting::create([
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'mail_host' => 'smtp.example.com',
            'mail_from_address' => 'no-reply@example.com',
            'mail_from_name' => 'Ministère Test',
        ]);

        $profile = AdministrationProfile::create([
            'name' => 'Agent',
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'permissions' => ['menuPermissions' => ['dashboard']],
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'profile_id' => null]);
        $newUser = User::factory()->create([
            'profile_id' => $profile->id,
            'email' => 'nouvel.agent@example.com',
            'full_name' => 'Nouvel Agent',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users-tab.notify-account', $newUser->id));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        Mail::assertSent(AccountCreatedMail::class, function (AccountCreatedMail $mail) use ($newUser) {
            return $mail->email === $newUser->email
                && $mail->displayName === 'Nouvel Agent'
                && $mail->hasTo($newUser->email);
        });
    }

    public function test_notify_user_account_fails_gracefully_without_smtp_configuration(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['role' => 'admin', 'profile_id' => null]);
        $newUser = User::factory()->create(['profile_id' => null]);

        $response = $this->actingAs($admin)->post(route('admin.users-tab.notify-account', $newUser->id));

        $response->assertSessionHasErrors('users');
        Mail::assertNothingSent();
    }
}
