import { Entity, PrimaryGeneratedColumn, Column, CreateDateColumn, UpdateDateColumn, OneToMany, Index } from 'typeorm';
import { RoutingRule } from './routing-rule.entity';

@Entity('recipient_administrations')
@Index(['name'])
@Index(['channel'])
export class RecipientAdministration {
  @PrimaryGeneratedColumn('uuid')
  id!: string;

  @Column({ type: 'varchar', length: 255, unique: true })
  name!: string;

  @Column({ type: 'varchar', length: 20 })
  channel!: 'api' | 'email' | 'ler' | 'application';

  @Column({ type: 'varchar', length: 1000, nullable: true })
  apiEndpoint!: string;

  @Column({ type: 'varchar', length: 255, nullable: true })
  emailAddress!: string;

  @Column({ type: 'json', nullable: true })
  metadata!: Record<string, any>;

  @Column({ type: 'boolean', default: true })
  isActive!: boolean;

  @OneToMany(() => RoutingRule, (rule) => rule.recipientAdministration)
  routingRules!: RoutingRule[];

  @CreateDateColumn()
  createdAt!: Date;

  @UpdateDateColumn()
  updatedAt!: Date;
}
