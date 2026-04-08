import { Column, CreateDateColumn, Entity, Index, PrimaryGeneratedColumn, UpdateDateColumn } from 'typeorm';

@Entity('requested_acts')
@Index(['administrationScopeType', 'administrationScopeId'])
@Index(['directionCode'])
@Index(['createdAt'])
export class RequestedAct {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'varchar', length: 20 })
  administrationScopeType!: 'emitter' | 'recipient';

  @Column({ type: 'uuid' })
  administrationScopeId!: string;

  @Column({ type: 'varchar', length: 255 })
  administrationLabel!: string;

  @Column({ type: 'varchar', length: 120 })
  directionCode!: string;

  @Column({ type: 'varchar', length: 255 })
  directionLabel!: string;

  @Column({ type: 'varchar', length: 500 })
  documentName!: string;

  @Column({ type: 'json' })
  requiredDocuments!: string[];

  @Column({ type: 'json', nullable: true })
  applicantFields!: Array<{
    label: string;
    inputType: 'text' | 'date' | 'number' | 'phone' | 'email' | 'textarea';
  }> | null;

  @Column({ type: 'uuid', nullable: true })
  createdBy!: string | null;

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
