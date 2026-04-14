import { ApiProperty } from '@nestjs/swagger';
import {
  IsArray,
  IsBoolean,
  IsEmail,
  IsIn,
  IsInt,
  IsObject,
  IsOptional,
  IsString,
  IsUUID,
  Min,
} from 'class-validator';

export class CreateTemplateDto {
  @ApiProperty({ example: 'Template Attestation' })
  @IsString()
  name: string;

  @ApiProperty({ example: 'attestation.docx' })
  @IsString()
  fileName: string;

  @ApiProperty({ example: 'docx' })
  @IsString()
  fileType: 'docx' | 'xlsx' | 'pptx' | 'pdf';

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  storagePath?: string;

  @ApiProperty({
    required: false,
    description: 'Template body with placeholders like {{nom_usager}}',
  })
  @IsOptional()
  @IsString()
  content?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsUUID()
  administrationId?: string;
}

export class UpdateTemplateDto {
  @IsOptional()
  @IsString()
  name?: string;

  @IsOptional()
  @IsString()
  fileName?: string;

  @IsOptional()
  @IsString()
  fileType?: 'docx' | 'xlsx' | 'pptx' | 'pdf';

  @IsOptional()
  @IsString()
  storagePath?: string;

  @IsOptional()
  @IsString()
  content?: string;
}

export class GenerateTemplateDocumentDto {
  @ApiProperty({ required: false, type: Object, description: 'Values map for placeholders' })
  @IsOptional()
  @IsObject()
  values?: Record<string, string>;

  @ApiProperty({ required: false, example: 'attestation-generee.txt' })
  @IsOptional()
  @IsString()
  outputFileName?: string;

  @ApiProperty({ required: false, example: true, description: 'Require a non-empty value for every template placeholder' })
  @IsOptional()
  @IsBoolean()
  requireAllFields?: boolean;

  @ApiProperty({ required: false, example: 'pdf', enum: ['pdf'] })
  @IsOptional()
  @IsIn(['pdf'])
  outputFormat?: 'pdf';
}

export class CreateTemplateVariableDto {
  @ApiProperty({ example: 'nom_usager' })
  @IsString()
  key: string;

  @ApiProperty({ example: 'Nom usager' })
  @IsString()
  label: string;

  @ApiProperty({ example: 'text' })
  @IsString()
  fieldType: 'text' | 'date' | 'number' | 'select' | 'textarea';

  @ApiProperty({ required: false })
  @IsOptional()
  @IsBoolean()
  required?: boolean;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  placeholder?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  defaultValue?: string;

  @ApiProperty({ required: false, type: [String] })
  @IsOptional()
  @IsArray()
  options?: string[];
}

export class UpdateTemplateVariableDto {
  @IsOptional()
  @IsString()
  key?: string;

  @IsOptional()
  @IsString()
  label?: string;

  @IsOptional()
  @IsString()
  fieldType?: 'text' | 'date' | 'number' | 'select' | 'textarea';

  @IsOptional()
  @IsBoolean()
  required?: boolean;

  @IsOptional()
  @IsString()
  placeholder?: string;

  @IsOptional()
  @IsString()
  defaultValue?: string;

  @IsOptional()
  @IsArray()
  options?: string[];
}

export class CreateIssuingAdministrationDto {
  @ApiProperty({ example: 'Direction Générale des Impôts' })
  @IsString()
  name: string;

  @ApiProperty({ example: 'DGI' })
  @IsString()
  code: string;

  @ApiProperty({ required: false, example: 'DGI' })
  @IsOptional()
  @IsString()
  documentNumberPrefix?: string;

  @ApiProperty({ required: false, example: 6 })
  @IsOptional()
  @IsInt()
  @Min(3)
  documentNumberPadding?: number;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsObject()
  metadata?: Record<string, any>;
}

export class UpdateIssuingAdministrationDto {
  @IsOptional()
  @IsString()
  name?: string;

  @IsOptional()
  @IsString()
  code?: string;

  @IsOptional()
  @IsBoolean()
  isActive?: boolean;

  @IsOptional()
  @IsString()
  documentNumberPrefix?: string;

  @IsOptional()
  @IsInt()
  @Min(3)
  documentNumberPadding?: number;

  @IsOptional()
  @IsObject()
  metadata?: Record<string, any>;
}

export class CreateAdministrationProfileDto {
  @ApiProperty({ example: 'Gestionnaire RH' })
  @IsString()
  name: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsObject()
  permissions?: Record<string, any>;
}

export class UpdateAdministrationProfileDto {
  @IsOptional()
  @IsString()
  name?: string;

  @IsOptional()
  @IsObject()
  permissions?: Record<string, any>;
}

export class CreateDirectionTypeDto {
  @ApiProperty({ example: 'Direction Générale' })
  @IsString()
  name: string;

  @ApiProperty({ required: false, example: 'Type utilisé pour les directions générales.' })
  @IsOptional()
  @IsString()
  description?: string;
}

export class UpdateDirectionTypeDto {
  @IsOptional()
  @IsString()
  name?: string;

  @IsOptional()
  @IsString()
  description?: string;
}

export class CreateAdministrationUserDto {
  @ApiProperty({ example: 'Jean Dupont' })
  @IsString()
  fullName: string;

  @ApiProperty({ example: 'jean.dupont@admin.local' })
  @IsEmail()
  email: string;

  @ApiProperty({ example: 'jdupont' })
  @IsString()
  username: string;

  @ApiProperty({
    required: false,
    enum: ['super_admin', 'admin', 'manager', 'user', 'signer'],
    default: 'user',
  })
  @IsOptional()
  @IsString()
  adminRole?: 'super_admin' | 'admin' | 'manager' | 'user' | 'signer';

  @ApiProperty({ required: false })
  @IsOptional()
  @IsUUID()
  profileId?: string;
}

export class UpdateAdministrationUserDto {
  @IsOptional()
  @IsString()
  fullName?: string;

  @IsOptional()
  @IsEmail()
  email?: string;

  @IsOptional()
  @IsString()
  username?: string;

  @IsOptional()
  @IsString()
  adminRole?: 'super_admin' | 'admin' | 'manager' | 'user' | 'signer';

  @IsOptional()
  @IsUUID()
  profileId?: string;

  @IsOptional()
  @IsString()
  status?: 'active' | 'inactive';
}

export class CreateRecipientAdministrationDto {
  @ApiProperty({ example: 'Trésor Public' })
  @IsString()
  name: string;

  @ApiProperty({ example: 'api' })
  @IsString()
  channel: 'api' | 'email' | 'ler' | 'application';

  @ApiProperty({ required: false })
  @IsOptional()
  @IsString()
  apiEndpoint?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsEmail()
  emailAddress?: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsObject()
  metadata?: Record<string, any>;
}

export class UpdateRecipientAdministrationDto {
  @IsOptional()
  @IsString()
  name?: string;

  @IsOptional()
  @IsString()
  channel?: 'api' | 'email' | 'ler' | 'application';

  @IsOptional()
  @IsString()
  apiEndpoint?: string;

  @IsOptional()
  @IsEmail()
  emailAddress?: string;

  @IsOptional()
  @IsObject()
  metadata?: Record<string, any>;

  @IsOptional()
  @IsBoolean()
  isActive?: boolean;
}

export class CreateRoutingRuleDto {
  @ApiProperty({ example: 'Routage Attestation vers Trésor' })
  @IsString()
  name: string;

  @ApiProperty({ example: 'ATTESTATION' })
  @IsString()
  documentType: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsUUID()
  templateId?: string;

  @ApiProperty()
  @IsUUID()
  recipientAdministrationId: string;

  @ApiProperty({ required: false })
  @IsOptional()
  @IsObject()
  conditions?: Record<string, any>;

  @ApiProperty({ required: false, example: 1 })
  @IsOptional()
  @IsInt()
  @Min(1)
  priority?: number;
}

export class UpdateRoutingRuleDto {
  @IsOptional()
  @IsString()
  name?: string;

  @IsOptional()
  @IsString()
  documentType?: string;

  @IsOptional()
  @IsUUID()
  templateId?: string;

  @IsOptional()
  @IsUUID()
  recipientAdministrationId?: string;

  @IsOptional()
  @IsObject()
  conditions?: Record<string, any>;

  @IsOptional()
  @IsInt()
  @Min(1)
  priority?: number;

  @IsOptional()
  @IsBoolean()
  isActive?: boolean;
}

export class UpsertSignatureProviderConfigDto {
  @ApiProperty({
    required: false,
    example: 'uuid-admin-id',
    description: 'Administration ID for multi-tenant config',
  })
  @IsOptional()
  @IsUUID()
  administrationId?: string;

  @ApiProperty({ required: false, example: true })
  @IsOptional()
  @IsBoolean()
  isActive?: boolean;

  @ApiProperty({ required: false, example: 'https://uvci.artci-sign.ci/api/' })
  @IsOptional()
  @IsString()
  endpoint?: string;

  @ApiProperty({ required: false, example: 'sign' })
  @IsOptional()
  @IsString()
  signPath?: string;

  @ApiProperty({ required: false, example: 'act_...key...' })
  @IsOptional()
  @IsString()
  apiKey?: string;

  @ApiProperty({ required: false, example: 'cop_xxx' })
  @IsOptional()
  @IsString()
  consentPageId?: string;

  @ApiProperty({ required: false, example: 'sip_xxx' })
  @IsOptional()
  @IsString()
  signatureProfileId?: string;

  @ApiProperty({
    required: false,
    example: 'usr_xxx',
    description: 'User ID of the API token owner on the provider platform',
  })
  @IsOptional()
  @IsString()
  providerOwnerUserId?: string;

  @ApiProperty({ required: false, example: true })
  @IsOptional()
  @IsBoolean()
  verifySsl?: boolean;

  @ApiProperty({ required: false, example: 30000 })
  @IsOptional()
  @IsInt()
  @Min(1000)
  timeoutMs?: number;
}

export class UpsertNotificationConfigDto {
  @ApiProperty({ required: false, example: true })
  @IsOptional()
  @IsBoolean()
  isActive?: boolean;

  @ApiProperty({ required: false, example: 'smtp.office365.com' })
  @IsOptional()
  @IsString()
  smtpHost?: string;

  @ApiProperty({ required: false, example: 587 })
  @IsOptional()
  @IsInt()
  @Min(1)
  smtpPort?: number;

  @ApiProperty({ required: false, example: false })
  @IsOptional()
  @IsBoolean()
  smtpSecure?: boolean;

  @ApiProperty({ required: false, example: 'admin@contoso.com' })
  @IsOptional()
  @IsString()
  smtpUser?: string;

  @ApiProperty({ required: false, example: 'app-password' })
  @IsOptional()
  @IsString()
  smtpPassword?: string;

  @ApiProperty({ required: false, example: 'admin@contoso.com' })
  @IsOptional()
  @IsString()
  smtpFrom?: string;

  @ApiProperty({
    required: false,
    type: Object,
    example: {
      onDocumentShared: true,
      onSignatureRequested: true,
      onSignatureResponded: true,
      onWorkflowAssigned: true,
      onWorkflowStepCompleted: true,
      onDocumentUploaded: false,
      onUserCreated: false,
    },
  })
  @IsOptional()
  @IsObject()
  triggers?: Record<string, boolean>;
}

export class UpsertAppSettingDto {
  @ApiProperty({ example: 'settings value' })
  @IsOptional()
  @IsString()
  value?: string;

  @ApiProperty({ required: false, example: 'Description of the setting' })
  @IsOptional()
  @IsString()
  description?: string;
}

export class CreateRequestedActDto {
  @ApiProperty({ example: 'emitter' })
  @IsString()
  administrationScopeType: 'emitter' | 'recipient';

  @ApiProperty({ example: '3f59a14f-8dc4-41c7-b35f-8ca6e2a6d89f' })
  @IsUUID()
  administrationScopeId: string;

  @ApiProperty({ example: 'Émettrice - Direction Générale des Impôts' })
  @IsString()
  administrationLabel: string;

  @ApiProperty({ example: 'DIR001' })
  @IsString()
  directionCode: string;

  @ApiProperty({ example: 'DIR001 - Direction du Contrôle' })
  @IsString()
  directionLabel: string;

  @ApiProperty({ example: 'Demande de certificat de nationalité' })
  @IsString()
  documentName: string;

  @ApiProperty({ type: [String], example: ['Copie CNI', 'Extrait de naissance'] })
  @IsArray()
  @IsString({ each: true })
  requiredDocuments: string[];

  @ApiProperty({
    required: false,
    type: [Object],
    example: [
      { label: 'Nom du bénéficiaire', inputType: 'text' },
      { label: 'Date de naissance', inputType: 'date' },
      { label: 'Téléphone', inputType: 'phone' },
    ],
  })
  @IsOptional()
  @IsArray()
  @IsObject({ each: true })
  applicantFields?: Array<{
    label: string;
    inputType: 'text' | 'date' | 'number' | 'phone' | 'email' | 'textarea';
  }>;
}

export class UpdateRequestedActDto {
  @ApiProperty({ required: false, example: 'emitter' })
  @IsOptional()
  @IsString()
  administrationScopeType?: 'emitter' | 'recipient';

  @ApiProperty({ required: false, example: '3f59a14f-8dc4-41c7-b35f-8ca6e2a6d89f' })
  @IsOptional()
  @IsUUID()
  administrationScopeId?: string;

  @ApiProperty({ required: false, example: 'Émettrice - Direction Générale des Impôts' })
  @IsOptional()
  @IsString()
  administrationLabel?: string;

  @ApiProperty({ required: false, example: 'DIR001' })
  @IsOptional()
  @IsString()
  directionCode?: string;

  @ApiProperty({ required: false, example: 'DIR001 - Direction du Contrôle' })
  @IsOptional()
  @IsString()
  directionLabel?: string;

  @ApiProperty({ required: false, example: 'Demande de certificat de nationalité' })
  @IsOptional()
  @IsString()
  documentName?: string;

  @ApiProperty({ required: false, type: [String], example: ['Copie CNI', 'Extrait de naissance'] })
  @IsOptional()
  @IsArray()
  @IsString({ each: true })
  requiredDocuments?: string[];

  @ApiProperty({
    required: false,
    type: [Object],
    example: [
      { label: 'Nom du bénéficiaire', inputType: 'text' },
      { label: 'Date de naissance', inputType: 'date' },
      { label: 'Téléphone', inputType: 'phone' },
    ],
  })
  @IsOptional()
  @IsArray()
  @IsObject({ each: true })
  applicantFields?: Array<{
    label: string;
    inputType: 'text' | 'date' | 'number' | 'phone' | 'email' | 'textarea';
  }>;
}
