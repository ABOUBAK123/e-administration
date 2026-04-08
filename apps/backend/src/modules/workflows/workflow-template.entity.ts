import { Column, CreateDateColumn, Entity, Index, JoinColumn, ManyToOne, PrimaryGeneratedColumn, UpdateDateColumn } from 'typeorm';
import { User } from '../users/user.entity';

type WorkflowTemplateAssignee = {
  id: number;
  approverId?: string;
  signerId?: string;
};

@Entity('workflow_templates')
@Index(['administrationId'])
@Index(['createdBy'])
@Index(['status'])
export class WorkflowTemplate {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  administrationId!: string;

  @Column({ type: 'varchar', length: 500 })
  name!: string;

  @Column({ type: 'text', nullable: true })
  description!: string | null;

  @Column({ type: 'json', nullable: true, default: () => "'[]'" })
  validationSteps!: WorkflowTemplateAssignee[];

  @Column({ type: 'json', nullable: true, default: () => "'[]'" })
  signatureSteps!: WorkflowTemplateAssignee[];

  @Column({ type: 'json', nullable: true })
  notificationConfig!: {
    notifyEmail: boolean;
    emails: string;
    cc: string;
    stages: {
      onValidationStep: boolean;
      onSignatureStep: boolean;
      onApproved: boolean;
      onRejected: boolean;
      onCompleted: boolean;
    };
    sendDownloadLink: boolean;
  } | null;

  @Column({ type: 'varchar', length: 50, default: 'active' })
  status!: 'active' | 'archived';

  @Column({ type: 'uuid' })
  createdBy!: string;

  @ManyToOne(() => User, { nullable: true })
  @JoinColumn({ name: 'createdBy' })
  creator!: User;

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}