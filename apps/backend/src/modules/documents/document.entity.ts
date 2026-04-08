import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, OneToMany, CreateDateColumn, UpdateDateColumn, DeleteDateColumn, Index } from 'typeorm';
import { User } from '../users/user.entity';
import { Signature } from '../signatures/signature.entity';
import { QrCode } from '../qrcode/qrcode.entity';
import { WorkflowExecution } from '../workflows/workflow-execution.entity';
import { DocumentVersion } from './document-version.entity';

@Entity('documents')
@Index(['ownerId'])
@Index(['status'])
@Index(['createdAt'])
@Index(['recipientAdministrationId'])
@Index(['issuingAdministrationId', 'documentNumber'], { unique: true })
export class Document {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'varchar', length: 500 })
  title!: string;

  @Column({ type: 'text', nullable: true })
  description!: string;

  @Column({ type: 'varchar', length: 1000 })
  filePath!: string;

  @Column({ type: 'bigint' })
  fileSize!: number;

  @Column({ type: 'varchar', length: 100 })
  mimeType!: string;

  @Column({ type: 'varchar', length: 50, default: 'draft' })
  status!: 'draft' | 'active' | 'signed' | 'archived' | 'pending_signature';

  @Column({ type: 'uuid' })
  createdBy!: string;

  @Column({ type: 'uuid' })
  ownerId!: string;

  @Column({ type: 'uuid', nullable: true })
  issuingAdministrationId!: string | null;

  @Column({ type: 'uuid', nullable: true })
  recipientAdministrationId!: string | null;

  @Column({ type: 'varchar', length: 120, nullable: true })
  documentNumber!: string | null;

  @Column({ type: 'varchar', length: 100, nullable: true })
  subEntityCode!: string | null;

  @Column({ type: 'timestamp', nullable: true })
  signedAt!: Date | null;

  @ManyToOne(() => User, { eager: true })
  owner!: User;

  @OneToMany(() => DocumentVersion, (version) => version.document, { cascade: true })
  versions!: DocumentVersion[];

  @OneToMany(() => Signature, (signature) => signature.document, { cascade: true })
  signatures!: Signature[];

  @OneToMany(() => QrCode, (qrcode) => qrcode.document, { cascade: true })
  qrcodes!: QrCode[];

  @OneToMany(() => WorkflowExecution, (execution) => execution.document, { cascade: true })
  workflowExecutions!: WorkflowExecution[];

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;

  @DeleteDateColumn({ nullable: true })
  deletedAt!: Date;
}
