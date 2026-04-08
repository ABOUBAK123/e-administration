import { Entity, PrimaryGeneratedColumn, Column, CreateDateColumn, Index } from 'typeorm';

@Entity('chat_messages')
@Index(['room'])
@Index(['createdAt'])
export class ChatMessageEntity {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'varchar', length: 128 })
  senderId!: string;

  @Column({ type: 'varchar', length: 255 })
  senderName!: string;

  @Column({ type: 'varchar', length: 8 })
  senderInitials!: string;

  @Column({ type: 'text' })
  text!: string;

  @Column({ type: 'varchar', length: 255 })
  room!: string;

  @CreateDateColumn()
  createdAt!: Date;
}
