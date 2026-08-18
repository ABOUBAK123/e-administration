<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('meeting_id')->index();
            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->uuid('meeting_participant_id')->nullable()->index();
            $table->foreign('meeting_participant_id')->references('id')->on('meeting_participants')->nullOnDelete();
            $table->string('identifier', 191)->nullable()->index();
            $table->string('full_name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('job_title', 255)->nullable();
            $table->string('organization', 255)->nullable();
            $table->string('attendance_status', 20)->default('present')->index();
            $table->dateTime('signed_at')->index();
            $table->string('signature_path', 1000)->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendances');
    }
};
