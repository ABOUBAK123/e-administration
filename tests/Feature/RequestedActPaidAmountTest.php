<?php

namespace Tests\Feature;

use App\Models\IssuingAdministration;
use App\Models\RequestedAct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestedActPaidAmountTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'profile_id' => null,
        ]);
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

    public function test_creating_a_paid_act_without_amount_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        $administration = $this->createAdministration();

        $response = $this->actingAs($admin)->post(route('admin.requested-acts.store'), [
            'administration_id' => $administration->id,
            'document_name' => 'Extrait de naissance',
            'is_paid' => '1',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('requested_acts', ['document_name' => 'Extrait de naissance']);
    }

    public function test_creating_a_paid_act_with_amount_succeeds(): void
    {
        $admin = $this->createSuperAdmin();
        $administration = $this->createAdministration();

        $response = $this->actingAs($admin)->post(route('admin.requested-acts.store'), [
            'administration_id' => $administration->id,
            'document_name' => 'Extrait de naissance',
            'is_paid' => '1',
            'amount' => '2000',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('requested_acts', [
            'document_name' => 'Extrait de naissance',
            'is_paid' => true,
            'amount' => 2000,
        ]);
    }

    public function test_unpaid_act_stores_null_amount_even_if_amount_field_is_sent(): void
    {
        $admin = $this->createSuperAdmin();
        $administration = $this->createAdministration();

        $this->actingAs($admin)->post(route('admin.requested-acts.store'), [
            'administration_id' => $administration->id,
            'document_name' => 'Certificat de résidence',
            'is_paid' => '0',
            'amount' => '5000',
        ]);

        $act = RequestedAct::where('document_name', 'Certificat de résidence')->firstOrFail();
        $this->assertFalse($act->is_paid);
        $this->assertNull($act->amount);
    }
}
