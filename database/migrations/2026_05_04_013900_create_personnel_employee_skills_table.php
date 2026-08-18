<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_employee_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('personnel_employees')->cascadeOnDelete();
            $table->string('administration_type', 20);
            $table->string('administration_id', 50);
            $table->string('skill_name', 191);
            $table->string('category', 100)->nullable();
            $table->unsignedTinyInteger('current_level')->default(1);
            $table->unsignedTinyInteger('target_level')->nullable();
            $table->date('assessment_date')->nullable();
            $table->string('source', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['administration_type', 'administration_id'], 'pers_skill_admin_idx');
            $table->index(['employee_id', 'category'], 'pers_skill_emp_cat_idx');
            $table->unique(['employee_id', 'skill_name'], 'pers_skill_emp_name_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_employee_skills');
    }
};
