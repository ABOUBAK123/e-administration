import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, CreateDateColumn, UpdateDateColumn, Index } from 'typeorm';
import { DocumentTemplate } from './template.entity';

@Entity('template_variables')
@Index(['templateId'])
@Index(['key'])
export class TemplateVariable {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  templateId!: string;

  @Column({ type: 'varchar', length: 150 })
  key!: string;

  @Column({ type: 'varchar', length: 255 })
  label!: string;

  @Column({ type: 'varchar', length: 50, default: 'text' })
  fieldType!: 'text' | 'date' | 'number' | 'select' | 'textarea';

  @Column({ type: 'boolean', default: false })
  required!: boolean;

  @Column({ type: 'varchar', length: 500, nullable: true })
  placeholder!: string;

  @Column({ type: 'varchar', length: 500, nullable: true })
  defaultValue!: string;

  @Column({ type: 'json', nullable: true })
  options!: string[];

  @ManyToOne(() => DocumentTemplate, (template) => template.variables, { onDelete: 'CASCADE' })
  template!: DocumentTemplate;

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
