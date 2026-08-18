<?php

namespace Tests\Feature;

use App\Models\CivilStatusRecordType;
use App\Models\IssuingAdministration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CivilStatusRecordTypeAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'profile_id' => null,
        ]);
    }

    public function test_admin_can_view_civil_status_types_tab(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin.index', ['tab' => 'civil-status-types']));

        $response->assertOk();
        $response->assertSee('Types de dossiers État civil');
    }

    public function test_admin_can_create_a_civil_status_record_type(): void
    {
        $admin = $this->createSuperAdmin();
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère État civil',
            'code' => 'MEC-' . Str::random(5),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.civil-status-types.store'), [
            'administration_type' => 'emitter',
            'administration_id' => $administration->id,
            'code' => 'naissance',
            'name' => 'Naissance',
            'description' => "Déclaration de naissance à numériser",
            'required_documents' => json_encode(['Certificat médical de naissance', "Pièce d'identité du déclarant"]),
            'fields_schema' => json_encode([
                ['label' => 'Nom du père', 'inputType' => 'text'],
                ['label' => 'Nom de la mère', 'inputType' => 'text'],
            ]),
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('civil_status_record_types', [
            'administration_id' => $administration->id,
            'code' => 'NAISSANCE',
            'name' => 'Naissance',
        ]);

        $type = CivilStatusRecordType::where('code', 'NAISSANCE')->firstOrFail();
        $this->assertCount(2, $type->required_documents);
        $this->assertCount(2, $type->fields_schema);
        $this->assertSame('text', $type->fields_schema[0]['inputType']);
    }

    public function test_duplicate_code_for_same_administration_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère État civil 2',
            'code' => 'MEC2-' . Str::random(5),
            'is_active' => true,
        ]);

        CivilStatusRecordType::create([
            'id' => (string) Str::uuid(),
            'administration_type' => 'emitter',
            'administration_id' => $administration->id,
            'code' => 'MARIAGE',
            'name' => 'Mariage',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.civil-status-types.store'), [
            'administration_type' => 'emitter',
            'administration_id' => $administration->id,
            'code' => 'mariage',
            'name' => 'Mariage (doublon)',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertSame(1, CivilStatusRecordType::where('administration_id', $administration->id)->count());
    }

    public function test_type_used_by_a_record_cannot_be_deleted(): void
    {
        $admin = $this->createSuperAdmin();
        $administration = IssuingAdministration::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ministère État civil 3',
            'code' => 'MEC3-' . Str::random(5),
            'is_active' => true,
        ]);

        $type = CivilStatusRecordType::create([
            'id' => (string) Str::uuid(),
            'administration_type' => 'emitter',
            'administration_id' => $administration->id,
            'code' => 'DECES',
            'name' => 'Décès',
            'is_active' => true,
        ]);

        $type->records()->create([
            'id' => (string) Str::uuid(),
            'administration_type' => 'emitter',
            'administration_id' => $administration->id,
            'reference_number' => 'DECES-2026-000001',
            'subject_name' => 'Jean Test',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.civil-status-types.destroy', $type->id));

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseHas('civil_status_record_types', ['id' => $type->id]);
    }
}
