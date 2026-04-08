import { Entity, PrimaryGeneratedColumn, Column, UpdateDateColumn, Index } from 'typeorm';

@Entity('app_settings')
@Index(['key'], { unique: true })
export class AppSetting {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'varchar', length: 100, unique: true })
  key!: string;

  @Column({ type: 'text', nullable: true })
  value!: string | null;

  @Column({ type: 'varchar', length: 255, nullable: true })
  description!: string | null;

  @UpdateDateColumn()
  updatedAt!: Date;
}
