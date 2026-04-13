import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { UsersService } from './users.service';
import { UsersController } from './users.controller';
import { User } from './user.entity';
import { AdministrationUser } from '../administration/entities/administration-user.entity';
import { AdministrationProfile } from '../administration/entities/administration-profile.entity';
import { AppSetting } from '../administration/entities/app-setting.entity';
import { IssuingAdministration } from '../administration/entities/issuing-administration.entity';
import { RecipientAdministration } from '../administration/entities/recipient-administration.entity';
import { UserDirectionAssignment } from './user-direction-assignment.entity';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      User,
      AdministrationUser,
      AdministrationProfile,
      AppSetting,
      IssuingAdministration,
      RecipientAdministration,
      UserDirectionAssignment,
    ]),
  ],
  providers: [UsersService],
  controllers: [UsersController],
  exports: [UsersService],
})
export class UsersModule {}
