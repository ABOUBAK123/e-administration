<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meeting_rooms')) {
            return;
        }

        Schema::table('meeting_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('meeting_rooms', 'administration_id')) {
                $table->uuid('administration_id')->nullable()->after('id')->index();
            }
        });

        if (
            Schema::hasTable('issuing_administrations')
            && Schema::hasColumn('issuing_administrations', 'id')
            && Schema::hasColumn('meeting_rooms', 'administration_id')
            && $this->canCreateIssuingAdminForeignKey()
            && !$this->foreignKeyExists('meeting_rooms', 'meeting_rooms_administration_id_foreign')
        ) {
            try {
                DB::statement(
                    'ALTER TABLE `meeting_rooms` '
                    . 'ADD CONSTRAINT `meeting_rooms_administration_id_foreign` '
                    . 'FOREIGN KEY (`administration_id`) '
                    . 'REFERENCES `issuing_administrations`(`id`) '
                    . 'ON DELETE CASCADE'
                );
            } catch (\Throwable $e) {
                // Ne pas bloquer la migration si FK impossible (schema legacy/collation).
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('meeting_rooms')) {
            return;
        }

        if ($this->foreignKeyExists('meeting_rooms', 'meeting_rooms_administration_id_foreign')) {
            try {
                DB::statement('ALTER TABLE `meeting_rooms` DROP FOREIGN KEY `meeting_rooms_administration_id_foreign`');
            } catch (\Throwable $e) {
                // Ignore si la contrainte est deja absente ou non supprimable.
            }
        }

        Schema::table('meeting_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_rooms', 'administration_id')) {
                $table->dropColumn('administration_id');
            }
        });
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        $database = DB::getDatabaseName();

        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $tableName)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        return (bool) $exists;
    }

    private function canCreateIssuingAdminForeignKey(): bool
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        $database = DB::getDatabaseName();

        $roomTable = DB::table('information_schema.TABLES')
            ->select(['ENGINE', 'TABLE_COLLATION'])
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'meeting_rooms')
            ->first();

        $adminTable = DB::table('information_schema.TABLES')
            ->select(['ENGINE', 'TABLE_COLLATION'])
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'issuing_administrations')
            ->first();

        if (!$roomTable || !$adminTable) {
            return false;
        }

        if (strtoupper((string) $roomTable->ENGINE) !== 'INNODB' || strtoupper((string) $adminTable->ENGINE) !== 'INNODB') {
            return false;
        }

        $roomColumn = DB::table('information_schema.COLUMNS')
            ->select(['COLUMN_TYPE', 'CHARACTER_SET_NAME', 'COLLATION_NAME'])
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'meeting_rooms')
            ->where('COLUMN_NAME', 'administration_id')
            ->first();

        $adminColumn = DB::table('information_schema.COLUMNS')
            ->select(['COLUMN_TYPE', 'CHARACTER_SET_NAME', 'COLLATION_NAME'])
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'issuing_administrations')
            ->where('COLUMN_NAME', 'id')
            ->first();

        if (!$roomColumn || !$adminColumn) {
            return false;
        }

        return strtolower((string) $roomColumn->COLUMN_TYPE) === strtolower((string) $adminColumn->COLUMN_TYPE)
            && strtolower((string) ($roomColumn->CHARACTER_SET_NAME ?? '')) === strtolower((string) ($adminColumn->CHARACTER_SET_NAME ?? ''))
            && strtolower((string) ($roomColumn->COLLATION_NAME ?? '')) === strtolower((string) ($adminColumn->COLLATION_NAME ?? ''));
    }
};
