import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  ManyToOne,
  OneToMany,
  Index,
} from 'typeorm';
import { IssuingAdministration } from './issuing-administration.entity';
import { TemplateVariable } from './template-variable.entity';
import { RoutingRule } from './routing-rule.entity';

@Entity('document_templates')
@Index(['name'])
@Index(['fileType'])
@Index(['administrationId'])
export class DocumentTemplate {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'varchar', length: 255 })
  name!: string;

  @Column({ type: 'varchar', length: 255 })
  fileName!: string;

  @Column({ type: 'varchar', length: 20 })
  fileType!: 'docx' | 'xlsx' | 'pptx' | 'pdf';

  @Column({ type: 'varchar', length: 1000, nullable: true })
  storagePath!: string;

  @Column({ type: 'text', nullable: true })
  content!: string;

  @Column({ type: 'uuid', nullable: true })
  administrationId!: string;

  @Column({ type: 'uuid', nullable: true })
  createdBy!: string;

  @ManyToOne(() => IssuingAdministration, (administration) => administration.templates, {
    nullable: true,
    onDelete: 'SET NULL',
  })
  administration!: IssuingAdministration;

  @OneToMany(() => TemplateVariable, (variable) => variable.template, { cascade: true })
  variables!: TemplateVariable[];

  @OneToMany(() => RoutingRule, (rule) => rule.template)
  routingRules!: RoutingRule[];

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
