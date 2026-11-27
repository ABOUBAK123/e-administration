<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onlyoffice_server_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('administration_id');
            $table->string('administration_type', 20)->default('emitter')->comment('emitter|recipient');
            $table->boolean('is_active')->default(true);
            $table->string('server_url', 500);
            $table->text('secret')->nullable()->comment('Clé secrète JWT, chiffrée');
            $table->boolean('disable_cert')->default(false);
            $table->timestamps();

            $table->unique(['administration_id', 'administration_type'], 'uniq_onlyoffice_admin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onlyoffice_server_configs');
    }
};
