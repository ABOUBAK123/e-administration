<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mobile_money_provider_configs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('administration_id')->nullable()->index()->comment('NULL = config globale');
            $table->string('administration_type', 20)->default('emitter')->comment('emitter|recipient');
            $table->string('provider', 40)->comment('orange_money|mtn_money|moov_money|wave|autre');
            $table->string('label')->nullable()->comment('Nom affiché, utile si provider = autre');
            $table->boolean('is_active')->default(false);
            $table->string('endpoint')->nullable()->comment('URL de base de l\'API du fournisseur');
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('callback_url')->nullable();
            $table->boolean('verify_ssl')->default(true);
            $table->timestamps();

            $table->unique(['administration_id', 'administration_type', 'provider'], 'uniq_mobile_money_admin_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_money_provider_configs');
    }
};
