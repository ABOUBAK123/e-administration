<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (!Schema::hasTable('documents')) {
            return;
        }

        // Add 'sent' to enum values so shares to recipient administrations can persist this status.
        DB::statement("ALTER TABLE documents MODIFY COLUMN status ENUM('draft','active','signed','archived','pending_signature','sent') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        // No-op to avoid data loss if rows already use 'sent'.
    }
};
