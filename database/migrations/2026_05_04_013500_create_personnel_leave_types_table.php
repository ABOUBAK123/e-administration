<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('personnel_leave_types');

        Schema::create('personnel_leave_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('administration_type', 20);
            $table->string('administration_id', 50);
            $table->string('code', 100)->nullable();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('day');
            $table->decimal('default_days', 8, 2)->nullable();
            $table->decimal('carry_over_days', 8, 2)->default(0);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['administration_type', 'administration_id', 'code'], 'pers_leave_type_code_uq');
            $table->index(['administration_type', 'administration_id'], 'pers_leave_type_admin_idx');
            $table->index(['administration_type', 'administration_id', 'is_active'], 'pers_leave_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_leave_types');
    }
};
