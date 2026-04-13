import { Injectable, NotFoundException, BadRequestException, Logger } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { JwtService } from '@nestjs/jwt';
import { ConfigService } from '@nestjs/config';
import { Workflow } from './workflow.entity';
import { WorkflowStep } from './workflow-step.entity';
import { WorkflowExecution } from './workflow-execution.entity';
import { Document } from '../documents/document.entity';
import {
  CreateWorkflowDto,
  CreateWorkflowTemplateDto,
  UpdateWorkflowDto,
} from './dto/workflow.dto';
import { WorkflowTemplate } from './workflow-template.entity';
import { User } from '../users/user.entity';
import { AdministrationUser } from '../administration/entities/administration-user.entity';
import { NotificationsService } from '../notifications/notifications.service';

@Injectable()
export class WorkflowsService {
  private readonly logger = new Logger(WorkflowsService.name);

  constructor(
    @InjectRepository(Workflow)
    private workflowRepository: Repository<Workflow>,
    @InjectRepository(WorkflowStep)
    private workflowStepRepository: Repository<WorkflowStep>,
    @InjectRepository(WorkflowExecution)
    private workflowExecutionRepository: Repository<WorkflowExecution>,
    @InjectRepository(Document)
    private documentRepository: Repository<Document>,
    @InjectRepository(WorkflowTemplate)
    private workflowTemplateRepository: Repository<WorkflowTemplate>,
    @InjectRepository(User)
    private userRepository: Repository<User>,
    @InjectRepository(AdministrationUser)
    private administrationUserRepository: Repository<AdministrationUser>,
    private readonly notificationsService: NotificationsService,
    private readonly jwtService: JwtService,
    private readonly configService: ConfigService
  ) {}

  private async resolveUserAdministrationId(userId: string): Promise<string | null> {
    const user = await this.userRepository.findOne({ where: { id: userId } });
    if (!user) {
      return null;
    }

    const administrationUser = await this.administrationUserRepository.findOne({
      where: [{ email: user.email }, { username: user.username }],
    });

    return administrationUser?.administrationId || null;
  }

  async findAll(userId: string) {
    const all = await this.workflowRepository.find({
      relations: ['steps', 'creator', 'executions'],
    });
    return all.filter((wf) => {
      if (wf.createdBy === userId) return true;
      if (wf.steps?.some((s) => s.assigneeId === userId)) return true;
      return false;
    });
  }

  async findTemplates(userId: string) {
    try {
      const administrationId = await this.resolveUserAdministrationId(userId);
      if (!administrationId) {
        this.logger.warn(`findTemplates: no administrationId for user ${userId}`);
        return [];
      }

      return await this.workflowTemplateRepository.find({
        where: {
          administrationId,
          status: 'active',
        },
        order: {
          createdAt: 'DESC',
        },
      });
    } catch (error) {
      this.logger.error(`findTemplates error for user ${userId}`, error?.stack || error);
      throw error;
    }
  }

  async createTemplate(userId: string, templateData: CreateWorkflowTemplateDto) {
    const administrationId = await this.resolveUserAdministrationId(userId);
    if (!administrationId) {
      throw new BadRequestException('Administration introuvable pour cet utilisateur');
    }

    const hasValidator = (templateData.validationSteps || []).some(
      (step) => !!step.approverId?.trim()
    );
    if (!hasValidator) {
      throw new BadRequestException('Au moins un validateur est requis');
    }

    const template = this.workflowTemplateRepository.create({
      administrationId,
      name: templateData.name.trim(),
      description: templateData.description?.trim() || null,
      validationSteps: (templateData.validationSteps || []).filter(
        (step) => !!step.approverId?.trim()
      ),
      signatureSteps: (templateData.signatureSteps || []).filter((step) => !!step.signerId?.trim()),
      notificationConfig: templateData.notificationConfig || null,
      status: 'active',
      createdBy: userId,
    });

    return this.workflowTemplateRepository.save(template);
  }

  async findOne(id: string) {
    const workflow = await this.workflowRepository.findOne({
      where: { id },
      relations: ['steps', 'creator', 'executions'],
    });

    if (!workflow) {
      throw new NotFoundException('Workflow not found');
    }

    return workflow;
  }

  async create(userId: string, workflowData: CreateWorkflowDto) {
    const workflow = this.workflowRepository.create({
      name: workflowData.name,
      description: workflowData.description,
      docsToSign: workflowData.docsToSign || [],
      attachedDocs: workflowData.attachedDocs || [],
      uploadedSignatureFiles: workflowData.uploadedSignatureFiles || [],
      createdBy: userId,
    });

    const savedWorkflow = await this.workflowRepository.save(workflow);

    // Create workflow steps
    if (workflowData.steps && workflowData.steps.length > 0) {
      const steps = workflowData.steps.map((step) =>
        this.workflowStepRepository.create({
          workflowId: savedWorkflow.id,
          order: step.order,
          type: 'approve',
          assigneeId: step.approverId,
          description: step.name,
          requiresSignature: true,
        })
      );
      await this.workflowStepRepository.save(steps);
    }

    return await this.findOne(savedWorkflow.id);
  }

  async update(id: string, workflowData: UpdateWorkflowDto) {
    const workflow = await this.findOne(id);

    if (workflowData.name !== undefined) {
      workflow.name = workflowData.name;
    }
    if (workflowData.description !== undefined) {
      workflow.description = workflowData.description;
    }
    if (workflowData.docsToSign !== undefined) {
      workflow.docsToSign = workflowData.docsToSign;
    }
    if (workflowData.attachedDocs !== undefined) {
      workflow.attachedDocs = workflowData.attachedDocs;
    }
    if (workflowData.uploadedSignatureFiles !== undefined) {
      workflow.uploadedSignatureFiles = workflowData.uploadedSignatureFiles;
    }

    // Update steps
    await this.workflowStepRepository.delete({ workflowId: id });

    if (workflowData.steps && workflowData.steps.length > 0) {
      const steps = workflowData.steps.map((step) =>
        this.workflowStepRepository.create({
          workflowId: id,
          order: step.order,
          type: 'approve',
          assigneeId: step.approverId,
          description: step.name,
          requiresSignature: true,
        })
      );
      await this.workflowStepRepository.save(steps);
    }

    return await this.workflowRepository.save(workflow);
  }

  async delete(id: string) {
    const workflow = await this.findOne(id);
    await this.workflowRepository.remove(workflow);

    return { message: 'Workflow deleted successfully' };
  }

  async getSteps(id: string) {
    return await this.workflowStepRepository.find({
      where: { workflowId: id },
      relations: ['assignee'],
    });
  }

  async executeWorkflow(documentId: string, workflowId: string, initiatorUserId?: string) {
    const document = await this.documentRepository.findOne({
      where: { id: documentId },
    });

    if (!document) {
      throw new NotFoundException('Document not found');
    }

    const workflow = await this.findOne(workflowId);

    // Create workflow execution
    const execution = this.workflowExecutionRepository.create({
      workflowId,
      documentId,
      currentStep: 1,
      status: 'in_progress',
      stepData: {},
    });

    const savedExecution = await this.workflowExecutionRepository.save(execution);

    // Update document status
    document.status = 'pending_signature';
    await this.documentRepository.save(document);

    // Send notifications to all validation step assignees
    this.sendWorkflowStartNotifications(workflow, savedExecution, initiatorUserId).catch((err) => {
      this.logger.error(`Failed to send workflow start notifications: ${err.message}`);
    });

    return {
      executionId: savedExecution.id,
      workflowId,
      documentId,
      currentStep: 1,
      status: 'in_progress',
      steps: workflow.steps,
    };
  }

  private generateInviteToken(userId: string, workflowId: string, executionId: string): string {
    const secret =
      this.configService.get<string>('JWT_SECRET') || 'your-secret-key-change-in-production';
    return this.jwtService.sign(
      {
        aud: 'invite',
        userId,
        workflowId,
        executionId,
      },
      { secret, expiresIn: '30d' }
    );
  }

  private async sendWorkflowStartNotifications(
    workflow: Workflow,
    execution: WorkflowExecution,
    initiatorUserId?: string
  ) {
    // Resolve the initiator's name
    let initiatorName = 'un utilisateur';
    if (initiatorUserId) {
      const initiator = await this.userRepository.findOne({
        where: { id: initiatorUserId },
        select: ['id', 'fullName', 'username', 'email'],
      });
      if (initiator) {
        initiatorName = initiator.fullName || initiator.username || initiator.email;
      }
    }

    const frontendUrl = this.configService.get<string>('FRONTEND_URL') || 'http://localhost:5173';

    // Get all unique assignee IDs from workflow steps
    const assigneeIds = [
      ...new Set(workflow.steps.filter((step) => step.assigneeId).map((step) => step.assigneeId)),
    ];

    if (assigneeIds.length === 0) return;

    // Resolve all assignee users
    const assignees = await this.userRepository.find({
      where: assigneeIds.map((id) => ({ id })),
      select: ['id', 'email', 'fullName', 'username'],
    });

    for (const assignee of assignees) {
      const stepForUser = workflow.steps.find((s) => s.assigneeId === assignee.id);
      const isSignatureStep =
        stepForUser?.requiresSignature ||
        stepForUser?.description?.toLowerCase().includes('signature');
      const actionType = isSignatureStep ? 'signature' : 'validation';
      const actionLabel = isSignatureStep ? 'signature' : 'validation';

      // Generate invite token
      const token = this.generateInviteToken(assignee.id, workflow.id, execution.id);
      const inviteUrl = `${frontendUrl}/invite?token=${token}`;

      // 1. In-app notification
      await this.notificationsService.createNotification({
        recipientId: assignee.id,
        title: `Demande de ${actionLabel}`,
        message: `Vous venez de recevoir une demande de ${actionLabel} de la part de ${initiatorName}.`,
        type: actionType as 'validation' | 'signature',
        workflowId: workflow.id,
        executionId: execution.id,
        actionUrl: `/workflows`,
      });

      // 2. Email notification
      const emailSubject = `E-Parapheur - Demande de ${actionLabel}`;
      const emailText = `Bonjour,\n\nVous venez de recevoir une demande de ${actionLabel} de la part de ${initiatorName}.\n\nAfin de ${actionLabel === 'validation' ? 'valider' : 'signer'} le(s) document(s) en question, cliquez sur l'adresse ci-dessous ou recopiez la dans la barre d'adresse de votre navigateur :\n\n${inviteUrl}\n\nCordialement,\nE-Parapheur`;
      const emailHtml = `
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
          <div style="background: #2453d6; padding: 20px; text-align: center;">
            <h1 style="color: #fff; margin: 0; font-size: 22px;">E-Parapheur</h1>
          </div>
          <div style="padding: 30px 20px; background: #f9fafb;">
            <p style="font-size: 16px; color: #333;">Bonjour,</p>
            <p style="font-size: 15px; color: #333; line-height: 1.6;">
              Vous venez de recevoir une <strong>demande de ${actionLabel}</strong> de la part de <strong>${initiatorName}</strong>.
            </p>
            <p style="font-size: 15px; color: #333; line-height: 1.6;">
              Afin de ${actionLabel === 'validation' ? 'valider' : 'signer'} le(s) document(s) en question, cliquez sur le bouton ci-dessous ou recopiez l'adresse dans la barre d'adresse de votre navigateur.
            </p>
            <div style="text-align: center; margin: 30px 0;">
              <a href="${inviteUrl}" style="display: inline-block; background: #2453d6; color: #fff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 15px;">
                ${actionLabel === 'validation' ? 'Valider le document' : 'Signer le document'}
              </a>
            </div>
            <p style="font-size: 12px; color: #999; word-break: break-all;">
              ${inviteUrl}
            </p>
          </div>
          <div style="padding: 15px 20px; text-align: center; background: #eee;">
            <p style="font-size: 12px; color: #666; margin: 0;">Cordialement, E-Parapheur</p>
          </div>
        </div>`;

      this.notificationsService
        .sendEmailNotification({
          to: assignee.email,
          subject: emailSubject,
          text: emailText,
          html: emailHtml,
        })
        .then((result) => {
          if (!result.sent) {
            this.logger.warn(`Email notification not sent to ${assignee.email}: ${result.reason}`);
          }
        })
        .catch((err) => {
          this.logger.error(`Email notification error for ${assignee.email}: ${err.message}`);
        });
    }
  }

  async getExecution(executionId: string) {
    const execution = await this.workflowExecutionRepository.findOne({
      where: { id: executionId },
      relations: ['workflow', 'workflow.steps', 'document'],
    });

    if (!execution) {
      throw new NotFoundException('Workflow execution not found');
    }

    return execution;
  }

  async advanceWorkflowStep(executionId: string, stepData: Record<string, any>) {
    const execution = await this.getExecution(executionId);

    execution.stepData = { ...execution.stepData, [execution.currentStep]: stepData };
    execution.currentStep += 1;

    // Check if workflow is complete
    const totalSteps = execution.workflow.steps.length;
    if (execution.currentStep > totalSteps) {
      execution.status = 'completed';
      execution.completedAt = new Date();

      // Update document status
      const document = await this.documentRepository.findOne({
        where: { id: execution.documentId },
      });
      if (document) {
        document.status = 'signed';
        await this.documentRepository.save(document);
      }
    }

    return await this.workflowExecutionRepository.save(execution);
  }

  async rejectWorkflow(executionId: string, reason?: string) {
    const execution = await this.getExecution(executionId);

    execution.status = 'rejected';
    execution.stepData = { ...execution.stepData, rejectionReason: reason || 'Rejected' };

    return await this.workflowExecutionRepository.save(execution);
  }
}
