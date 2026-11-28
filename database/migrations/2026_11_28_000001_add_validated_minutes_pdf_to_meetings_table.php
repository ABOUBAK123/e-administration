<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meetings') || Schema::hasColumn('meetings', 'minutes_pdf_path')) {
            return;
        }

        Schema::table('meetings', function (Blueprint $table) {
            $table->string('minutes_pdf_path', 500)->nullable();
            $table->string('minutes_pdf_sha256', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('meetings', 'minutes_pdf_path')) {
            Schema::table('meetings', fn (Blueprint $table) => $table->dropColumn(['minutes_pdf_path', 'minutes_pdf_sha256']));
        }
    }
};
