import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { AdministrationController } from './administration.controller';
import { AdministrationPublicController } from './administration-public.controller';
import { AdministrationService } from './administration.service';
import { IssuingAdministration } from './entities/issuing-administration.entity';
import { AdministrationProfile } from './entities/administration-profile.entity';
import { AdministrationUser } from './entities/administration-user.entity';
import { DocumentTemplate } from './entities/template.entity';
import { TemplateVariable } from './entities/template-variable.entity';
import { RecipientAdministration } from './entities/recipient-administration.entity';
import { RoutingRule } from './entities/routing-rule.entity';
import { SignatureProviderConfig } from './entities/signature-provider-config.entity';
import { NotificationConfig } from './entities/notification-config.entity';
import { DirectionType } from './entities/direction-type.entity';
import { AppSetting } from './entities/app-setting.entity';
import { RequestedAct } from './entities/requested-act.entity';
import { User } from '../users/user.entity';
import { UserDirectionAssignment } from '../users/user-direction-assignment.entity';
import { UsersModule } from '../users/users.module';
import { MenuPermissionGuard } from '../../common/guards/menu-permission.guard';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      IssuingAdministration,
      AdministrationProfile,
      AdministrationUser,
      DocumentTemplate,
      TemplateVariable,
      RecipientAdministration,
      RoutingRule,
      SignatureProviderConfig,
      NotificationConfig,
      DirectionType,
      AppSetting,
      RequestedAct,
      User,
      UserDirectionAssignment,
    ]),
    UsersModule,
  ],
  controllers: [AdministrationController, AdministrationPublicController],
  providers: [AdministrationService, MenuPermissionGuard],
  exports: [AdministrationService],
})
export class AdministrationModule {}
