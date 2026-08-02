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
            if (!Schema::hasColumn('act_request_submissions', 'unique_key_value')) {
                $table->string('unique_key_value', 180)->nullable()->after('tracking_token');
                $table->index(['requested_act_id', 'unique_key_value'], 'act_req_requested_act_unique_key_idx');
            }

            if (!Schema::hasColumn('act_request_submissions', 'auto_generated_document_id')) {
                $table->uuid('auto_generated_document_id')->nullable()->after('attachments');
                $table->index('auto_generated_document_id', 'act_req_auto_generated_document_idx');
            }

            if (!Schema::hasColumn('act_request_submissions', 'auto_generated_at')) {
                $table->timestamp('auto_generated_at')->nullable()->after('auto_generated_document_id');
            }

            if (!Schema::hasColumn('act_request_submissions', 'validated_by_user_id')) {
                $table->uuid('validated_by_user_id')->nullable()->after('auto_generated_at');
                $table->index('validated_by_user_id', 'act_req_validated_by_user_idx');
            }

            if (!Schema::hasColumn('act_request_submissions', 'validated_at')) {
                $table->timestamp('validated_at')->nullable()->after('validated_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('act_request_submissions')) {
            return;
        }

        Schema::table('act_request_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('act_request_submissions', 'unique_key_value')) {
                $table->dropIndex('act_req_requested_act_unique_key_idx');
            }
            if (Schema::hasColumn('act_request_submissions', 'auto_generated_document_id')) {
                $table->dropIndex('act_req_auto_generated_document_idx');
            }
            if (Schema::hasColumn('act_request_submissions', 'validated_by_user_id')) {
                $table->dropIndex('act_req_validated_by_user_idx');
            }

            $table->dropColumn([
                'unique_key_value',
                'auto_generated_document_id',
                'auto_generated_at',
                'validated_by_user_id',
                'validated_at',
            ]);
        });
    }
};
