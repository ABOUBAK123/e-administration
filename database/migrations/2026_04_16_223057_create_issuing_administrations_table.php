<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('issuing_administrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 125)->unique();
            $table->string('code', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('document_number_prefix', 50)->default('DOC');
            $table->integer('document_number_padding')->default(6);
            $table->integer('document_number_sequence')->default(0);
            $table->string('logo', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issuing_administrations');
    }
};
