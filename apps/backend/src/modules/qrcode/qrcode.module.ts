import { Module } from '@nestjs/common';
import { TypeOrmModule } from '@nestjs/typeorm';
import { QrcodeService } from './qrcode.service';
import { QrcodeController } from './qrcode.controller';
import { QrCode } from './qrcode.entity';
import { Document } from '../documents/document.entity';
import { IssuingAdministration } from '../administration/entities/issuing-administration.entity';
import { Signature } from '../signatures/signature.entity';

@Module({
  imports: [TypeOrmModule.forFeature([QrCode, Document, IssuingAdministration, Signature])],
  providers: [QrcodeService],
  controllers: [QrcodeController],
  exports: [QrcodeService],
})
export class QrcodeModule {}
