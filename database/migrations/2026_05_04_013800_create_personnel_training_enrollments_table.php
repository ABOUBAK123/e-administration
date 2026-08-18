<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_training_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('personnel_employees')->cascadeOnDelete();
            $table->foreignUuid('training_id')->constrained('personnel_trainings')->cascadeOnDelete();
            $table->foreignUuid('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('administration_type', 20);
            $table->string('administration_id', 50);
            $table->string('status', 50)->default('planned');
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('attendance_rate', 5, 2)->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('satisfaction_score', 5, 2)->nullable();
            $table->string('certificate_disk', 50)->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('certificate_original_name')->nullable();
            $table->string('certificate_mime_type', 191)->nullable();
            $table->unsignedBigInteger('certificate_size')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['administration_type', 'administration_id'], 'pers_train_enr_admin_idx');
            $table->index(['employee_id', 'status'], 'pers_train_enr_emp_stat_idx');
            $table->unique(['employee_id', 'training_id'], 'pers_train_enr_emp_train_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_training_enrollments');
    }
};
