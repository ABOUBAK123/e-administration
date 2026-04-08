import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddSubEntityCodeToUserDirectionAssignments1712600000000 implements MigrationInterface {
  name = 'AddSubEntityCodeToUserDirectionAssignments1712600000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query(
        'ALTER TABLE "user_direction_assignments" ADD COLUMN IF NOT EXISTS "subEntityCode" varchar(120)',
      );
      await queryRunner.query(
        'CREATE INDEX IF NOT EXISTS "IDX_user_direction_assignments_subEntityCode" ON "user_direction_assignments" ("subEntityCode")',
      );
      return;
    }

    const columnExistsResult = await queryRunner.query(`
      SELECT COUNT(*) as count
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_direction_assignments'
        AND COLUMN_NAME = 'subEntityCode'
    `);
    const hasColumn = Number(columnExistsResult?.[0]?.count || 0) > 0;

    if (!hasColumn) {
      await queryRunner.query(
        'ALTER TABLE `user_direction_assignments` ADD COLUMN `subEntityCode` VARCHAR(120) NULL AFTER `directionScopeId`',
      );
    }

    const indexExistsResult = await queryRunner.query(`
      SELECT COUNT(*) as count
      FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_direction_assignments'
        AND INDEX_NAME = 'IDX_user_direction_assignments_subEntityCode'
    `);
    const hasIndex = Number(indexExistsResult?.[0]?.count || 0) > 0;
    if (!hasIndex) {
      await queryRunner.query(
        'CREATE INDEX `IDX_user_direction_assignments_subEntityCode` ON `user_direction_assignments` (`subEntityCode`)',
      );
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query('DROP INDEX IF EXISTS "IDX_user_direction_assignments_subEntityCode"');
      await queryRunner.query('ALTER TABLE "user_direction_assignments" DROP COLUMN IF EXISTS "subEntityCode"');
      return;
    }

    const indexExistsResult = await queryRunner.query(`
      SELECT COUNT(*) as count
      FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_direction_assignments'
        AND INDEX_NAME = 'IDX_user_direction_assignments_subEntityCode'
    `);
    const hasIndex = Number(indexExistsResult?.[0]?.count || 0) > 0;
    if (hasIndex) {
      await queryRunner.query('ALTER TABLE `user_direction_assignments` DROP INDEX `IDX_user_direction_assignments_subEntityCode`');
    }

    const columnExistsResult = await queryRunner.query(`
      SELECT COUNT(*) as count
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_direction_assignments'
        AND COLUMN_NAME = 'subEntityCode'
    `);
    const hasColumn = Number(columnExistsResult?.[0]?.count || 0) > 0;
    if (hasColumn) {
      await queryRunner.query('ALTER TABLE `user_direction_assignments` DROP COLUMN `subEntityCode`');
    }
  }
}
