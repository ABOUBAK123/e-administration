<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_trainings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('administration_type', 20);
            $table->string('administration_id', 50);
            $table->string('code', 100)->nullable();
            $table->string('title', 191);
            $table->string('category', 100)->nullable();
            $table->string('provider_name', 191)->nullable();
            $table->string('delivery_mode', 50)->default('internal');
            $table->decimal('duration_hours', 8, 2)->nullable();
            $table->decimal('budget_amount', 12, 2)->nullable();
            $table->unsignedInteger('validity_months')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->json('skills')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['administration_type', 'administration_id', 'code'], 'pers_train_code_uq');
            $table->index(['administration_type', 'administration_id'], 'pers_train_admin_idx');
            $table->index(['administration_type', 'administration_id', 'is_active'], 'pers_train_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_trainings');
    }
};
