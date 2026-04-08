import { Entity, PrimaryGeneratedColumn, Column, CreateDateColumn, Index } from 'typeorm';

@Entity('notifications')
@Index(['recipientId'])
@Index(['isRead'])
export class Notification {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  recipientId!: string;

  @Column({ type: 'varchar', length: 255 })
  title!: string;

  @Column({ type: 'text' })
  message!: string;

  @Column({ type: 'varchar', length: 50, default: 'info' })
  type!: 'info' | 'validation' | 'signature' | 'workflow' | 'system';

  @Column({ type: 'uuid', nullable: true })
  workflowId!: string | null;

  @Column({ type: 'uuid', nullable: true })
  executionId!: string | null;

  @Column({ type: 'varchar', length: 512, nullable: true })
  actionUrl!: string | null;

  @Column({ type: 'boolean', default: false })
  isRead!: boolean;

  @CreateDateColumn()
  createdAt!: Date;
}
