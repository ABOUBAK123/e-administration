<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('act_request_submissions') || !Schema::hasColumn('act_request_submissions', 'status') || !in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Environnements historiques: colonne status en ENUM restreint.
        // On autorise explicitement les nouveaux etats utilises par le workflow.
        DB::statement("ALTER TABLE act_request_submissions MODIFY COLUMN status ENUM('pending','in_progress','sent','recu','treated','rejected') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('act_request_submissions') || !Schema::hasColumn('act_request_submissions', 'status') || !in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE act_request_submissions MODIFY COLUMN status ENUM('pending','in_progress','treated','rejected') NOT NULL DEFAULT 'pending'");
    }
};
