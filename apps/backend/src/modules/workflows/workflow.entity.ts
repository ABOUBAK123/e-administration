import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  ManyToOne,
  OneToMany,
  CreateDateColumn,
  UpdateDateColumn,
  Index,
  JoinColumn,
} from 'typeorm';
import { User } from '../users/user.entity';
import { WorkflowStep } from './workflow-step.entity';
import { WorkflowExecution } from './workflow-execution.entity';

@Entity('workflows')
@Index(['createdBy'])
@Index(['status'])
export class Workflow {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'varchar', length: 500 })
  name: string;

  @Column({ type: 'text', nullable: true })
  description: string;

  @Column({ type: 'varchar', length: 50, default: 'active' })
  status: 'active' | 'inactive' | 'archived';

  @Column({ type: 'json', nullable: true, default: () => "'[]'" })
  docsToSign?: string[];

  @Column({ type: 'json', nullable: true, default: () => "'[]'" })
  attachedDocs?: string[];

  @Column({ type: 'json', nullable: true, default: () => "'[]'" })
  uploadedSignatureFiles?: Array<{
    fileName: string;
    fileSize: number;
    fileType: string;
    zones: Array<{ x: number; y: number; width: number; height: number }>;
  }>;

  @Column({ type: 'uuid' })
  createdBy: string;

  @ManyToOne(() => User)
  @JoinColumn({ name: 'createdBy' })
  creator: User;

  @OneToMany(() => WorkflowStep, (step) => step.workflow, { cascade: true })
  steps: WorkflowStep[];

  @OneToMany(() => WorkflowExecution, (execution) => execution.workflow)
  executions!: WorkflowExecution[];

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
