<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('requested_acts')) {
            return;
        }

        Schema::table('requested_acts', function (Blueprint $table) {
            if (!Schema::hasColumn('requested_acts', 'is_paid')) {
                $table->boolean('is_paid')->default(false)->after('is_active');
            }

            if (!Schema::hasColumn('requested_acts', 'amount')) {
                $table->decimal('amount', 12, 2)->nullable()->after('is_paid');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('requested_acts')) {
            return;
        }

        Schema::table('requested_acts', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'amount']);
        });
    }
};
