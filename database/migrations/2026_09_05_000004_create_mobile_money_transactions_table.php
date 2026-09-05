<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureInnoDb('act_request_submissions');

        // Repli défensif : si une exécution précédente a créé la table sans pouvoir
        // ajouter ses clés étrangères (table référencée non-InnoDB), on repart propre.
        if (Schema::hasTable('mobile_money_transactions')) {
            Schema::dropIfExists('mobile_money_transactions');
        }

        Schema::create('mobile_money_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('act_request_submission_id')->index();
            $table->uuid('mobile_money_provider_config_id')->nullable()->index();
            $table->string('provider', 40);
            $table->string('external_id', 60)->unique()->comment('Notre reference envoyee au fournisseur (X-Reference-Id)');
            $table->string('phone_number', 30);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10);
            $table->string('status', 20)->default('pending')->comment('pending|successful|failed');
            $table->string('financial_transaction_id')->nullable();
            $table->string('reason')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->foreign('act_request_submission_id', 'mm_transactions_submission_fk')
                ->references('id')->on('act_request_submissions')->cascadeOnDelete();
            $table->foreign('mobile_money_provider_config_id', 'mm_transactions_provider_config_fk')
                ->references('id')->on('mobile_money_provider_configs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_transactions');
    }

    /**
     * Les clés étrangères MySQL exigent InnoDB des deux côtés. Certaines tables plus
     * anciennes de cette base sont restées en MyISAM (config serveur historique) ;
     * on les convertit à la demande, uniquement quand une FK doit s'y appuyer.
     */
    private function ensureInnoDb(string $table): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) || !Schema::hasTable($table)) {
            return;
        }

        $engine = DB::selectOne(
            'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [DB::getDatabaseName(), $table]
        )->ENGINE ?? null;

        if ($engine !== null && strtoupper($engine) !== 'INNODB') {
            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }
    }
};
