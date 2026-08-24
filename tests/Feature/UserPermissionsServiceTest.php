<?php

namespace Tests\Feature;

use App\Models\AdministrationProfile;
use App\Models\User;
use App\Services\UserPermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_profile_without_menu_permissions_keeps_core_modules_visible(): void
    {
        $profile = AdministrationProfile::create([
            'name' => 'ADMIN',
            'permissions' => ['menuPermissions' => []],
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'profile_id' => $profile->id,
        ]);

        $service = new UserPermissionsService();
        $resolved = $service->resolve($user);

        $this->assertContains('documents', $resolved['permissions']);
        $this->assertContains('civil-status', $resolved['permissions']);
        $this->assertContains('personnel', $resolved['permissions']);
        $this->assertTrue($service->can($user, 'documents'));
        $this->assertTrue($service->can($user, 'civil-status'));
        $this->assertTrue($service->can($user, 'personnel'));
    }

    public function test_admin_role_with_scoped_profile_is_restricted_to_its_checked_permissions(): void
    {
        // Regression test: a user with the system role "admin" but assigned to a
        // scoped profile (e.g. "ADMIN ADMINISTRATION") must be restricted to that
        // profile's own menuPermissions, not silently upgraded to the full default
        // admin menu just because user.role === 'admin'.
        $profile = AdministrationProfile::create([
            'name' => 'ADMIN ADMINISTRATION',
            'permissions' => ['menuPermissions' => [
                'dashboard',
                'administration',
                'administration.templates',
                'administration.users',
            ]],
        ]);

        $user = User::factory()->create([
            'role' => 'admin',
            'profile_id' => $profile->id,
        ]);

        $service = new UserPermissionsService();
        $resolved = $service->resolve($user);

        $this->assertFalse($resolved['isElevated']);
        $this->assertSame([
            'dashboard',
            'administration',
            'administration.templates',
            'administration.users',
        ], $resolved['permissions']);

        $this->assertTrue($service->can($user, 'administration.templates'));
        $this->assertTrue($service->can($user, 'administration.users'));
        $this->assertFalse($service->can($user, 'documents'));
        $this->assertFalse($service->can($user, 'courrier'));
    }

    public function test_parent_permission_with_explicit_children_does_not_grant_unlisted_siblings(): void
    {
        // Regression test for the real-world "ADMIN ADMINISTRATION" profile bug:
        // when the profile checks the parent "administration" AND a specific subset
        // of "administration.*" children, the checked children are authoritative —
        // unchecked siblings must stay denied, even though the parent key is present.
        $profile = AdministrationProfile::create([
            'name' => 'Profil scope partiel',
            'permissions' => ['menuPermissions' => [
                'administration',
                'administration.templates',
                'administration.users',
            ]],
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'profile_id' => $profile->id,
        ]);

        $service = new UserPermissionsService();

        $this->assertTrue($service->can($user, 'administration'));
        $this->assertTrue($service->can($user, 'administration.templates'));
        $this->assertTrue($service->can($user, 'administration.users'));

        $this->assertFalse($service->can($user, 'administration.emitters'));
        $this->assertFalse($service->can($user, 'administration.user-profiles'));
        $this->assertFalse($service->can($user, 'administration.theming'));
    }

    public function test_parent_only_permission_still_grants_all_its_children(): void
    {
        // When only the parent is checked (no explicit children at all), the parent
        // grants access to the whole module — this must keep working as before
        // (e.g. a profile that only checks "courrier" gets all courrier.* screens).
        $profile = AdministrationProfile::create([
            'name' => 'Profil module complet',
            'permissions' => ['menuPermissions' => ['courrier']],
        ]);

        $user = User::factory()->create([
            'role' => 'user',
            'profile_id' => $profile->id,
        ]);

        $service = new UserPermissionsService();

        $this->assertTrue($service->can($user, 'courrier.liste'));
        $this->assertTrue($service->can($user, 'courrier.archives'));
    }
}
