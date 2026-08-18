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
        Schema::create('administration_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->foreign('administration_id')->references('id')->on('issuing_administrations')->onDelete('cascade');
            $table->uuid('profile_id')->nullable();
            $table->string('full_name', 255);
            $table->string('email', 191)->unique();
            $table->string('username', 150)->unique();
            $table->string('password_hash', 255);
            $table->enum('admin_role', ['super_admin', 'admin', 'manager', 'user', 'signer'])->default('user');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administration_users');
    }
};
