import { Column, CreateDateColumn, Entity, Index, PrimaryGeneratedColumn, UpdateDateColumn, ManyToOne } from 'typeorm';
import { IssuingAdministration } from './issuing-administration.entity';

@Entity('signature_provider_configs')
@Index(['isActive'])
@Index(['administrationId'])
export class SignatureProviderConfig {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid', nullable: true })
  administrationId!: string | null;

  @Column({ type: 'boolean', default: false })
  isActive!: boolean;

  @Column({ type: 'varchar', length: 500, nullable: true })
  endpoint!: string | null;

  @Column({ type: 'varchar', length: 500, nullable: true })
  signPath!: string | null;

  @Column({ type: 'varchar', length: 500, nullable: true })
  apiKey!: string | null;

  @Column({ type: 'varchar', length: 255, nullable: true })
  consentPageId!: string | null;

  @Column({ type: 'varchar', length: 255, nullable: true })
  signatureProfileId!: string | null;

  @Column({ type: 'varchar', length: 255, nullable: true })
  providerOwnerUserId!: string | null;

  @Column({ type: 'boolean', default: true })
  verifySsl!: boolean;

  @Column({ type: 'int', default: 30000 })
  timeoutMs!: number;

  @Column({ type: 'json', nullable: true })
  metadata!: Record<string, any> | null;

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;

  @ManyToOne(() => IssuingAdministration, { nullable: true, onDelete: 'CASCADE' })
  administration?: IssuingAdministration;
}
