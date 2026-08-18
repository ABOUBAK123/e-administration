<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('personnel_job_references');

        Schema::create('personnel_job_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('administration_type', 20);
            $table->string('administration_id', 36);
            $table->string('reference_type', 20); // grade, employment, function
            $table->string('label', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['administration_type', 'administration_id'], 'pers_job_ref_admin_idx');
            $table->index(['reference_type', 'is_active'], 'pers_job_ref_type_active_idx');
            $table->unique(['administration_type', 'administration_id', 'reference_type', 'label'], 'pers_job_ref_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_job_references');
    }
};
