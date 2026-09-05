<?php

namespace Tests\Feature;

use App\Models\AdministrationProfile;
use App\Models\IssuingAdministration;
use App\Models\MobileMoneyProviderConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileMoneyProviderConfigAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'profile_id' => null,
        ]);
    }

    public function test_admin_can_create_a_mobile_money_config(): void
    {
        $admin = $this->createSuperAdmin();
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère Test',
            'code' => 'MIN-' . Str::random(5),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.mobile-money.store'), [
            'mm_admin_id' => $administration->id,
            'mm_admin_type' => 'emitter',
            'provider' => 'orange_money',
            'endpoint' => 'https://api.orange.ci',
            'api_key' => 'test-key',
            'merchant_id' => 'MERCH-1',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mobile_money_provider_configs', [
            'administration_id' => $administration->id,
            'provider' => 'orange_money',
            'merchant_id' => 'MERCH-1',
        ]);
    }

    public function test_resubmitting_same_provider_updates_instead_of_duplicating(): void
    {
        $admin = $this->createSuperAdmin();
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère Test 2',
            'code' => 'MIN2-' . Str::random(5),
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.mobile-money.store'), [
            'mm_admin_id' => $administration->id,
            'provider' => 'wave',
            'endpoint' => 'https://old.example.com',
        ]);

        $this->actingAs($admin)->post(route('admin.mobile-money.store'), [
            'mm_admin_id' => $administration->id,
            'provider' => 'wave',
            'endpoint' => 'https://new.example.com',
        ]);

        $this->assertSame(1, MobileMoneyProviderConfig::where('administration_id', $administration->id)->count());
        $this->assertDatabaseHas('mobile_money_provider_configs', [
            'administration_id' => $administration->id,
            'provider' => 'wave',
            'endpoint' => 'https://new.example.com',
        ]);
    }

    public function test_user_without_mobile_money_permission_is_rejected(): void
    {
        $profile = AdministrationProfile::create([
            'name' => 'Profil restreint',
            'permissions' => ['menuPermissions' => ['dashboard']],
        ]);
        $user = User::factory()->create(['role' => 'user', 'profile_id' => $profile->id]);

        $response = $this->actingAs($user)->post(route('admin.mobile-money.store'), [
            'mm_admin_id' => (string) Str::uuid(),
            'provider' => 'mtn_money',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_delete_a_mobile_money_config(): void
    {
        $admin = $this->createSuperAdmin();
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère Test 3',
            'code' => 'MIN3-' . Str::random(5),
            'is_active' => true,
        ]);

        $config = MobileMoneyProviderConfig::create([
            'administration_id' => $administration->id,
            'administration_type' => 'emitter',
            'provider' => 'moov_money',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.mobile-money.destroy', $config->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('mobile_money_provider_configs', ['id' => $config->id]);
    }
}
