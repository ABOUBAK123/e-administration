<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mobile_money_provider_configs')) {
            return;
        }

        Schema::table('mobile_money_provider_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('mobile_money_provider_configs', 'environment')) {
                $table->string('environment', 20)->default('sandbox')->after('provider')->comment('sandbox|production');
            }

            if (!Schema::hasColumn('mobile_money_provider_configs', 'currency')) {
                $table->string('currency', 10)->default('XOF')->after('environment');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mobile_money_provider_configs')) {
            return;
        }

        Schema::table('mobile_money_provider_configs', function (Blueprint $table) {
            $table->dropColumn(['environment', 'currency']);
        });
    }
};
