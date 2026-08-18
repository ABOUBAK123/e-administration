<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Types de dossiers d'état civil paramétrables par administration
     * (ex: Naissance, Mariage, Décès, ou tout autre type spécifique).
     */
    public function up(): void
    {
        Schema::create('civil_status_record_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('administration_type', 20)->default('emitter');
            $table->string('administration_id', 36);
            $table->string('code', 50);
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->json('required_documents')->nullable();
            $table->json('fields_schema')->nullable();
            $table->uuid('auto_template_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['administration_type', 'administration_id', 'code'], 'cs_rec_type_admin_code_uq');
            $table->index(['administration_type', 'administration_id'], 'cs_rec_type_admin_idx');
            $table->index('auto_template_id', 'cs_rec_type_auto_template_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civil_status_record_types');
    }
};
