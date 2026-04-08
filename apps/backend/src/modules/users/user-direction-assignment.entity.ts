import { Column, CreateDateColumn, Entity, Index, PrimaryGeneratedColumn, UpdateDateColumn } from 'typeorm';

@Entity('user_direction_assignments')
@Index(['userId'], { unique: true })
@Index(['directionScopeType'])
@Index(['directionScopeId'])
@Index(['subEntityCode'])
export class UserDirectionAssignment {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  userId!: string;

  @Column({ type: 'varchar', length: 20, nullable: true })
  directionScopeType!: 'emitter' | 'recipient' | null;

  @Column({ type: 'varchar', length: 120, nullable: true })
  directionScopeId!: string | null;

  @Column({ type: 'varchar', length: 120, nullable: true })
  subEntityCode!: string | null;

  @Column({ type: 'varchar', length: 255 })
  directionLabel!: string;

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
