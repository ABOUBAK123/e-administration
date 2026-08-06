<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'reminder_sent_at')) {
                $table->dateTime('reminder_sent_at')->nullable()->after('starts_at');
            }

            if (!Schema::hasColumn('meetings', 'validation_reminder_sent_at')) {
                $table->dateTime('validation_reminder_sent_at')->nullable()->after('validation_requested_at');
            }

            if (!Schema::hasColumn('meetings', 'deadline_alert_sent_at')) {
                $table->dateTime('deadline_alert_sent_at')->nullable()->after('processing_deadline');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('meetings')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'reminder_sent_at')) {
                $table->dropColumn('reminder_sent_at');
            }
            if (Schema::hasColumn('meetings', 'validation_reminder_sent_at')) {
                $table->dropColumn('validation_reminder_sent_at');
            }
            if (Schema::hasColumn('meetings', 'deadline_alert_sent_at')) {
                $table->dropColumn('deadline_alert_sent_at');
            }
        });
    }
};
