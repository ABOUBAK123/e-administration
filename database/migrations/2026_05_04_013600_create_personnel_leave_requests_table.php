<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('personnel_leave_requests');

        Schema::create('personnel_leave_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('personnel_employees')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->constrained('personnel_leave_types')->restrictOnDelete();
            $table->foreignUuid('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('administration_type', 20);
            $table->string('administration_id', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('return_date')->nullable();
            $table->decimal('requested_days', 8, 2)->default(0);
            $table->decimal('approved_days', 8, 2)->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('reason')->nullable();
            $table->text('manager_comments')->nullable();
            $table->text('hr_comments')->nullable();
            $table->boolean('unexpected_absence')->default(false);
            $table->string('attachment_disk', 50)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->string('attachment_mime_type', 191)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['administration_type', 'administration_id'], 'pers_leave_req_admin_idx');
            $table->index(['employee_id', 'status'], 'pers_leave_req_emp_stat_idx');
            $table->index(['leave_type_id', 'status'], 'pers_leave_req_type_stat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_leave_requests');
    }
};
