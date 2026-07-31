<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('routing_rules', function (Blueprint $table) {
            $table->string('target_type', 20)->default('recipient')->after('template_id');
            $table->uuid('target_user_id')->nullable()->after('recipient_id');

            $table->index(['target_type', 'target_user_id'], 'routing_rules_target_type_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routing_rules', function (Blueprint $table) {
            $table->dropIndex('routing_rules_target_type_user_idx');
            $table->dropColumn(['target_type', 'target_user_id']);
        });
    }
};
