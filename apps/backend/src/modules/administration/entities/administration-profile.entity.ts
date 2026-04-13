import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  ManyToOne,
  CreateDateColumn,
  UpdateDateColumn,
  Index,
  OneToMany,
} from 'typeorm';
import { IssuingAdministration } from './issuing-administration.entity';
import { AdministrationUser } from './administration-user.entity';

@Entity('administration_profiles')
@Index(['administrationId'])
@Index(['name'])
export class AdministrationProfile {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'uuid' })
  administrationId!: string;

  @Column({ type: 'varchar', length: 150 })
  name!: string;

  @Column({ type: 'json', nullable: true })
  permissions!: Record<string, any>;

  @ManyToOne(() => IssuingAdministration, (administration) => administration.profiles, {
    onDelete: 'CASCADE',
  })
  administration!: IssuingAdministration;

  @OneToMany(() => AdministrationUser, (user) => user.profile)
  users!: AdministrationUser[];

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
