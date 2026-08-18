<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dossiers d'état civil (base documentaire d'une administration à numériser),
     * distincts des demandes publiques d'actes (requested_acts / act_request_submissions).
     */
    public function up(): void
    {
        Schema::create('civil_status_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('record_type_id')->constrained('civil_status_record_types')->cascadeOnDelete();
            $table->string('administration_type', 20)->default('emitter');
            $table->string('administration_id', 36);
            $table->char('sub_entity_id', 36)->nullable()->index();
            $table->string('reference_number', 100)->unique();
            $table->string('subject_name', 255);
            $table->date('event_date')->nullable();
            $table->string('event_place', 191)->nullable();
            $table->string('declarant_name', 191)->nullable();
            $table->string('declarant_contact', 191)->nullable();
            $table->json('data')->nullable();
            $table->string('status', 30)->default('draft');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->uuid('generated_document_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['administration_type', 'administration_id'], 'cs_rec_admin_idx');
            $table->index('status', 'cs_rec_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civil_status_records');
    }
};
