import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateDocumentUserPreferencesTable1712700000000 implements MigrationInterface {
  name = 'CreateDocumentUserPreferencesTable1712700000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query('CREATE EXTENSION IF NOT EXISTS "pgcrypto"');
      await queryRunner.query(`
        CREATE TABLE IF NOT EXISTS "document_user_preferences" (
          "id" uuid NOT NULL DEFAULT gen_random_uuid(),
          "userId" uuid NOT NULL,
          "documentId" uuid NOT NULL,
          "isFavorite" boolean NOT NULL DEFAULT false,
          "labelCodes" text,
          "createdAt" TIMESTAMP NOT NULL DEFAULT now(),
          "updatedAt" TIMESTAMP NOT NULL DEFAULT now(),
          CONSTRAINT "PK_document_user_preferences_id" PRIMARY KEY ("id"),
          CONSTRAINT "UQ_document_user_preferences_user_document" UNIQUE ("userId", "documentId"),
          CONSTRAINT "FK_document_user_preferences_documentId" FOREIGN KEY ("documentId") REFERENCES "documents"("id") ON DELETE CASCADE
        )
      `);
      await queryRunner.query(
        'CREATE INDEX IF NOT EXISTS "IDX_document_user_preferences_userId" ON "document_user_preferences" ("userId")'
      );
      await queryRunner.query(
        'CREATE INDEX IF NOT EXISTS "IDX_document_user_preferences_documentId" ON "document_user_preferences" ("documentId")'
      );
      return;
    }

    const tableExists = await queryRunner.query(`
      SELECT COUNT(*) as count
      FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'document_user_preferences'
    `);

    if (Number(tableExists?.[0]?.count || 0) === 0) {
      await queryRunner.query(`
        CREATE TABLE \
          \`document_user_preferences\` (
            \`id\` CHAR(36) NOT NULL,
            \`userId\` CHAR(36) NOT NULL,
            \`documentId\` CHAR(36) NOT NULL,
            \`isFavorite\` TINYINT(1) NOT NULL DEFAULT 0,
            \`labelCodes\` TEXT NULL,
            \`createdAt\` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
            \`updatedAt\` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
            PRIMARY KEY (\`id\`),
            UNIQUE KEY \`UQ_document_user_preferences_user_document\` (\`userId\`, \`documentId\`),
            KEY \`IDX_document_user_preferences_userId\` (\`userId\`),
            KEY \`IDX_document_user_preferences_documentId\` (\`documentId\`),
            CONSTRAINT \`FK_document_user_preferences_documentId\` FOREIGN KEY (\`documentId\`) REFERENCES \`documents\`(\`id\`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      `);
    }
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query('DROP TABLE IF EXISTS "document_user_preferences"');
      return;
    }

    const tableExists = await queryRunner.query(`
      SELECT COUNT(*) as count
      FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'document_user_preferences'
    `);

    if (Number(tableExists?.[0]?.count || 0) > 0) {
      await queryRunner.query('DROP TABLE `document_user_preferences`');
    }
  }
}
