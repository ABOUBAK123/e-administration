import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, CreateDateColumn, Index } from 'typeorm';
import { Document } from '../documents/document.entity';
import { User } from '../users/user.entity';

@Entity('document_versions')
@Index(['documentId'])
@Index(['version'])
export class DocumentVersion {
  @PrimaryGeneratedColumn('uuid')
  id: string;

  @Column({ type: 'uuid' })
  documentId: string;

  @Column({ type: 'integer' })
  version: number;

  @Column({ type: 'varchar', length: 1000 })
  filePath: string;

  @Column({ type: 'uuid' })
  creatorId: string;

  @Column({ type: 'text', nullable: true })
  changeLog: string;

  @ManyToOne(() => Document, (doc) => doc.versions, { onDelete: 'CASCADE' })
  document: Document;

  @ManyToOne(() => User)
  creator: User;

  @CreateDateColumn()
  createdAt: Date;
}
