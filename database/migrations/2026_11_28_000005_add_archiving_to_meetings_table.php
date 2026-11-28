<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meetings') && !Schema::hasColumn('meetings', 'archived_at')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dateTime('archived_at')->nullable()->index();
                $table->dateTime('archive_attempted_at')->nullable();
                $table->uuid('archived_minutes_document_id')->nullable();
                $table->uuid('archived_attendance_document_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meetings', 'archived_at')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropIndex(['archived_at']);
                $table->dropColumn(['archived_at', 'archive_attempted_at', 'archived_minutes_document_id', 'archived_attendance_document_id']);
            });
        }
    }
};
