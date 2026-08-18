<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('meeting_id')->index();
            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->uuid('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('email', 191)->nullable()->index();
            $table->string('full_name', 255)->nullable();
            $table->enum('participant_role', ['chair', 'participant', 'guest', 'observer'])->default('participant')->index();
            $table->boolean('is_external')->default(false)->index();
            $table->enum('invitation_status', ['pending', 'sent', 'accepted', 'declined'])->default('pending')->index();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};
