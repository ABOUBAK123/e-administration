import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { DocumentsService } from './documents.service';
import { DocumentsController } from './documents.controller';
import { DocumentsPublicController } from './documents-public.controller';
import { Document } from './document.entity';
import { DocumentVersion } from './document-version.entity';
import { User } from '../users/user.entity';
import { NotificationsModule } from '../notifications/notifications.module';
import { AdministrationUser } from '../administration/entities/administration-user.entity';
import { AdministrationProfile } from '../administration/entities/administration-profile.entity';
import { RecipientAdministration } from '../administration/entities/recipient-administration.entity';
import { IssuingAdministration } from '../administration/entities/issuing-administration.entity';
import { RequestedAct } from '../administration/entities/requested-act.entity';
import { UserDirectionAssignment } from '../users/user-direction-assignment.entity';
import { QrcodeModule } from '../qrcode/qrcode.module';
import { DocumentUserPreference } from './document-user-preference.entity';

@Module({
  imports: [TypeOrmModule.forFeature([Document, DocumentVersion, DocumentUserPreference, User, AdministrationUser, AdministrationProfile, RecipientAdministration, IssuingAdministration, RequestedAct, UserDirectionAssignment]), NotificationsModule, QrcodeModule],
  providers: [DocumentsService],
  controllers: [DocumentsController, DocumentsPublicController],
  exports: [DocumentsService],
})
export class DocumentsModule {}
