import { MigrationInterface, QueryRunner } from 'typeorm';

export class AddApplicantFieldsToRequestedActs1712400000000 implements MigrationInterface {
  name = 'AddApplicantFieldsToRequestedActs1712400000000';

  public async up(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query(`ALTER TABLE "requested_acts" ADD COLUMN IF NOT EXISTS "applicantFields" json NULL`);
      return;
    }

    await queryRunner.query(`ALTER TABLE ` + '`requested_acts`' + ` ADD COLUMN ` + '`applicantFields`' + ` JSON NULL`);
  }

  public async down(queryRunner: QueryRunner): Promise<void> {
    const dbType = queryRunner.connection.options.type;

    if (dbType === 'postgres') {
      await queryRunner.query(`ALTER TABLE "requested_acts" DROP COLUMN IF EXISTS "applicantFields"`);
      return;
    }

    await queryRunner.query(`ALTER TABLE ` + '`requested_acts`' + ` DROP COLUMN ` + '`applicantFields`');
  }
}
