import { MigrationInterface, QueryRunner } from 'typeorm';

export class CreateChatMessagesTable1712500000000 implements MigrationInterface {
  name = 'CreateChatMessagesTable1712500000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query(`
        CREATE TABLE IF NOT EXISTS "chat_messages" (
          "id" uuid NOT NULL,
          "senderId" varchar(128) NOT NULL,
          "senderName" varchar(255) NOT NULL,
          "senderInitials" varchar(8) NOT NULL,
          "text" text NOT NULL,
          "room" varchar(255) NOT NULL,
          "createdAt" TIMESTAMP NOT NULL DEFAULT now(),
          CONSTRAINT "PK_chat_messages_id" PRIMARY KEY ("id")
        )
      `);

      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS "IDX_chat_messages_room" ON "chat_messages" ("room")`
      );
      await queryRunner.query(
        `CREATE INDEX IF NOT EXISTS "IDX_chat_messages_createdAt" ON "chat_messages" ("createdAt")`
      );
      return;
    }

    // MySQL/MariaDB
    await queryRunner.query(
      `
      CREATE TABLE IF NOT EXISTS ` +
        '`chat_messages`' +
        ` (
        ` +
        '`id` CHAR(36) NOT NULL,' +
        `
        ` +
        '`senderId` VARCHAR(128) NOT NULL,' +
        `
        ` +
        '`senderName` VARCHAR(255) NOT NULL,' +
        `
        ` +
        '`senderInitials` VARCHAR(8) NOT NULL,' +
        `
        ` +
        '`text` TEXT NOT NULL,' +
        `
        ` +
        '`room` VARCHAR(255) NOT NULL,' +
        `
        ` +
        '`createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,' +
        `
        PRIMARY KEY (` +
        '`id`' +
        `),
        KEY ` +
        '`IDX_chat_messages_room`' +
        ` (` +
        '`room`' +
        `),
        KEY ` +
        '`IDX_chat_messages_createdAt`' +
        ` (` +
        '`createdAt`' +
        `)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    `
    );
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query(`DROP INDEX IF EXISTS "IDX_chat_messages_createdAt"`);
      await queryRunner.query(`DROP INDEX IF EXISTS "IDX_chat_messages_room"`);
      await queryRunner.query(`DROP TABLE IF EXISTS "chat_messages"`);
      return;
    }

    await queryRunner.query(`DROP TABLE IF EXISTS ` + '`chat_messages`');
  }
}
