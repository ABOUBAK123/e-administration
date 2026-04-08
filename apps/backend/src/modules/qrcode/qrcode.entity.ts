import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, CreateDateColumn, Index } from 'typeorm';
import { Document } from '../documents/document.entity';
import { User } from '../users/user.entity';

@Entity('qr_codes')
@Index(['documentId'])
@Index(['status'])
@Index(['verificationCode'])
export class QrCode {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'uuid' })
  documentId: string;

  @Column({ type: 'text' })
  data: string;

  @Column({ type: 'json', nullable: true })
  metadata: Record<string, any>;

  @Column({ type: 'varchar', length: 255, unique: true })
  verificationCode: string;

  @Column({ type: 'varchar', length: 50, default: 'active' })
  status: 'active' | 'revoked' | 'expired';

  @Column({ type: 'integer', default: 0 })
  scanCount: number;

  @Column({ type: 'uuid' })
  createdBy: string;

  @ManyToOne(() => Document, (doc) => doc.qrcodes, { onDelete: 'CASCADE' })
  document: Document;

  @ManyToOne(() => User)
  creator: User;

  @CreateDateColumn()
  createdAt: Date;

  @Column({ type: 'timestamp' })
  expiresAt: Date;
}
