import { IsString, IsOptional, IsUUID, IsDateString } from 'class-validator';
import { ApiProperty } from '@nestjs/swagger';

export class CreateSignatureDto {
  @ApiProperty({ required: false, example: 'sig-local-hash' })
  @IsOptional()
  @IsString()
  signatureHash?: string;

  @ApiProperty({ example: 'Signature digitale' })
  @IsOptional()
  @IsString()
  reason?: string;

  @ApiProperty({ example: 'Paris, France' })
  @IsOptional()
  @IsString()
  location?: string;

  @ApiProperty({ example: 'certificat_data' })
  @IsOptional()
  @IsString()
  certificate: string;
}

export class RequestSignatureDto {
  @ApiProperty({ example: 'test@example.com' })
  @IsString()
  recipientEmail: string;

  @ApiProperty({ example: 'Veuillez signer ce document' })
  @IsOptional()
  @IsString()
  message: string;

  @ApiProperty({ example: '2026-12-31', required: false })
  @IsOptional()
  @IsDateString()
  expiryDate?: string;
}

export class SignatureResponseDto {
  @ApiProperty()
  id: string;

  @ApiProperty()
  documentId: string;

  @ApiProperty()
  signerId: string;

  @ApiProperty()
  timestamp: Date;

  @ApiProperty()
  reason: string;

  @ApiProperty()
  location: string;

  @ApiProperty()
  isValid: boolean;

  @ApiProperty()
  status: string;

  @ApiProperty()
  createdAt: Date;
}
