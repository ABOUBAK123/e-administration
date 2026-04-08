import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, CreateDateColumn, Index } from 'typeorm';
import { Document } from '../documents/document.entity';
import { User } from '../users/user.entity';

@Entity('signature_requests')
@Index(['documentId'])
@Index(['status'])
@Index(['expiryDate'])
export class SignatureRequest {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'uuid' })
  documentId: string;

  @Column({ type: 'uuid' })
  requestedBy: string;

  @Column({ type: 'uuid' })
  requestedTo: string;

  @Column({ type: 'text', nullable: true })
  message: string;

  @Column({ type: 'varchar', length: 50, default: 'pending' })
  status: 'pending' | 'signed' | 'declined' | 'expired';

  @Column({ type: 'timestamp' })
  expiryDate: Date;

  @ManyToOne(() => Document, { onDelete: 'CASCADE' })
  document: Document;

  @ManyToOne(() => User)
  requester: User;

  @ManyToOne(() => User)
  recipient: User;

  @CreateDateColumn()
  createdAt: Date;

  @Column({ type: 'timestamp', nullable: true })
  respondedAt: Date;
}
