<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadAjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_response_includes_the_same_ownership_fields_as_the_initial_page_load(): void
    {
        // Regression test: uploadAjax() used to return only {id, title, file_path,
        // mime_type}. The frontend then unshift()-ed that partial object straight
        // into allDocs, so a freshly uploaded file rendered as "Partagé avec moi
        // (lecture seule)" and without Renommer/Déplacer — missing is_owner,
        // can_share, can_edit_content, shares_count etc. that the server-rendered
        // initial list (index.blade.php $docsJson) always includes for the owner.
        // It only looked correct after a full page reload — hence "must refresh
        // manually". The response must now carry every field the initial list has.
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin', 'profile_id' => null]);
        $file = UploadedFile::fake()->create('rapport.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->postJson(route('documents.uploadAjax'), [
            'file' => $file,
            'title' => 'Rapport annuel',
        ]);

        $response->assertCreated();
        $response->assertJson([
            'is_owner' => true,
            'can_share' => true,
            'can_edit_content' => true,
            'share_permission' => 'modification',
            'shares_count' => 0,
            'status' => 'draft',
            'title' => 'Rapport annuel',
        ]);
        $response->assertJsonStructure([
            'id', 'owner_id', 'is_owner', 'can_share', 'share_permission', 'can_edit_content',
            'title', 'description', 'file_path', 'final_file_path', 'file_size', 'mime_type',
            'status', 'shares_count', 'paraphed_by_me', 'is_courrier_depart_tagged',
            'next_courrier_depart_number', 'act_validation', 'created_at', 'updated_at',
        ]);
    }

    public function test_upload_into_a_folder_returns_the_folder_description(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'admin', 'profile_id' => null]);
        $file = UploadedFile::fake()->create('note.pdf', 50, 'application/pdf');

        $response = $this->actingAs($user)->postJson(route('documents.uploadAjax'), [
            'file' => $file,
            'title' => 'Note',
            'folder' => 'MonDossier',
        ]);

        $response->assertCreated();
        $response->assertJson(['description' => 'Dossier: MonDossier']);
    }
}
