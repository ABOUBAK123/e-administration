<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute nni_hash (clé de rapprochement automatique, jamais réversible) et
 * nni_masked (affichage uniquement) aux tables portant des données de demandeur.
 * Le NNI en clair n'est jamais stocké.
 */
return new class extends Migration
{
    private array $tables = ['act_request_submissions', 'document_shares', 'courriers'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'nni_hash')) {
                    $table->string('nni_hash', 64)->nullable();
                    $table->index('nni_hash', $tableName . '_nni_hash_idx');
                }

                if (!Schema::hasColumn($tableName, 'nni_masked')) {
                    $table->string('nni_masked', 40)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'nni_hash')) {
                    $table->dropIndex($tableName . '_nni_hash_idx');
                }

                $table->dropColumn(array_filter(['nni_hash', 'nni_masked'], fn ($col) => Schema::hasColumn($tableName, $col)));
            });
        }
    }
};
