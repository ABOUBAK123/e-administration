import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, CreateDateColumn, Index } from 'typeorm';
import { Workflow } from './workflow.entity';
import { Document } from '../documents/document.entity';

@Entity('workflow_executions')
@Index(['workflowId'])
@Index(['documentId'])
@Index(['status'])
export class WorkflowExecution {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'uuid' })
  workflowId!: string;

  @Column({ type: 'uuid' })
  documentId!: string;

  @Column({ type: 'integer', default: 1 })
  currentStep!: number;

  @Column({ type: 'varchar', length: 50, default: 'in_progress' })
  status!: 'in_progress' | 'completed' | 'rejected' | 'paused' | 'cancelled';

  @Column({ type: 'json', nullable: true })
  stepData!: Record<string, any>;

  @ManyToOne(() => Workflow)
  workflow!: Workflow;

  @ManyToOne(() => Document, (doc) => doc.workflowExecutions, { onDelete: 'CASCADE' })
  document!: Document;

  @CreateDateColumn()
  startedAt!: Date;

  @Column({ type: 'timestamp', nullable: true })
  completedAt!: Date;
}
