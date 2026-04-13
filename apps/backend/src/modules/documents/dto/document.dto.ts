import {
  IsString,
  IsOptional,
  IsEmail,
  IsIn,
  IsNumber,
  IsUUID,
  Min,
  IsBoolean,
  IsArray,
} from 'class-validator';
import { ApiProperty } from '@nestjs/swagger';

export class CreateDocumentDto {
  @ApiProperty({ example: 'Mon Document' })
  @IsString()
  title: string;

  @ApiProperty({ example: 'Description du document', required: false })
  @IsOptional()
  @IsString()
  description?: string;

  @ApiProperty({ example: 'CONTROLE', description: 'Code entite sous tutelle', required: false })
  @IsOptional()
  @IsString()
  subEntityCode?: string;

  @ApiProperty({ required: false, example: 'uuid-recipient-administration' })
  @IsOptional()
  @IsUUID()
  recipientAdministrationId?: string;
}

export class UpdateDocumentDto {
  @ApiProperty({ example: 'Mon Document Modifie', required: false })
  @IsOptional()
  @IsString()
  title?: string;

  @ApiProperty({ example: 'Description modifiee', required: false })
  @IsOptional()
  @IsString()
  description?: string;

  @ApiProperty({ example: 'CONTROLE', description: 'Code entite sous tutelle', required: false })
  @IsOptional()
  @IsString()
  subEntityCode?: string;

  @ApiProperty({ required: false, example: 'uuid-recipient-administration' })
  @IsOptional()
  @IsUUID()
  recipientAdministrationId?: string;
}

export class ShareDocumentDto {
  @ApiProperty({ example: 'internal', enum: ['internal', 'external', 'recipient_administration'] })
  @IsString()
  @IsIn(['internal', 'external', 'recipient_administration'])
  mode: 'internal' | 'external' | 'recipient_administration';

  @ApiProperty({ required: false, example: 'uuid-recipient-administration' })
  @IsOptional()
  @IsUUID()
  recipientAdministrationId?: string;

  @ApiProperty({ example: 'agent.rhs@admin.local', required: false })
  @IsOptional()
  @IsEmail()
  recipientEmail?: string;

  @ApiProperty({ example: 'Direction RH', required: false })
  @IsOptional()
  @IsString()
  recipientName?: string;

  @ApiProperty({ example: 'Kouadio Ange-Helene', required: false })
  @IsOptional()
  @IsString()
  applicantFullName?: string;

  @ApiProperty({ example: 'MTR-2026-0012', required: false })
  @IsOptional()
  @IsString()
  applicantMatricule?: string;

  @ApiProperty({ example: 'usager@example.com', required: false })
  @IsOptional()
  @IsEmail()
  applicantEmail?: string;

  @ApiProperty({ example: 'lecture', enum: ['lecture', 'modification'], required: false })
  @IsOptional()
  @IsString()
  @IsIn(['lecture', 'modification'])
  permission?: 'lecture' | 'modification';

  @ApiProperty({ example: true, required: false })
  @IsOptional()
  hasDelay?: boolean;

  @ApiProperty({ example: 24, required: false })
  @IsOptional()
  @IsNumber()
  @Min(1)
  delayValue?: number;

  @ApiProperty({ example: 'hours', enum: ['hours', 'days'], required: false })
  @IsOptional()
  @IsString()
  @IsIn(['hours', 'days'])
  delayUnit?: 'hours' | 'days';
}

export class DocumentResponseDto {
  @ApiProperty()
  id: string;

  @ApiProperty()
  title: string;

  @ApiProperty()
  description: string;

  @ApiProperty()
  filePath: string;

  @ApiProperty()
  fileSize: number;

  @ApiProperty()
  mimeType: string;

  @ApiProperty()
  status: string;

  @ApiProperty()
  createdBy: string;

  @ApiProperty()
  ownerId: string;

  @ApiProperty()
  createdAt: Date;

  @ApiProperty()
  updatedAt: Date;
}

export class UpdateDocumentFavoriteDto {
  @ApiProperty({ example: true })
  @IsBoolean()
  isFavorite: boolean;
}

export class UpdateDocumentLabelCodesDto {
  @ApiProperty({ example: ['ETQ-RH-001', 'DOSSIER-2026'], type: [String] })
  @IsArray()
  @IsString({ each: true })
  codes: string[];
}
