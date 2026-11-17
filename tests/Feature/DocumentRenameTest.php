<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentRenameTest extends TestCase
{
    use RefreshDatabase;

    private function createDocument(User $owner, array $overrides = []): Document
    {
        return Document::create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Ancien nom.docx',
            'file_path' => '/storage/documents/' . Str::uuid() . '.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'status' => 'draft',
            'owner_id' => $owner->id,
            'created_by' => $owner->id,
        ], $overrides));
    }

    public function test_owner_can_rename_a_file(): void
    {
        $owner = User::factory()->create();
        $document = $this->createDocument($owner);

        $response = $this->actingAs($owner)->postJson(route('documents.rename', $document->id), [
            'title' => 'Nouveau nom.docx',
        ]);

        $response->assertOk();
        $response->assertJson(['title' => 'Nouveau nom.docx']);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Nouveau nom.docx',
        ]);
    }

    public function test_owner_can_rename_a_folder(): void
    {
        // Les dossiers sont des Document avec description = '[folder]' (voir index.blade.php isFolder()).
        $owner = User::factory()->create();
        $folder = $this->createDocument($owner, [
            'title' => 'Ancien dossier',
            'description' => '[folder]',
            'file_path' => '',
            'mime_type' => 'application\\x-folder',
        ]);

        $response = $this->actingAs($owner)->postJson(route('documents.rename', $folder->id), [
            'title' => 'Nouveau dossier',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('documents', [
            'id' => $folder->id,
            'title' => 'Nouveau dossier',
            'description' => '[folder]',
        ]);
    }

    public function test_non_owner_cannot_rename_the_document(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $document = $this->createDocument($owner);

        $response = $this->actingAs($otherUser)->postJson(route('documents.rename', $document->id), [
            'title' => 'Tentative de renommage',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Ancien nom.docx',
        ]);
    }

    public function test_empty_title_is_rejected(): void
    {
        $owner = User::factory()->create();
        $document = $this->createDocument($owner);

        $response = $this->actingAs($owner)->postJson(route('documents.rename', $document->id), [
            'title' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    }
}
