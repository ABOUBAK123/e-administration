<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meetings') && !Schema::hasColumn('meetings', 'reminder_hours_before')) {
            Schema::table('meetings', fn (Blueprint $table) => $table->unsignedSmallInteger('reminder_hours_before')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meetings', 'reminder_hours_before')) {
            Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn('reminder_hours_before'));
        }
    }
};
