import { IsString, IsOptional, IsArray, IsUUID, IsNumber } from 'class-validator';
import { ApiProperty } from '@nestjs/swagger';
import { Type } from 'class-transformer';

export class CreateWorkflowStepDto {
  @ApiProperty({ example: 'Étape 1' })
  @IsString()
  name: string;

  @ApiProperty({ example: 'user-uuid' })
  @IsUUID()
  approverId: string;

  @ApiProperty({ example: 1 })
  order: number;
}

export class SignatureZoneDto {
  @ApiProperty({ example: 10 })
  @IsNumber()
  x: number;

  @ApiProperty({ example: 15 })
  @IsNumber()
  y: number;

  @ApiProperty({ example: 28 })
  @IsNumber()
  width: number;

  @ApiProperty({ example: 12 })
  @IsNumber()
  height: number;
}

export class UploadedSignatureFileDto {
  @ApiProperty({ example: 'CONTRAT DE BAIL.pdf' })
  @IsString()
  fileName: string;

  @ApiProperty({ example: 780880 })
  @IsNumber()
  fileSize: number;

  @ApiProperty({ example: 'application/pdf' })
  @IsString()
  fileType: string;

  @ApiProperty({ type: [SignatureZoneDto] })
  @IsArray()
  @Type(() => SignatureZoneDto)
  zones: SignatureZoneDto[];
}

export class CreateWorkflowDto {
  @ApiProperty({ example: "Workflow d'approbation" })
  @IsString()
  name: string;

  @ApiProperty({ example: 'Workflow pour approbation des documents' })
  @IsOptional()
  @IsString()
  description: string;

  @ApiProperty({ type: [CreateWorkflowStepDto] })
  @IsArray()
  @Type(() => CreateWorkflowStepDto)
  steps: CreateWorkflowStepDto[];

  @ApiProperty({ type: [String], required: false })
  @IsOptional()
  @IsArray()
  @IsUUID('4', { each: true })
  docsToSign?: string[];

  @ApiProperty({ type: [String], required: false })
  @IsOptional()
  @IsArray()
  @IsUUID('4', { each: true })
  attachedDocs?: string[];

  @ApiProperty({ type: [UploadedSignatureFileDto], required: false })
  @IsOptional()
  @IsArray()
  @Type(() => UploadedSignatureFileDto)
  uploadedSignatureFiles?: UploadedSignatureFileDto[];
}

export class ExecuteWorkflowDto {
  @ApiProperty({ example: 'user-uuid' })
  @IsUUID()
  workflowId: string;
}

export class UpdateWorkflowDto {
  @ApiProperty({ example: "Workflow d'approbation modifié" })
  @IsOptional()
  @IsString()
  name?: string;

  @ApiProperty({ example: 'Workflow pour approbation des documents modifié' })
  @IsOptional()
  @IsString()
  description?: string;

  @ApiProperty({ type: [CreateWorkflowStepDto] })
  @IsOptional()
  @IsArray()
  @Type(() => CreateWorkflowStepDto)
  steps?: CreateWorkflowStepDto[];

  @ApiProperty({ type: [String], required: false })
  @IsOptional()
  @IsArray()
  @IsUUID('4', { each: true })
  docsToSign?: string[];

  @ApiProperty({ type: [String], required: false })
  @IsOptional()
  @IsArray()
  @IsUUID('4', { each: true })
  attachedDocs?: string[];

  @ApiProperty({ type: [UploadedSignatureFileDto], required: false })
  @IsOptional()
  @IsArray()
  @Type(() => UploadedSignatureFileDto)
  uploadedSignatureFiles?: UploadedSignatureFileDto[];
}

export class WorkflowResponseDto {
  @ApiProperty()
  id: string;

  @ApiProperty()
  name: string;

  @ApiProperty()
  description: string;

  @ApiProperty()
  status: string;

  @ApiProperty()
  steps: any[];

  @ApiProperty()
  createdBy: string;

  @ApiProperty()
  createdAt: Date;

  @ApiProperty()
  updatedAt: Date;
}

export class WorkflowTemplateAssigneeDto {
  @ApiProperty({ example: 1 })
  @IsNumber()
  id!: number;

  @ApiProperty({ required: false, example: 'user-uuid' })
  @IsOptional()
  @IsUUID()
  approverId?: string;

  @ApiProperty({ required: false, example: 'user-uuid' })
  @IsOptional()
  @IsUUID()
  signerId?: string;
}

export class CreateWorkflowTemplateDto {
  @ApiProperty({ example: 'Modèle validation RH' })
  @IsString()
  name!: string;

  @ApiProperty({ required: false, example: 'Parapheur sans pièces jointes' })
  @IsOptional()
  @IsString()
  description?: string;

  @ApiProperty({ type: [WorkflowTemplateAssigneeDto] })
  @IsArray()
  @Type(() => WorkflowTemplateAssigneeDto)
  validationSteps!: WorkflowTemplateAssigneeDto[];

  @ApiProperty({ type: [WorkflowTemplateAssigneeDto] })
  @IsArray()
  @Type(() => WorkflowTemplateAssigneeDto)
  signatureSteps!: WorkflowTemplateAssigneeDto[];

  @ApiProperty({
    required: false,
    example: {
      notifyEmail: true,
      emails: 'user@gov.ma',
      cc: '',
      stages: {
        onValidationStep: true,
        onSignatureStep: true,
        onApproved: true,
        onRejected: false,
        onCompleted: true,
      },
      sendDownloadLink: true,
    },
  })
  @IsOptional()
  notificationConfig?: {
    notifyEmail: boolean;
    emails?: string;
    cc?: string;
    stages?: {
      onValidationStep?: boolean;
      onSignatureStep?: boolean;
      onApproved?: boolean;
      onRejected?: boolean;
      onCompleted?: boolean;
    };
    sendDownloadLink?: boolean;
  };
}
