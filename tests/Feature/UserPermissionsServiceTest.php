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
}
