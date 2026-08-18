<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_career_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('personnel_employees')->cascadeOnDelete();
            $table->foreignUuid('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('administration_type', 20);
            $table->string('administration_id', 50);
            $table->string('event_type', 50)->default('mobility');
            $table->date('effective_date')->nullable();
            $table->string('title', 191);
            $table->string('previous_job_title', 191)->nullable();
            $table->string('new_job_title', 191)->nullable();
            $table->string('status', 50)->default('planned');
            $table->text('summary')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['administration_type', 'administration_id'], 'pers_career_admin_idx');
            $table->index(['employee_id', 'event_type'], 'pers_career_emp_type_idx');
            $table->index(['status', 'effective_date'], 'pers_career_stat_eff_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_career_events');
    }
};
