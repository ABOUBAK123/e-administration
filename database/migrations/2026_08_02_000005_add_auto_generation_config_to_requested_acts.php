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
            if (!Schema::hasColumn('requested_acts', 'auto_generate_enabled')) {
                $table->boolean('auto_generate_enabled')->default(false)->after('applicant_fields');
            }

            if (!Schema::hasColumn('requested_acts', 'auto_template_id')) {
                $table->uuid('auto_template_id')->nullable()->after('auto_generate_enabled');
                $table->index('auto_template_id', 'requested_acts_auto_template_id_idx');
            }

            if (!Schema::hasColumn('requested_acts', 'unique_key_field')) {
                $table->string('unique_key_field', 120)->nullable()->after('auto_template_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('requested_acts')) {
            return;
        }

        Schema::table('requested_acts', function (Blueprint $table) {
            if (Schema::hasColumn('requested_acts', 'auto_template_id')) {
                $table->dropIndex('requested_acts_auto_template_id_idx');
            }

            $table->dropColumn([
                'auto_generate_enabled',
                'auto_template_id',
                'unique_key_field',
            ]);
        });
    }
};
