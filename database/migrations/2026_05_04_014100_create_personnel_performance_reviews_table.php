<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_performance_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('personnel_employees')->cascadeOnDelete();
            $table->foreignUuid('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('administration_type', 20);
            $table->string('administration_id', 50);
            $table->string('review_type', 50)->default('annual');
            $table->string('title', 191);
            $table->string('period_label', 100)->nullable();
            $table->date('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 50)->default('scheduled');
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('manager_comments')->nullable();
            $table->text('employee_comments')->nullable();
            $table->text('recommendations')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['administration_type', 'administration_id'], 'pers_rev_admin_idx');
            $table->index(['employee_id', 'status'], 'pers_rev_emp_stat_idx');
            $table->index(['review_type', 'status'], 'pers_rev_type_stat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_performance_reviews');
    }
};
