import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  ManyToOne,
  CreateDateColumn,
  Index,
} from 'typeorm';
import { Document } from '../documents/document.entity';
import { User } from '../users/user.entity';

@Entity('signatures')
@Index(['documentId'])
@Index(['signerId'])
@Index(['status'])
export class Signature {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  documentId!: string;

  @Column({ type: 'uuid' })
  signerId!: string;

  @Column({ type: 'longblob' })
  signature!: Buffer;

  @Column({ type: 'text', nullable: true })
  certificate!: string;

  @Column({ type: 'timestamp' })
  timestamp!: Date;

  @Column({ type: 'varchar', length: 500, nullable: true })
  reason!: string;

  @Column({ type: 'varchar', length: 500, nullable: true })
  location!: string;

  @Column({ type: 'boolean', default: true })
  isValid!: boolean;

  @Column({ type: 'varchar', length: 50, default: 'valid' })
  status!: 'valid' | 'revoked' | 'expired';

  @Column({ type: 'varchar', length: 100, nullable: true })
  signatureAlgorithm: string;

  @ManyToOne(() => Document, (doc) => doc.signatures, { onDelete: 'CASCADE' })
  document: Document;

  @ManyToOne(() => User)
  signer: User;

  @CreateDateColumn()
  createdAt: Date;
}
