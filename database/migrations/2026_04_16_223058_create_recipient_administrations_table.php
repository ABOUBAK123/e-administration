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
        Schema::create('recipient_administrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 125)->unique();
            $table->enum('channel', ['api', 'email', 'ler', 'application']);
            $table->string('api_endpoint', 1000)->nullable();
            $table->string('email_address', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipient_administrations');
    }
};
