import { IsString, IsOptional, IsObject, IsDateString } from 'class-validator';
import { ApiProperty } from '@nestjs/swagger';

export class GenerateQrCodeDto {
  @ApiProperty({ example: 'document-uuid' })
  @IsString()
  documentId: string;

  @ApiProperty({ example: 'verification' })
  @IsString()
  type: string;

  @ApiProperty({ example: { custom: 'data' } })
  @IsOptional()
  @IsObject()
  metadata: Record<string, any>;

  @ApiProperty({ example: '2026-12-31' })
  @IsDateString()
  expiresAt: string;
}

export class VerifyQrCodeDto {
  @ApiProperty({ example: 'qrcode_data_encoded' })
  @IsString()
  qrcodeData: string;
}

export class QrCodeResponseDto {
  @ApiProperty()
  id: string;

  @ApiProperty()
  documentId: string;

  @ApiProperty()
  data: string;

  @ApiProperty()
  verificationCode: string;

  @ApiProperty()
  status: string;

  @ApiProperty()
  scanCount: number;

  @ApiProperty()
  createdAt: Date;

  @ApiProperty()
  expiresAt: Date;
}
