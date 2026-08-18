<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('personnel_employees')->cascadeOnDelete();
            $table->foreignUuid('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('administration_type', 20);
            $table->string('administration_id', 50);
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('goal_type', 50)->default('individual');
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('target_value', 10, 2)->nullable();
            $table->decimal('current_value', 10, 2)->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 50)->default('draft');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['administration_type', 'administration_id'], 'pers_goal_admin_idx');
            $table->index(['employee_id', 'status'], 'pers_goal_emp_stat_idx');
            $table->index(['goal_type', 'status'], 'pers_goal_type_stat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_goals');
    }
};
