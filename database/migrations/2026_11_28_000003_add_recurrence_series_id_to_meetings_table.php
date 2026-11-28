<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meetings') && !Schema::hasColumn('meetings', 'recurrence_series_id')) {
            Schema::table('meetings', fn (Blueprint $table) => $table->uuid('recurrence_series_id')->nullable()->index());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meetings', 'recurrence_series_id')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->dropIndex(['recurrence_series_id']);
                $table->dropColumn('recurrence_series_id');
            });
        }
    }
};
