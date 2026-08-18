<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pièces jointes rattachées à un dossier d'état civil (même convention que
     * personnel_employee_documents).
     */
    public function up(): void
    {
        Schema::create('civil_status_record_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('record_id')->constrained('civil_status_records')->cascadeOnDelete();
            $table->string('category', 100);
            $table->string('label', 191);
            $table->string('disk', 50)->default('local');
            $table->string('path', 1000);
            $table->string('original_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'category'], 'cs_rec_doc_rec_cat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civil_status_record_documents');
    }
};
