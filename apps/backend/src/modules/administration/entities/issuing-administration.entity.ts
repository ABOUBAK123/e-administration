import {
  Entity,
  PrimaryGeneratedColumn,
  Column,
  CreateDateColumn,
  UpdateDateColumn,
  OneToMany,
  Index,
} from 'typeorm';
import { AdministrationProfile } from './administration-profile.entity';
import { AdministrationUser } from './administration-user.entity';
import { DocumentTemplate } from './template.entity';

@Entity('issuing_administrations')
@Index(['name'])
@Index(['code'])
export class IssuingAdministration {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'varchar', length: 255, unique: true })
  name!: string;

  @Column({ type: 'varchar', length: 100, unique: true })
  code!: string;

  @Column({ type: 'boolean', default: true })
  isActive!: boolean;

  @Column({ type: 'varchar', length: 50, default: 'DOC' })
  documentNumberPrefix!: string;

  @Column({ type: 'integer', default: 6 })
  documentNumberPadding!: number;

  @Column({ type: 'integer', default: 0 })
  documentNumberSequence!: number;

  @Column({ type: 'varchar', length: 500, nullable: true })
  logo!: string | null;

  @Column({ type: 'json', nullable: true })
  metadata!: Record<string, any>;

  @OneToMany(() => AdministrationProfile, (profile) => profile.administration, { cascade: true })
  profiles!: AdministrationProfile[];

  @OneToMany(() => AdministrationUser, (user) => user.administration, { cascade: true })
  users!: AdministrationUser[];

  @OneToMany(() => DocumentTemplate, (template) => template.administration)
  templates!: DocumentTemplate[];

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
