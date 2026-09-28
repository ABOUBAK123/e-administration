<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_reset_link_does_not_crash_with_route_not_found(): void
    {
        // Regression test: sendPasswordResetNotification() used to rely on Laravel's
        // default "password.reset" route, which this app never defines (custom auth
        // controller/routes) — that threw RouteNotFoundException (500) on every attempt.
        Mail::fake();

        $user = User::factory()->create(['email' => 'reset-me@example.com']);

        $response = $this->post(route('password.email'), ['email' => $user->email]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        Mail::assertSent(\App\Mail\ResetPasswordMail::class);
    }

    public function test_reset_password_page_renders_for_a_given_token(): void
    {
        $response = $this->get(route('password.reset', ['token' => 'sometoken', 'email' => 'x@example.com']));

        $response->assertOk();
        $response->assertSee(route('password.update'), false);
    }

    public function test_submitting_a_valid_token_actually_resets_the_password(): void
    {
        $user = User::factory()->create(['email' => 'reset-me2@example.com']);

        $token = Password::broker()->getRepository()->create($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPassword123',
            'password_confirmation' => 'BrandNewPassword123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('BrandNewPassword123', $user->refresh()->password));
    }

    public function test_submitting_an_invalid_token_is_rejected_without_crashing(): void
    {
        $user = User::factory()->create(['email' => 'reset-me3@example.com']);

        $response = $this->post(route('password.update'), [
            'token' => 'not-a-real-token',
            'email' => $user->email,
            'password' => 'BrandNewPassword123',
            'password_confirmation' => 'BrandNewPassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
