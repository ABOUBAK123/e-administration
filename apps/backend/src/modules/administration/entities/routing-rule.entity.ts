import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, CreateDateColumn, UpdateDateColumn, Index } from 'typeorm';
import { RecipientAdministration } from './recipient-administration.entity';
import { DocumentTemplate } from './template.entity';

@Entity('routing_rules')
@Index(['documentType'])
@Index(['recipientAdministrationId'])
@Index(['priority'])
export class RoutingRule {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'varchar', length: 255 })
  name!: string;

  @Column({ type: 'varchar', length: 100 })
  documentType!: string;

  @Column({ type: 'uuid', nullable: true })
  templateId!: string;

  @Column({ type: 'uuid' })
  recipientAdministrationId!: string;

  @Column({ type: 'json', nullable: true })
  conditions!: Record<string, any>;

  @Column({ type: 'int', default: 1 })
  priority!: number;

  @Column({ type: 'boolean', default: true })
  isActive!: boolean;

  @ManyToOne(() => RecipientAdministration, (recipient) => recipient.routingRules, { onDelete: 'CASCADE' })
  recipientAdministration!: RecipientAdministration;

  @ManyToOne(() => DocumentTemplate, (template) => template.routingRules, { nullable: true, onDelete: 'SET NULL' })
  template!: DocumentTemplate;

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
