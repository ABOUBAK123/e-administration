import { Entity, PrimaryGeneratedColumn, Column, ManyToOne, CreateDateColumn, UpdateDateColumn, Index } from 'typeorm';
import { IssuingAdministration } from './issuing-administration.entity';
import { AdministrationProfile } from './administration-profile.entity';

@Entity('administration_users')
@Index(['administrationId'])
@Index(['email'])
@Index(['username'])
export class AdministrationUser {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  administrationId!: string;

  @Column({ type: 'uuid', nullable: true })
  profileId!: string;

  @Column({ type: 'varchar', length: 255 })
  fullName!: string;

  @Column({ type: 'varchar', length: 255, unique: true })
  email!: string;

  @Column({ type: 'varchar', length: 150, unique: true })
  username!: string;

  @Column({ type: 'varchar', length: 50, default: 'user' })
  adminRole!: 'super_admin' | 'admin' | 'manager' | 'user' | 'signer';

  @Column({ type: 'varchar', length: 50, default: 'active' })
  status!: 'active' | 'inactive';

  @ManyToOne(() => IssuingAdministration, (administration) => administration.users, { onDelete: 'CASCADE' })
  administration!: IssuingAdministration;

  @ManyToOne(() => AdministrationProfile, (profile) => profile.users, { nullable: true, onDelete: 'SET NULL' })
  profile!: AdministrationProfile;

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
