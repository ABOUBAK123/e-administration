import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { SignaturesService } from './signatures.service';
import { SignaturesController } from './signatures.controller';
import { Signature } from './signature.entity';
import { SignatureRequest } from './signature-request.entity';
import { Document } from '../documents/document.entity';
import { User } from '../users/user.entity';
import { NotificationsModule } from '../notifications/notifications.module';
import { AdministrationUser } from '../administration/entities/administration-user.entity';
import { IssuingAdministration } from '../administration/entities/issuing-administration.entity';
import { SignatureProviderConfig } from '../administration/entities/signature-provider-config.entity';
import { AppSetting } from '../administration/entities/app-setting.entity';
import { QrcodeModule } from '../qrcode/qrcode.module';

@Module({
  imports: [
    TypeOrmModule.forFeature([
      Signature,
      SignatureRequest,
      Document,
      User,
      AdministrationUser,
      IssuingAdministration,
      SignatureProviderConfig,
      AppSetting,
    ]),
    NotificationsModule,
    QrcodeModule,
  ],
  providers: [SignaturesService],
  controllers: [SignaturesController],
  exports: [SignaturesService],
})
export class SignaturesModule {}
