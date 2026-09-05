<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('act_request_submissions')) {
            return;
        }

        Schema::table('act_request_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('act_request_submissions', 'mobile_money_transaction_id')) {
                $table->uuid('mobile_money_transaction_id')->nullable()->after('status');
            }

            if (!Schema::hasColumn('act_request_submissions', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('mobile_money_transaction_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('act_request_submissions')) {
            return;
        }

        Schema::table('act_request_submissions', function (Blueprint $table) {
            $table->dropColumn(['mobile_money_transaction_id', 'paid_at']);
        });
    }
};
