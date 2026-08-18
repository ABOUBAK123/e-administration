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
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $tableNames = config('permission.table_names', [
            'permissions' => 'permissions',
            'roles' => 'roles',
        ]);

        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';

        if (Schema::hasTable($permissionsTable)) {
            DB::statement("ALTER TABLE `{$permissionsTable}` ENGINE=InnoDB");
            DB::statement("ALTER TABLE `{$permissionsTable}` MODIFY COLUMN `name` VARCHAR(125) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
            DB::statement("ALTER TABLE `{$permissionsTable}` MODIFY COLUMN `guard_name` VARCHAR(125) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");

            try {
                DB::statement("ALTER TABLE `{$permissionsTable}` DROP INDEX permissions_name_guard_name_unique");
            } catch (\Throwable $e) {
                // The index may not exist or may use a different generated name.
            }

            DB::statement("ALTER TABLE `{$permissionsTable}` ADD UNIQUE `permissions_name_guard_name_unique` (`name`, `guard_name`)");
        }

        if (Schema::hasTable($rolesTable)) {
            DB::statement("ALTER TABLE `{$rolesTable}` ENGINE=InnoDB");
            DB::statement("ALTER TABLE `{$rolesTable}` MODIFY COLUMN `name` VARCHAR(125) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
            DB::statement("ALTER TABLE `{$rolesTable}` MODIFY COLUMN `guard_name` VARCHAR(125) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");

            try {
                DB::statement("ALTER TABLE `{$rolesTable}` DROP INDEX roles_name_guard_name_unique");
            } catch (\Throwable $e) {
                // The index may not exist or may use a different generated name.
            }

            DB::statement("ALTER TABLE `{$rolesTable}` ADD UNIQUE `roles_name_guard_name_unique` (`name`, `guard_name`)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $tableNames = config('permission.table_names', [
            'permissions' => 'permissions',
            'roles' => 'roles',
        ]);

        $permissionsTable = $tableNames['permissions'] ?? 'permissions';
        $rolesTable = $tableNames['roles'] ?? 'roles';

        if (Schema::hasTable($permissionsTable)) {
            try {
                DB::statement("ALTER TABLE `{$permissionsTable}` DROP INDEX permissions_name_guard_name_unique");
            } catch (\Throwable $e) {
                // Nothing to do.
            }
        }

        if (Schema::hasTable($rolesTable)) {
            try {
                DB::statement("ALTER TABLE `{$rolesTable}` DROP INDEX roles_name_guard_name_unique");
            } catch (\Throwable $e) {
                // Nothing to do.
            }
        }
    }
};
