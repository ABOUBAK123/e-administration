import {
  Column,
  CreateDateColumn,
  Entity,
  Index,
  JoinColumn,
  ManyToOne,
  PrimaryGeneratedColumn,
  UpdateDateColumn,
} from 'typeorm';
import { IssuingAdministration } from './issuing-administration.entity';

@Entity('notification_configs')
@Index(['administrationId'], { unique: true })
export class NotificationConfig {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  administrationId!: string;

  @Column({ type: 'boolean', default: true })
  isActive!: boolean;

  @Column({ type: 'varchar', length: 255, nullable: true })
  smtpHost!: string | null;

  @Column({ type: 'int', default: 587 })
  smtpPort!: number;

  @Column({ type: 'boolean', default: false })
  smtpSecure!: boolean;

  @Column({ type: 'varchar', length: 255, nullable: true })
  smtpUser!: string | null;

  @Column({ type: 'varchar', length: 500, nullable: true })
  smtpPassword!: string | null;

  @Column({ type: 'varchar', length: 255, nullable: true })
  smtpFrom!: string | null;

  @Column({ type: 'json', nullable: true })
  triggers!: Record<string, boolean> | null;

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;

  @ManyToOne(() => IssuingAdministration, { nullable: false, onDelete: 'CASCADE' })
  @JoinColumn({ name: 'administrationId' })
  administration!: IssuingAdministration;
}
