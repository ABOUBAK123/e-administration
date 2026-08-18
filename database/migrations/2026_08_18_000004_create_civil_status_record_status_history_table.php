<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des changements de statut d'un dossier d'état civil (traçabilité).
     */
    public function up(): void
    {
        Schema::create('civil_status_record_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('record_id')->constrained('civil_status_records')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->uuid('changed_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('record_id', 'cs_rec_hist_rec_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civil_status_record_status_history');
    }
};
