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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name')->nullable()->after('name');
            }

            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }

            if (!Schema::hasColumn('users', 'two_factor_code')) {
                $table->string('two_factor_code')->nullable()->after('password');
            }

            if (!Schema::hasColumn('users', 'two_factor_expires_at')) {
                $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code');
            }

            if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('two_factor_expires_at');
            }

            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 10)->nullable()->default('fr')->after('remember_token');
            }

            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('updated_at');
            }

            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('avatar');
            }

            if (!Schema::hasColumn('users', 'profile_id')) {
                $table->uuid('profile_id')->nullable()->after('role');
            }

            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active')->after('profile_id');
            }

            if (!Schema::hasColumn('users', 'quota')) {
                $table->string('quota')->nullable()->default('5 Go')->after('status');
            }

            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('quota');
            }
        });

        if (!Schema::hasColumn('users', 'email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email')->unique()->change();
            });
        }

        if (!Schema::hasColumn('users', 'email') && !Schema::hasIndex('users', ['email'])) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'phone',
                'two_factor_code',
                'two_factor_expires_at',
                'two_factor_enabled',
                'locale',
                'avatar',
                'role',
                'profile_id',
                'status',
                'quota',
                'bio',
            ]);
        });
    }
};
