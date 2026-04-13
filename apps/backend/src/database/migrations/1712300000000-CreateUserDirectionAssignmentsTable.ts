import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateUserDirectionAssignmentsTable1712300000000 implements MigrationInterface {
  name = 'CreateUserDirectionAssignmentsTable1712300000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query(`
        CREATE TABLE IF NOT EXISTS "user_direction_assignments" (
          "id" uuid NOT NULL,
          "userId" uuid NOT NULL,
          "directionScopeType" varchar(20),
          "directionScopeId" varchar(120),
          "directionLabel" varchar(255) NOT NULL,
          "createdAt" TIMESTAMP NOT NULL DEFAULT now(),
          "updatedAt" TIMESTAMP NOT NULL DEFAULT now(),
          CONSTRAINT "PK_user_direction_assignments_id" PRIMARY KEY ("id"),
          CONSTRAINT "UQ_user_direction_assignments_userId" UNIQUE ("userId"),
          CONSTRAINT "FK_user_direction_assignments_user" FOREIGN KEY ("userId") REFERENCES "users"("id") ON DELETE CASCADE
        )
      `);

      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS "IDX_user_direction_assignments_scopeType" ON "user_direction_assignments" ("directionScopeType")`
      );
      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS "IDX_user_direction_assignments_scopeId" ON "user_direction_assignments" ("directionScopeId")`
      );
      return;
    }

    await queryRunner.query(
      `
      CREATE TABLE IF NOT EXISTS ` +
        '`user_direction_assignments`' +
        ` (
        ` +
        '`id` CHAR(36) NOT NULL,' +
        `
        ` +
        '`userId` CHAR(36) NOT NULL,' +
        `
        ` +
        '`directionScopeType` VARCHAR(20) DEFAULT NULL,' +
        `
        ` +
        '`directionScopeId` VARCHAR(120) DEFAULT NULL,' +
        `
        ` +
        '`directionLabel` VARCHAR(255) NOT NULL,' +
        `
        ` +
        '`createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,' +
        `
        ` +
        '`updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,' +
        `
        PRIMARY KEY (` +
        '`id`' +
        `),
        UNIQUE KEY ` +
        '`UQ_user_direction_assignments_userId`' +
        ` (` +
        '`userId`' +
        `),
        KEY ` +
        '`IDX_user_direction_assignments_scopeType`' +
        ` (` +
        '`directionScopeType`' +
        `),
        KEY ` +
        '`IDX_user_direction_assignments_scopeId`' +
        ` (` +
        '`directionScopeId`' +
        `),
        CONSTRAINT ` +
        '`FK_user_direction_assignments_user`' +
        ` FOREIGN KEY (` +
        '`userId`' +
        `)
          REFERENCES ` +
        '`users`' +
        `(` +
        '`id`' +
        `)
          ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query(`DROP INDEX IF EXISTS "IDX_user_direction_assignments_scopeId"`);
      await queryRunner.query(`DROP INDEX IF EXISTS "IDX_user_direction_assignments_scopeType"`);
      await queryRunner.query(`DROP TABLE IF EXISTS "user_direction_assignments"`);
      return;
    }

    await queryRunner.query(`DROP TABLE IF EXISTS ` + '`user_direction_assignments`');
  }
}
