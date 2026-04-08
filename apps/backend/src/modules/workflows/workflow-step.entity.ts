import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, CreateDateColumn, Index, Unique } from 'typeorm';
import { Workflow } from './workflow.entity';
import { User } from '../users/user.entity';

@Entity('workflow_steps')
@Index(['workflowId'])
@Index(['order'])
@Unique(['workflowId', 'order'])
export class WorkflowStep {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  workflowId!: string;

  @Column({ type: 'integer' })
  order!: number;

  @Column({ type: 'varchar', length: 100 })
  type!: 'review' | 'sign' | 'approve' | 'reject' | 'notify';

  @Column({ type: 'uuid', nullable: true })
  assigneeId!: string;

  @Column({ type: 'text', nullable: true })
  description!: string;

  @Column({ type: 'boolean', default: false })
  requiresSignature!: boolean;

  @ManyToOne(() => Workflow, (workflow) => workflow.steps, { onDelete: 'CASCADE' })
  workflow!: Workflow;

  @ManyToOne(() => User, { nullable: true })
  assignee!: User;

  @CreateDateColumn()
  createdAt!: Date;
}
