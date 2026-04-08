import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateRequestedActsTable1712200000000 implements MigrationInterface {
  name = 'CreateRequestedActsTable1712200000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query(`
        CREATE TABLE IF NOT EXISTS "requested_acts" (
          "id" uuid NOT NULL,
          "administrationScopeType" varchar(20) NOT NULL,
          "administrationScopeId" uuid NOT NULL,
          "administrationLabel" varchar(255) NOT NULL,
          "directionCode" varchar(120) NOT NULL,
          "directionLabel" varchar(255) NOT NULL,
          "documentName" varchar(500) NOT NULL,
          "requiredDocuments" json NOT NULL,
          "createdBy" uuid,
          "createdAt" TIMESTAMP NOT NULL DEFAULT now(),
          "updatedAt" TIMESTAMP NOT NULL DEFAULT now(),
          CONSTRAINT "PK_requested_acts_id" PRIMARY KEY ("id")
        )
      `);

      await queryRunner.query(`CREATE INDEX IF NOT EXISTS "IDX_requested_acts_scope" ON "requested_acts" ("administrationScopeType", "administrationScopeId")`);
      await queryRunner.query(`CREATE INDEX IF NOT EXISTS "IDX_requested_acts_directionCode" ON "requested_acts" ("directionCode")`);
      await queryRunner.query(`CREATE INDEX IF NOT EXISTS "IDX_requested_acts_createdAt" ON "requested_acts" ("createdAt")`);
      return;
    }

    // MySQL/MariaDB
    await queryRunner.query(`
      CREATE TABLE IF NOT EXISTS ` + '`requested_acts`' + ` (
        ` + '`id` CHAR(36) NOT NULL,' + `
        ` + '`administrationScopeType` VARCHAR(20) NOT NULL,' + `
        ` + '`administrationScopeId` CHAR(36) NOT NULL,' + `
        ` + '`administrationLabel` VARCHAR(255) NOT NULL,' + `
        ` + '`directionCode` VARCHAR(120) NOT NULL,' + `
        ` + '`directionLabel` VARCHAR(255) NOT NULL,' + `
        ` + '`documentName` VARCHAR(500) NOT NULL,' + `
        ` + '`requiredDocuments` JSON NOT NULL,' + `
        ` + '`createdBy` CHAR(36) DEFAULT NULL,' + `
        ` + '`createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,' + `
        ` + '`updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,' + `
        PRIMARY KEY (` + '`id`' + `),
        KEY ` + '`IDX_requested_acts_scope`' + ` (` + '`administrationScopeType`' + `, ` + '`administrationScopeId`' + `),
        KEY ` + '`IDX_requested_acts_directionCode`' + ` (` + '`directionCode`' + `),
        KEY ` + '`IDX_requested_acts_createdAt`' + ` (` + '`createdAt`' + `)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query(`DROP INDEX IF EXISTS "IDX_requested_acts_createdAt"`);
      await queryRunner.query(`DROP INDEX IF EXISTS "IDX_requested_acts_directionCode"`);
      await queryRunner.query(`DROP INDEX IF EXISTS "IDX_requested_acts_scope"`);
      await queryRunner.query(`DROP TABLE IF EXISTS "requested_acts"`);
      return;
    }

    await queryRunner.query(`DROP TABLE IF EXISTS ` + '`requested_acts`');
  }
}
