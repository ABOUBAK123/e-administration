import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { JwtModule } from '@nestjs/jwt';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { WorkflowsService } from './workflows.service';
import { WorkflowsController } from './workflows.controller';
import { Workflow } from './workflow.entity';
import { WorkflowStep } from './workflow-step.entity';
import { WorkflowExecution } from './workflow-execution.entity';
import { Document } from '../documents/document.entity';
import { WorkflowTemplate } from './workflow-template.entity';
import { User } from '../users/user.entity';
import { AdministrationUser } from '../administration/entities/administration-user.entity';
import { NotificationsModule } from '../notifications/notifications.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      Workflow,
      WorkflowStep,
      WorkflowExecution,
      WorkflowTemplate,
      Document,
      User,
      AdministrationUser,
    ]),
    NotificationsModule,
    JwtModule.registerAsync({
      imports: [ConfigModule],
      inject: [ConfigService],
      useFactory: (configService: ConfigService) => ({
        secret: configService.get<string>('JWT_SECRET') || 'your-secret-key-change-in-production',
      }),
    }),
    ConfigModule,
  ],
  providers: [WorkflowsService],
  controllers: [WorkflowsController],
  exports: [WorkflowsService],
})
export class WorkflowsModule {}
