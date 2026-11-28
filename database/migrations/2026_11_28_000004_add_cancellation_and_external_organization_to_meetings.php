<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meetings') && !Schema::hasColumn('meetings', 'cancelled_at')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dateTime('cancelled_at')->nullable();
                $table->uuid('cancelled_by')->nullable();
                $table->text('cancellation_reason')->nullable();
            });
        }

        if (Schema::hasTable('meeting_participants') && !Schema::hasColumn('meeting_participants', 'organization')) {
            Schema::table('meeting_participants', fn (Blueprint $table) => $table->string('organization', 255)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meetings', 'cancelled_at')) {
            Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancellation_reason']));
        }
        if (Schema::hasColumn('meeting_participants', 'organization')) {
            Schema::table('meeting_participants', fn (Blueprint $table) => $table->dropColumn('organization'));
        }
    }
};
