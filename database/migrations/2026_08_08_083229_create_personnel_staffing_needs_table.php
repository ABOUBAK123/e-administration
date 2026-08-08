<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_staffing_needs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('administration_type', 20)->default('emitter');
            $table->string('administration_id', 36)->nullable();
            $table->char('sub_entity_id', 36)->nullable()->index();
            $table->string('job_title', 191);
            $table->unsignedInteger('required_count')->default(1);
            $table->unsignedInteger('current_count')->default(0);
            $table->string('priority', 20)->default('normal'); // urgent, high, normal, low
            $table->string('status', 20)->default('open'); // open, filled, cancelled
            $table->date('target_date')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['administration_type', 'administration_id'], 'pers_staffing_admin_scope_idx');
            $table->index('status', 'pers_staffing_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_staffing_needs');
    }
};
