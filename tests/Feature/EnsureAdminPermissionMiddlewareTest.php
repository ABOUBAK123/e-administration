<?php

namespace Tests\Feature;

use App\Models\AdministrationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureAdminPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function userWithMenuPermissions(array $menuPermissions): User
    {
        $profile = AdministrationProfile::create([
            'name' => 'Profil test',
            'permissions' => ['menuPermissions' => $menuPermissions],
        ]);

        return User::factory()->create([
            'role' => 'user',
            'profile_id' => $profile->id,
        ]);
    }

    public function test_profile_with_unrelated_permission_is_rejected_from_recipients_route(): void
    {
        // Before the fix, ANY non-empty menuPermissions (even just 'dashboard') was enough
        // to pass EnsureAdmin and reach every /admin/* write action.
        $user = $this->userWithMenuPermissions(['dashboard']);

        $response = $this->actingAs($user)->post(route('admin.recipients.store'), []);

        $response->assertForbidden();
    }

    public function test_profile_with_matching_permission_passes_the_gate_for_recipients_route(): void
    {
        $user = $this->userWithMenuPermissions(['administration.recipients']);

        $response = $this->actingAs($user)->post(route('admin.recipients.store'), []);

        $response->assertStatus(302); // validation redirect, not blocked by the permission gate
        $response->assertSessionHasErrors();
    }

    public function test_profile_with_parent_administration_permission_covers_recipients_route(): void
    {
        $user = $this->userWithMenuPermissions(['administration']);

        $response = $this->actingAs($user)->post(route('admin.recipients.store'), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_profile_without_users_permission_cannot_reach_user_controller(): void
    {
        $user = $this->userWithMenuPermissions(['dashboard']);

        $response = $this->actingAs($user)->post(route('admin.users.store'), []);

        $response->assertForbidden();
    }

    public function test_admin_role_bypasses_the_permission_gate(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'profile_id' => null]);

        $response = $this->actingAs($admin)->post(route('admin.recipients.store'), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_personnel_employees_route_requires_personnel_employees_permission(): void
    {
        $user = $this->userWithMenuPermissions(['personnel.training']);

        $response = $this->actingAs($user)->post(route('admin.personnel.employees.store'), []);

        $response->assertForbidden();
    }
}
