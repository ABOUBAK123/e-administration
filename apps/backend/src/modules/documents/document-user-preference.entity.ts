import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  ManyToOne,
  CreateDateColumn,
  UpdateDateColumn,
  Unique,
  Index,
} from 'typeorm';
import { Document } from './document.entity';

@Entity('document_user_preferences')
@Unique('UQ_document_user_preferences_user_document', ['userId', 'documentId'])
@Index('IDX_document_user_preferences_userId', ['userId'])
@Index('IDX_document_user_preferences_documentId', ['documentId'])
export class DocumentUserPreference {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  userId!: string;

  @Column({ type: 'uuid' })
  documentId!: string;

  @Column({ type: 'boolean', default: false })
  isFavorite!: boolean;

  @Column({ type: 'simple-json', nullable: true })
  labelCodes!: string[] | null;

  @ManyToOne(() => Document, { onDelete: 'CASCADE' })
  document!: Document;

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
