<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('courriers')) {
            return;
        }

        Schema::table('courriers', function (Blueprint $table) {
            if (!Schema::hasColumn('courriers', 'emetteur_administration_id')) {
                $table->uuid('emetteur_administration_id')->nullable()->after('administration_id')->index();
            }

            if (!Schema::hasColumn('courriers', 'destinataire_administration_id')) {
                $table->uuid('destinataire_administration_id')->nullable()->after('emetteur_administration_id')->index();
            }

            if (!Schema::hasColumn('courriers', 'source_document_id')) {
                $table->uuid('source_document_id')->nullable()->after('destinataire_administration_id')->index();
            }

            if (!Schema::hasColumn('courriers', 'is_system_generated')) {
                $table->boolean('is_system_generated')->default(false)->after('source_document_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('courriers')) {
            return;
        }

        Schema::table('courriers', function (Blueprint $table) {
            if (Schema::hasColumn('courriers', 'source_document_id')) {
                $table->dropIndex(['source_document_id']);
            }
            if (Schema::hasColumn('courriers', 'destinataire_administration_id')) {
                $table->dropIndex(['destinataire_administration_id']);
            }
            if (Schema::hasColumn('courriers', 'emetteur_administration_id')) {
                $table->dropIndex(['emetteur_administration_id']);
            }

            $table->dropColumn([
                'emetteur_administration_id',
                'destinataire_administration_id',
                'source_document_id',
                'is_system_generated',
            ]);
        });
    }
};
