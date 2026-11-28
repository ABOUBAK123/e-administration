<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meeting_minutes_receipts')) {
            Schema::create('meeting_minutes_receipts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('meeting_id');
                $table->string('email', 191);
                $table->string('name')->nullable();
                $table->string('token', 64)->unique();
                $table->dateTime('sent_at')->nullable();
                $table->unsignedInteger('send_count')->default(0);
                $table->dateTime('acknowledged_at')->nullable();
                $table->timestamps();
                $table->unique(['meeting_id', 'email']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes_receipts');
    }
};
