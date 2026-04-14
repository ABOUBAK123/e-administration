import {
  BadRequestException,
  ForbiddenException,
  Injectable,
  NotFoundException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { In, QueryFailedError, Repository } from 'typeorm';
import {
  CreateAdministrationProfileDto,
  CreateAdministrationUserDto,
  CreateDirectionTypeDto,
  CreateIssuingAdministrationDto,
  CreateRecipientAdministrationDto,
  CreateRoutingRuleDto,
  CreateTemplateDto,
  GenerateTemplateDocumentDto,
  CreateTemplateVariableDto,
  UpdateIssuingAdministrationDto,
  UpdateAdministrationProfileDto,
  UpdateAdministrationUserDto,
  UpdateDirectionTypeDto,
  UpdateRecipientAdministrationDto,
  UpdateRoutingRuleDto,
  UpdateTemplateDto,
  UpdateTemplateVariableDto,
  UpsertAppSettingDto,
  CreateRequestedActDto,
  UpdateRequestedActDto,
  UpsertNotificationConfigDto,
  UpsertSignatureProviderConfigDto,
} from './dto/administration.dto';
import { IssuingAdministration } from './entities/issuing-administration.entity';
import { AdministrationProfile } from './entities/administration-profile.entity';
import { AdministrationUser } from './entities/administration-user.entity';
import { DocumentTemplate } from './entities/template.entity';
import { TemplateVariable } from './entities/template-variable.entity';
import { RecipientAdministration } from './entities/recipient-administration.entity';
import { RoutingRule } from './entities/routing-rule.entity';
import { SignatureProviderConfig } from './entities/signature-provider-config.entity';
import { NotificationConfig } from './entities/notification-config.entity';
import { DirectionType } from './entities/direction-type.entity';
import { AppSetting } from './entities/app-setting.entity';
import { RequestedAct } from './entities/requested-act.entity';
import { User } from '../users/user.entity';
import { UserDirectionAssignment } from '../users/user-direction-assignment.entity';

const DEFAULT_DIRECTION_TYPES = [
  'Direction Générale',
  'Direction Centrale',
  'Direction Régionale',
  'Service',
  'Division',
  'Bureau',
];

@Injectable()
export class AdministrationService {
  constructor(
    @InjectRepository(IssuingAdministration)
    private issuingAdministrationRepository: Repository<IssuingAdministration>,
    @InjectRepository(AdministrationProfile)
    private administrationProfileRepository: Repository<AdministrationProfile>,
    @InjectRepository(AdministrationUser)
    private administrationUserRepository: Repository<AdministrationUser>,
    @InjectRepository(DocumentTemplate)
    private templateRepository: Repository<DocumentTemplate>,
    @InjectRepository(TemplateVariable)
    private templateVariableRepository: Repository<TemplateVariable>,
    @InjectRepository(RecipientAdministration)
    private recipientAdministrationRepository: Repository<RecipientAdministration>,
    @InjectRepository(RoutingRule)
    private routingRuleRepository: Repository<RoutingRule>,
    @InjectRepository(SignatureProviderConfig)
    private signatureProviderConfigRepository: Repository<SignatureProviderConfig>,
    @InjectRepository(NotificationConfig)
    private notificationConfigRepository: Repository<NotificationConfig>,
    @InjectRepository(DirectionType)
    private directionTypeRepository: Repository<DirectionType>,
    @InjectRepository(AppSetting)
    private appSettingRepository: Repository<AppSetting>,
    @InjectRepository(RequestedAct)
    private requestedActRepository: Repository<RequestedAct>,
    @InjectRepository(User)
    private userRepository: Repository<User>,
    @InjectRepository(UserDirectionAssignment)
    private userDirectionAssignmentRepository: Repository<UserDirectionAssignment>
  ) {}

  private isDuplicateEntryError(error: unknown): boolean {
    if (!(error instanceof QueryFailedError)) return false;
    const driverError = (error as any).driverError || {};
    const code = String(driverError.code || '').toUpperCase();
    const message = String(driverError.sqlMessage || driverError.message || '').toLowerCase();
    return (
      code === 'ER_DUP_ENTRY' ||
      message.includes('duplicate entry') ||
      message.includes('unique constraint')
    );
  }

  private isMissingDirectionAssignmentStorage(error: unknown): boolean {
    if (!(error instanceof QueryFailedError)) return false;
    const driverError = (error as any).driverError || {};
    const code = String(driverError.code || '').toUpperCase();
    const message = String(driverError.sqlMessage || driverError.message || '').toLowerCase();
    return (
      code === 'ER_NO_SUCH_TABLE' ||
      code === 'ER_BAD_FIELD_ERROR' ||
      message.includes("doesn't exist") ||
      message.includes('no such table') ||
      message.includes('unknown column') ||
      (message.includes('relation') && message.includes('does not exist'))
    );
  }

  private normalizeSubEntityCode(value?: string | null): string {
    return String(value || '')
      .trim()
      .toUpperCase();
  }

  private normalizeSubEntities(
    raw: unknown
  ): Array<{ code: string; managerEmail?: string; managerName?: string }> {
    if (!Array.isArray(raw)) return [];

    return raw
      .map((item) => {
        const data = item as Record<string, unknown>;
        const code = this.normalizeSubEntityCode(String(data.code || ''));
        if (!code) return null;
        return {
          code,
          managerEmail:
            String(data.managerEmail || data.responsableEmail || '')
              .trim()
              .toLowerCase() || undefined,
          managerName:
            String(data.managerName || data.responsableName || '')
              .trim()
              .toLowerCase() || undefined,
        };
      })
      .filter(Boolean) as Array<{ code: string; managerEmail?: string; managerName?: string }>;
  }

  private extractSubEntityCodesFromProfilePermissions(permissions: unknown): string[] {
    if (!permissions || typeof permissions !== 'object') return [];

    const source = permissions as Record<string, unknown>;
    const collect = (value: unknown): string[] => {
      if (Array.isArray(value)) {
        return value.map((item) => this.normalizeSubEntityCode(String(item || ''))).filter(Boolean);
      }
      if (typeof value === 'string') {
        const normalized = this.normalizeSubEntityCode(value);
        return normalized ? [normalized] : [];
      }
      return [];
    };

    return Array.from(
      new Set([
        ...collect(source.subEntityCode),
        ...collect(source.subEntityCodes),
        ...collect((source as any).entityCode),
        ...collect((source as any).entityCodes),
      ])
    );
  }

  private normalizeApplicantFields(raw: unknown): Array<{
    label: string;
    inputType: 'text' | 'date' | 'number' | 'phone' | 'email' | 'textarea';
  }> {
    if (!Array.isArray(raw)) return [];

    const allowedTypes = new Set(['text', 'date', 'number', 'phone', 'email', 'textarea']);
    const seen = new Set<string>();

    return raw
      .map((item) => {
        const data = item as Record<string, unknown>;
        const label = String(data?.label || '').trim();
        const inputType = String(data?.inputType || 'text')
          .trim()
          .toLowerCase();
        if (!label || !allowedTypes.has(inputType)) return null;
        const key = `${label.toLowerCase()}::${inputType}`;
        if (seen.has(key)) return null;
        seen.add(key);
        return {
          label,
          inputType: inputType as 'text' | 'date' | 'number' | 'phone' | 'email' | 'textarea',
        };
      })
      .filter(Boolean) as Array<{
      label: string;
      inputType: 'text' | 'date' | 'number' | 'phone' | 'email' | 'textarea';
    }>;
  }

  private async resolveRequestedActAccessContext(userId?: string | null): Promise<{
    isElevated: boolean;
    administrationId: string | null;
    allowedSubEntityCodes: string[];
  }> {
    const normalizedUserId = String(userId || '').trim();
    if (!normalizedUserId) {
      return { isElevated: true, administrationId: null, allowedSubEntityCodes: [] };
    }

    const user = await this.userRepository.findOne({ where: { id: normalizedUserId } });
    if (!user) {
      throw new NotFoundException('Utilisateur introuvable.');
    }

    const normalizedUserRole = String(user.role || '')
      .trim()
      .toLowerCase()
      .replace(/[-\s]+/g, '_');
    if (
      normalizedUserRole === 'admin' ||
      normalizedUserRole === 'super_admin' ||
      normalizedUserRole === 'superadmin'
    ) {
      return { isElevated: true, administrationId: null, allowedSubEntityCodes: [] };
    }

    const normalizedEmail = (user.email || '').trim().toLowerCase();
    const normalizedUsername = (user.username || '').trim().toLowerCase();

    const administrationUser = await this.administrationUserRepository
      .createQueryBuilder('administrationUser')
      .leftJoinAndSelect('administrationUser.profile', 'profile')
      .where('LOWER(administrationUser.email) = :email', { email: normalizedEmail })
      .orWhere('LOWER(administrationUser.username) = :username', { username: normalizedUsername })
      .orderBy('administrationUser.updatedAt', 'DESC')
      .getOne();

    let directionAssignment: UserDirectionAssignment | null = null;
    try {
      directionAssignment = await this.userDirectionAssignmentRepository.findOne({
        where: { userId: user.id },
      });
    } catch (error) {
      if (this.isMissingDirectionAssignmentStorage(error)) {
        throw new ForbiddenException(
          'Affectation stricte indisponible: veuillez appliquer les migrations de la base de données.'
        );
      }
      throw error;
    }

    let administrationId = String(administrationUser?.administrationId || '').trim();

    const assignmentScopeType = String(directionAssignment?.directionScopeType || '')
      .trim()
      .toLowerCase();
    const assignmentScopeId = String(directionAssignment?.directionScopeId || '').trim();
    if (!administrationId && assignmentScopeType === 'emitter' && assignmentScopeId) {
      administrationId = assignmentScopeId;
    }

    if (!administrationId) {
      throw new ForbiddenException('Aucune administration liée à cet utilisateur.');
    }

    const assignedSubEntityCode = this.normalizeSubEntityCode(
      (directionAssignment as any)?.subEntityCode || null
    );

    const assignmentMatchesAdministration =
      assignmentScopeType === 'emitter' && assignmentScopeId === administrationId;

    if (assignmentMatchesAdministration && assignedSubEntityCode) {
      return {
        isElevated: false,
        administrationId,
        allowedSubEntityCodes: [assignedSubEntityCode],
      };
    }

    // Fallback when strict assignment is absent/incomplete:
    // use profile-level scope if available, otherwise allow full administration scope.
    const profilePermissions = (administrationUser?.profile?.permissions || null) as unknown;
    const profileSubEntities = this.extractSubEntityCodesFromProfilePermissions(profilePermissions);

    return {
      isElevated: false,
      administrationId,
      allowedSubEntityCodes: profileSubEntities,
    };
  }

  private isRequestedActDirectionAllowed(
    access: { isElevated: boolean; allowedSubEntityCodes: string[] },
    directionCode: string
  ): boolean {
    if (access.isElevated) return true;
    if (access.allowedSubEntityCodes.length === 0) return true;
    return access.allowedSubEntityCodes.includes(this.normalizeSubEntityCode(directionCode));
  }

  async findRequestedActs(userId?: string | null) {
    const access = await this.resolveRequestedActAccessContext(userId);

    if (access.isElevated) {
      return this.requestedActRepository.find({
        order: { createdAt: 'DESC' },
      });
    }

    if (!access.administrationId) {
      return [];
    }

    const where =
      access.allowedSubEntityCodes.length > 0
        ? {
            administrationScopeType: 'emitter' as const,
            administrationScopeId: access.administrationId,
            directionCode: In(access.allowedSubEntityCodes),
          }
        : {
            administrationScopeType: 'emitter' as const,
            administrationScopeId: access.administrationId,
          };

    return this.requestedActRepository.find({
      where,
      order: { createdAt: 'DESC' },
    });
  }

  async createRequestedAct(
    dto: CreateRequestedActDto | Record<string, unknown>,
    userId?: string | null
  ) {
    const payload = dto as Record<string, unknown>;

    const scopeType = String(payload.administrationScopeType || '')
      .trim()
      .toLowerCase();
    if (scopeType !== 'emitter' && scopeType !== 'recipient') {
      throw new BadRequestException('administrationScopeType must be emitter or recipient');
    }

    const administrationScopeId = String(payload.administrationScopeId || '').trim();
    if (!administrationScopeId) {
      throw new BadRequestException('administrationScopeId is required');
    }

    if (scopeType === 'emitter') {
      const found = await this.issuingAdministrationRepository.findOne({
        where: { id: administrationScopeId },
      });
      if (!found) {
        throw new NotFoundException('Issuing administration not found');
      }
    } else {
      const found = await this.recipientAdministrationRepository.findOne({
        where: { id: administrationScopeId },
      });
      if (!found) {
        throw new NotFoundException('Recipient administration not found');
      }
    }

    const requiredDocuments = Array.from(
      new Set(
        (Array.isArray(payload.requiredDocuments) ? payload.requiredDocuments : [])
          .map((item) => String(item || '').trim())
          .filter(Boolean)
      )
    );

    if (requiredDocuments.length === 0) {
      throw new BadRequestException('requiredDocuments must contain at least one item');
    }

    const applicantFields = this.normalizeApplicantFields(payload.applicantFields);

    const access = await this.resolveRequestedActAccessContext(userId);
    const normalizedDirectionCode = this.normalizeSubEntityCode(
      String(payload.directionCode || '')
    );
    if (!access.isElevated) {
      if (
        !access.administrationId ||
        administrationScopeId !== access.administrationId ||
        scopeType !== 'emitter'
      ) {
        throw new ForbiddenException(
          'Vous ne pouvez configurer des actes demandés que pour votre administration émettrice.'
        );
      }

      if (!this.isRequestedActDirectionAllowed(access, normalizedDirectionCode)) {
        throw new ForbiddenException(
          'Vous ne pouvez configurer que les actes liés à vos entités sous tutelle gérées.'
        );
      }
    }

    const entity = this.requestedActRepository.create({
      administrationScopeType: scopeType as 'emitter' | 'recipient',
      administrationScopeId,
      administrationLabel: String(payload.administrationLabel || '').trim(),
      directionCode: normalizedDirectionCode,
      directionLabel: String(payload.directionLabel || '').trim(),
      documentName: String(payload.documentName || '').trim(),
      requiredDocuments,
      applicantFields,
      createdBy: userId || null,
    });

    if (
      !entity.directionCode ||
      !entity.documentName ||
      !entity.administrationLabel ||
      !entity.directionLabel
    ) {
      throw new BadRequestException('Missing required fields for requested act');
    }

    return this.requestedActRepository.save(entity);
  }

  async updateRequestedAct(
    id: string,
    dto: UpdateRequestedActDto | Record<string, unknown>,
    userId?: string | null
  ) {
    const existing = await this.requestedActRepository.findOne({ where: { id } });
    if (!existing) {
      throw new NotFoundException('Requested act not found');
    }

    const payload = dto as Record<string, unknown>;

    const scopeType = String(
      payload.administrationScopeType || existing.administrationScopeType || ''
    )
      .trim()
      .toLowerCase();
    if (scopeType !== 'emitter' && scopeType !== 'recipient') {
      throw new BadRequestException('administrationScopeType must be emitter or recipient');
    }

    const administrationScopeId = String(
      payload.administrationScopeId || existing.administrationScopeId || ''
    ).trim();
    if (!administrationScopeId) {
      throw new BadRequestException('administrationScopeId is required');
    }

    if (scopeType === 'emitter') {
      const found = await this.issuingAdministrationRepository.findOne({
        where: { id: administrationScopeId },
      });
      if (!found) {
        throw new NotFoundException('Issuing administration not found');
      }
    } else {
      const found = await this.recipientAdministrationRepository.findOne({
        where: { id: administrationScopeId },
      });
      if (!found) {
        throw new NotFoundException('Recipient administration not found');
      }
    }

    const requiredDocuments = Array.from(
      new Set(
        (
          (Array.isArray(payload.requiredDocuments) && payload.requiredDocuments.length > 0
            ? payload.requiredDocuments
            : existing.requiredDocuments || []) as unknown[]
        )
          .map((item) => String(item || '').trim())
          .filter(Boolean)
      )
    );

    const safeRequiredDocuments =
      requiredDocuments.length > 0
        ? requiredDocuments
        : Array.isArray(existing.requiredDocuments)
          ? existing.requiredDocuments
          : [];

    const applicantFields = Object.prototype.hasOwnProperty.call(payload, 'applicantFields')
      ? this.normalizeApplicantFields(payload.applicantFields)
      : Array.isArray(existing.applicantFields)
        ? existing.applicantFields
        : [];

    const access = await this.resolveRequestedActAccessContext(userId);
    const normalizedDirectionCode = this.normalizeSubEntityCode(
      String(payload.directionCode || existing.directionCode || '')
    );
    if (!access.isElevated) {
      if (
        !access.administrationId ||
        administrationScopeId !== access.administrationId ||
        scopeType !== 'emitter'
      ) {
        throw new ForbiddenException(
          'Vous ne pouvez modifier que les actes demandés de votre administration émettrice.'
        );
      }

      if (!this.isRequestedActDirectionAllowed(access, normalizedDirectionCode)) {
        throw new ForbiddenException(
          'Vous ne pouvez modifier que les actes liés à vos entités sous tutelle gérées.'
        );
      }
    }

    existing.administrationScopeType = scopeType as 'emitter' | 'recipient';
    existing.administrationScopeId = administrationScopeId;
    existing.administrationLabel = String(
      payload.administrationLabel || existing.administrationLabel || ''
    ).trim();
    existing.directionCode = normalizedDirectionCode;
    existing.directionLabel = String(
      payload.directionLabel || existing.directionLabel || ''
    ).trim();
    existing.documentName = String(payload.documentName || existing.documentName || '').trim();
    existing.requiredDocuments = safeRequiredDocuments;
    existing.applicantFields = applicantFields;

    if (
      !existing.directionCode ||
      !existing.documentName ||
      !existing.administrationLabel ||
      !existing.directionLabel
    ) {
      throw new BadRequestException('Missing required fields for requested act');
    }

    return this.requestedActRepository.save(existing);
  }

  async deleteRequestedAct(id: string, userId?: string | null) {
    const found = await this.requestedActRepository.findOne({ where: { id } });
    if (!found) {
      throw new NotFoundException('Requested act not found');
    }

    const access = await this.resolveRequestedActAccessContext(userId);
    if (!access.isElevated) {
      const sameAdministration =
        found.administrationScopeType === 'emitter' &&
        found.administrationScopeId === access.administrationId;
      const directionAllowed = this.isRequestedActDirectionAllowed(access, found.directionCode);
      if (!sameAdministration || !directionAllowed) {
        throw new ForbiddenException(
          'Vous ne pouvez supprimer que les actes de vos entités sous tutelle gérées.'
        );
      }
    }

    await this.requestedActRepository.remove(found);
    return { message: 'Requested act deleted successfully' };
  }

  private async ensureDefaultDirectionTypes() {
    const existingCount = await this.directionTypeRepository.count();
    if (existingCount > 0) {
      return;
    }

    const defaults = DEFAULT_DIRECTION_TYPES.map((name) =>
      this.directionTypeRepository.create({ name, description: null })
    );
    await this.directionTypeRepository.save(defaults);
  }

  async findDirectionTypes() {
    await this.ensureDefaultDirectionTypes();
    return this.directionTypeRepository.find({ order: { name: 'ASC', createdAt: 'ASC' } });
  }

  async createDirectionType(dto: CreateDirectionTypeDto) {
    await this.ensureDefaultDirectionTypes();
    const name = dto.name.trim();
    const existing = await this.directionTypeRepository
      .createQueryBuilder('directionType')
      .where('LOWER(directionType.name) = LOWER(:name)', { name })
      .getOne();

    if (existing) {
      throw new BadRequestException('A direction type with this name already exists');
    }

    const directionType = this.directionTypeRepository.create({
      name,
      description: dto.description?.trim() || null,
    });
    return this.directionTypeRepository.save(directionType);
  }

  async updateDirectionType(id: string, dto: UpdateDirectionTypeDto) {
    const directionType = await this.directionTypeRepository.findOne({ where: { id } });
    if (!directionType) {
      throw new NotFoundException('Direction type not found');
    }

    if (dto.name !== undefined) {
      const name = dto.name.trim();
      const existing = await this.directionTypeRepository
        .createQueryBuilder('directionType')
        .where('LOWER(directionType.name) = LOWER(:name)', { name })
        .andWhere('directionType.id != :id', { id })
        .getOne();

      if (existing) {
        throw new BadRequestException('A direction type with this name already exists');
      }

      directionType.name = name;
    }

    if (dto.description !== undefined) {
      directionType.description = dto.description.trim() || null;
    }

    return this.directionTypeRepository.save(directionType);
  }

  async deleteDirectionType(id: string) {
    const directionType = await this.directionTypeRepository.findOne({ where: { id } });
    if (!directionType) {
      throw new NotFoundException('Direction type not found');
    }

    await this.directionTypeRepository.remove(directionType);
    return { message: 'Direction type deleted successfully' };
  }

  // ---------------------------------------------------------------------------
  // App Settings (key-value global config: chat, OnlyOffice, etc.)
  // ---------------------------------------------------------------------------

  async getAppSettings(keys?: string[]): Promise<AppSetting[]> {
    if (keys && keys.length > 0) {
      return this.appSettingRepository
        .createQueryBuilder('s')
        .where('s.key IN (:...keys)', { keys })
        .getMany();
    }
    return this.appSettingRepository.find({ order: { key: 'ASC' } });
  }

  async getAppSetting(key: string): Promise<AppSetting | null> {
    return this.appSettingRepository.findOne({ where: { key } });
  }

  async upsertAppSetting(key: string, dto: UpsertAppSettingDto): Promise<AppSetting> {
    let setting = await this.appSettingRepository.findOne({ where: { key } });
    if (!setting) {
      setting = this.appSettingRepository.create({ key });
    }
    if (dto.value !== undefined) setting.value = dto.value ?? null;
    if (dto.description !== undefined) setting.description = dto.description ?? null;
    return this.appSettingRepository.save(setting);
  }

  async upsertAppSettings(
    entries: Array<{ key: string; value: string | null; description?: string }>
  ): Promise<AppSetting[]> {
    const results: AppSetting[] = [];
    for (const entry of entries) {
      const saved = await this.upsertAppSetting(entry.key, {
        value: entry.value ?? undefined,
        description: entry.description,
      });
      results.push(saved);
    }
    return results;
  }

  async getSignatureProviderConfig(administrationId?: string) {
    let config: SignatureProviderConfig | undefined;

    if (administrationId) {
      // Get config for specific administration
      [config] = await this.signatureProviderConfigRepository.find({
        where: { administrationId },
        order: { createdAt: 'ASC' },
        take: 1,
      });
    } else {
      // Get global config (backward compatibility)
      [config] = await this.signatureProviderConfigRepository.find({
        where: { administrationId: null as any },
        order: { createdAt: 'ASC' },
        take: 1,
      });
    }

    if (config) {
      return config;
    }

    // Create new config
    const created = this.signatureProviderConfigRepository.create({
      administrationId: administrationId || null,
      isActive: false,
      endpoint: null,
      signPath: null,
      apiKey: null,
      consentPageId: null,
      signatureProfileId: null,
      providerOwnerUserId: null,
      verifySsl: true,
      timeoutMs: 30000,
      metadata: null,
    });
    return this.signatureProviderConfigRepository.save(created);
  }

  async upsertSignatureProviderConfig(dto: UpsertSignatureProviderConfigDto) {
    const config = await this.getSignatureProviderConfig(dto.administrationId);

    if (dto.administrationId && !config.administrationId) {
      // If requesting admin-specific but got global, validate admin exists
      const administration = await this.issuingAdministrationRepository.findOne({
        where: { id: dto.administrationId },
      });
      if (!administration) {
        throw new NotFoundException('Issuing administration not found');
      }
    }

    Object.assign(config, {
      ...dto,
      endpoint: dto.endpoint !== undefined ? dto.endpoint.trim() : config.endpoint,
      signPath: dto.signPath !== undefined ? dto.signPath.trim() : config.signPath,
      apiKey: dto.apiKey !== undefined ? dto.apiKey.trim() : config.apiKey,
      consentPageId:
        dto.consentPageId !== undefined ? dto.consentPageId.trim() : config.consentPageId,
      signatureProfileId:
        dto.signatureProfileId !== undefined
          ? dto.signatureProfileId.trim()
          : config.signatureProfileId,
      providerOwnerUserId:
        dto.providerOwnerUserId !== undefined
          ? dto.providerOwnerUserId.trim()
          : config.providerOwnerUserId,
      administrationId: dto.administrationId || config.administrationId,
    });

    return this.signatureProviderConfigRepository.save(config);
  }

  async getNotificationConfigByAdministration(administrationId: string) {
    const administration = await this.issuingAdministrationRepository.findOne({
      where: { id: administrationId },
    });
    if (!administration) {
      throw new NotFoundException('Issuing administration not found');
    }

    let config = await this.notificationConfigRepository.findOne({
      where: { administrationId },
    });

    if (!config) {
      config = this.notificationConfigRepository.create({
        administrationId,
        isActive: true,
        smtpHost: null,
        smtpPort: 587,
        smtpSecure: false,
        smtpUser: null,
        smtpPassword: null,
        smtpFrom: null,
        triggers: {
          onDocumentShared: true,
          onSignatureRequested: true,
          onSignatureResponded: true,
          onWorkflowAssigned: true,
          onWorkflowStepCompleted: true,
          onDocumentUploaded: false,
          onUserCreated: false,
        },
      });
      config = await this.notificationConfigRepository.save(config);
    }

    return config;
  }

  async upsertNotificationConfigByAdministration(
    administrationId: string,
    dto: UpsertNotificationConfigDto
  ) {
    const config = await this.getNotificationConfigByAdministration(administrationId);

    Object.assign(config, {
      isActive: dto.isActive ?? config.isActive,
      smtpHost: dto.smtpHost !== undefined ? dto.smtpHost.trim() || null : config.smtpHost,
      smtpPort: dto.smtpPort ?? config.smtpPort,
      smtpSecure: dto.smtpSecure ?? config.smtpSecure,
      smtpUser: dto.smtpUser !== undefined ? dto.smtpUser.trim() || null : config.smtpUser,
      smtpPassword:
        dto.smtpPassword !== undefined ? dto.smtpPassword.trim() || null : config.smtpPassword,
      smtpFrom: dto.smtpFrom !== undefined ? dto.smtpFrom.trim() || null : config.smtpFrom,
      triggers: dto.triggers ?? config.triggers,
    });

    return this.notificationConfigRepository.save(config);
  }

  async findTemplates() {
    return this.templateRepository.find({
      relations: ['variables', 'administration'],
      order: { createdAt: 'DESC' },
    });
  }

  async createTemplate(dto: CreateTemplateDto, createdBy?: string) {
    const template = this.templateRepository.create({ ...dto, createdBy });
    return this.templateRepository.save(template);
  }

  async updateTemplate(id: string, dto: UpdateTemplateDto) {
    const template = await this.templateRepository.findOne({ where: { id } });
    if (!template) {
      throw new NotFoundException('Template not found');
    }
    Object.assign(template, dto);
    return this.templateRepository.save(template);
  }

  async deleteTemplate(id: string) {
    const template = await this.templateRepository.findOne({ where: { id } });
    if (!template) {
      throw new NotFoundException('Template not found');
    }
    await this.templateRepository.remove(template);
    return { message: 'Template deleted successfully' };
  }

  async generateTemplateDocument(id: string, dto: GenerateTemplateDocumentDto) {
    const template = await this.templateRepository.findOne({
      where: { id },
      relations: ['variables'],
    });
    if (!template) {
      throw new NotFoundException('Template not found');
    }

    const templateContent = template.content || '';
    if (!templateContent.trim()) {
      throw new BadRequestException('Template content is empty');
    }

    const values = dto.values || {};
    const requireAllFields = Boolean(dto.requireAllFields);
    const forcePdf = dto.outputFormat === 'pdf';
    const variableConfig = new Map(template.variables.map((variable) => [variable.key, variable]));

    // Regex élargie pour capturer tout contenu entre {{ }} (espaces, accents, apostrophes, etc.)
    const placeholderRegex = /\{\{\s*([^}]+?)\s*\}\}/g;

    const placeholders = Array.from(templateContent.matchAll(placeholderRegex)).map(
      (match) => match[1]
    );
    const uniquePlaceholders = Array.from(new Set(placeholders));

    // Fonction de normalisation : "Nom et Prénoms du Responsable" → "nom_et_prenoms_du_responsable"
    const slugify = (text: string): string =>
      text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/['']/g, '_')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

    // Construire un mapping label → key depuis les variables configurées
    const labelToKey = new Map<string, string>();
    for (const variable of template.variables) {
      labelToKey.set(slugify(variable.label || variable.key), variable.key);
    }

    // Résoudre la clé de valeur pour un placeholder donné
    const resolveKey = (placeholder: string): string => {
      // 1. Essai direct (le placeholder est déjà une clé slug)
      if (values[placeholder] !== undefined) return placeholder;
      // 2. Slugifier le placeholder et chercher dans les valeurs
      const slug = slugify(placeholder);
      if (values[slug] !== undefined) return slug;
      // 3. Chercher via le mapping label → key
      const mappedKey = labelToKey.get(slug);
      if (mappedKey && values[mappedKey] !== undefined) return mappedKey;
      return slug;
    };

    for (const placeholder of uniquePlaceholders) {
      const key = resolveKey(placeholder);
      const config = variableConfig.get(key);
      if (requireAllFields) {
        const rawValue = values[key];
        const hasValue = typeof rawValue === 'string' && rawValue.trim().length > 0;
        if (!hasValue) {
          throw new BadRequestException(`Missing required value for variable: ${key}`);
        }
      }
      if (config?.required) {
        const rawValue = values[key];
        const hasValue = typeof rawValue === 'string' && rawValue.trim().length > 0;
        if (!hasValue && !(config.defaultValue && config.defaultValue.trim().length > 0)) {
          throw new BadRequestException(`Missing required value for variable: ${key}`);
        }
      }
    }

    const generatedContent = templateContent.replace(
      placeholderRegex,
      (_match, placeholder: string) => {
        const key = resolveKey(placeholder);
        const inputValue = values[key];
        if (typeof inputValue === 'string' && inputValue.length > 0) {
          return inputValue;
        }
        const config = variableConfig.get(key);
        if (config?.defaultValue) {
          return config.defaultValue;
        }
        return '';
      }
    );

    const baseName = (template.fileName || template.name || 'document').replace(/\.[^.]+$/, '');
    const requestedName = dto.outputFileName || `${baseName}-genere.txt`;
    const fileName = forcePdf
      ? `${requestedName.replace(/\.[^.]+$/, '')}.pdf`
      : requestedName;

    return {
      templateId: template.id,
      fileName,
      generatedContent,
      variablesUsed: uniquePlaceholders,
    };
  }

  async findTemplateVariables(templateId: string) {
    return this.templateVariableRepository.find({
      where: { templateId },
      order: { createdAt: 'ASC' },
    });
  }

  async createTemplateVariable(templateId: string, dto: CreateTemplateVariableDto) {
    const template = await this.templateRepository.findOne({ where: { id: templateId } });
    if (!template) {
      throw new NotFoundException('Template not found');
    }
    const variable = this.templateVariableRepository.create({ ...dto, templateId });
    return this.templateVariableRepository.save(variable);
  }

  async updateTemplateVariable(
    templateId: string,
    variableId: string,
    dto: UpdateTemplateVariableDto
  ) {
    const variable = await this.templateVariableRepository.findOne({
      where: { id: variableId, templateId },
    });
    if (!variable) {
      throw new NotFoundException('Template variable not found');
    }
    Object.assign(variable, dto);
    return this.templateVariableRepository.save(variable);
  }

  async deleteTemplateVariable(templateId: string, variableId: string) {
    const variable = await this.templateVariableRepository.findOne({
      where: { id: variableId, templateId },
    });
    if (!variable) {
      throw new NotFoundException('Template variable not found');
    }
    await this.templateVariableRepository.remove(variable);
    return { message: 'Template variable deleted successfully' };
  }

  async findIssuingAdministrations() {
    return this.issuingAdministrationRepository.find({
      relations: ['profiles', 'users', 'users.profile'],
      order: { createdAt: 'DESC' },
    });
  }

  async createIssuingAdministration(dto: CreateIssuingAdministrationDto) {
    const administration = this.issuingAdministrationRepository.create({
      ...dto,
      documentNumberPrefix: (dto.documentNumberPrefix || dto.code || 'DOC').toUpperCase(),
      documentNumberPadding: dto.documentNumberPadding ?? 6,
    });
    return this.issuingAdministrationRepository.save(administration);
  }

  async updateAdministrationLogo(id: string, logoPath: string) {
    const administration = await this.issuingAdministrationRepository.findOne({ where: { id } });
    if (!administration) throw new NotFoundException('Issuing administration not found');
    administration.logo = logoPath;
    return this.issuingAdministrationRepository.save(administration);
  }

  async updateIssuingAdministration(id: string, dto: UpdateIssuingAdministrationDto) {
    const administration = await this.issuingAdministrationRepository.findOne({ where: { id } });
    if (!administration) {
      throw new NotFoundException('Issuing administration not found');
    }
    Object.assign(administration, {
      ...dto,
      documentNumberPrefix: dto.documentNumberPrefix
        ? dto.documentNumberPrefix.toUpperCase()
        : administration.documentNumberPrefix,
    });
    return this.issuingAdministrationRepository.save(administration);
  }

  async deleteIssuingAdministration(id: string) {
    const administration = await this.issuingAdministrationRepository.findOne({ where: { id } });
    if (!administration) {
      throw new NotFoundException('Issuing administration not found');
    }
    await this.issuingAdministrationRepository.remove(administration);
    return { message: 'Issuing administration deleted successfully' };
  }

  async createAdministrationProfile(administrationId: string, dto: CreateAdministrationProfileDto) {
    const administration = await this.issuingAdministrationRepository.findOne({
      where: { id: administrationId },
    });
    if (!administration) {
      throw new NotFoundException('Issuing administration not found');
    }
    const profile = this.administrationProfileRepository.create({ ...dto, administrationId });
    return this.administrationProfileRepository.save(profile);
  }

  async updateAdministrationProfile(
    administrationId: string,
    profileId: string,
    dto: UpdateAdministrationProfileDto
  ) {
    const profile = await this.administrationProfileRepository.findOne({
      where: { id: profileId, administrationId },
    });
    if (!profile) {
      throw new NotFoundException('Administration profile not found');
    }
    Object.assign(profile, dto);
    return this.administrationProfileRepository.save(profile);
  }

  async deleteAdministrationProfile(administrationId: string, profileId: string) {
    const profile = await this.administrationProfileRepository.findOne({
      where: { id: profileId, administrationId },
    });
    if (!profile) {
      throw new NotFoundException('Administration profile not found');
    }
    await this.administrationProfileRepository.remove(profile);
    return { message: 'Administration profile deleted successfully' };
  }

  async createAdministrationUser(administrationId: string, dto: CreateAdministrationUserDto) {
    const administration = await this.issuingAdministrationRepository.findOne({
      where: { id: administrationId },
    });
    if (!administration) {
      throw new NotFoundException('Issuing administration not found');
    }
    const user = this.administrationUserRepository.create({ ...dto, administrationId });
    return this.administrationUserRepository.save(user);
  }

  async updateAdministrationUser(
    administrationId: string,
    userId: string,
    dto: UpdateAdministrationUserDto
  ) {
    const user = await this.administrationUserRepository.findOne({
      where: { id: userId, administrationId },
    });
    if (!user) {
      throw new NotFoundException('Administration user not found');
    }

    if (dto.profileId) {
      const profile = await this.administrationProfileRepository.findOne({
        where: { id: dto.profileId, administrationId },
      });
      if (!profile) {
        throw new NotFoundException('Administration profile not found');
      }
    }

    Object.assign(user, dto);
    return this.administrationUserRepository.save(user);
  }

  async deleteAdministrationUser(administrationId: string, userId: string) {
    const user = await this.administrationUserRepository.findOne({
      where: { id: userId, administrationId },
    });
    if (!user) {
      throw new NotFoundException('Administration user not found');
    }
    await this.administrationUserRepository.remove(user);
    return { message: 'Administration user deleted successfully' };
  }

  async findRecipientAdministrations() {
    return this.recipientAdministrationRepository.find({
      order: { createdAt: 'DESC' },
    });
  }

  async createRecipientAdministration(dto: CreateRecipientAdministrationDto) {
    const normalizedName = (dto.name || '').trim();
    const existing = await this.recipientAdministrationRepository
      .createQueryBuilder('recipient')
      .where('LOWER(recipient.name) = LOWER(:name)', { name: normalizedName })
      .getOne();

    if (existing) {
      throw new BadRequestException('Une administration destinataire avec ce nom existe deja.');
    }

    const administration = this.recipientAdministrationRepository.create(dto);
    try {
      return await this.recipientAdministrationRepository.save(administration);
    } catch (error) {
      if (this.isDuplicateEntryError(error)) {
        throw new BadRequestException('Une administration destinataire avec ce nom existe deja.');
      }
      throw error;
    }
  }

  async updateRecipientAdministration(id: string, dto: UpdateRecipientAdministrationDto) {
    const administration = await this.recipientAdministrationRepository.findOne({ where: { id } });
    if (!administration) {
      throw new NotFoundException('Recipient administration not found');
    }

    if (dto.name && dto.name.trim()) {
      const duplicate = await this.recipientAdministrationRepository
        .createQueryBuilder('recipient')
        .where('LOWER(recipient.name) = LOWER(:name)', { name: dto.name.trim() })
        .andWhere('recipient.id != :id', { id })
        .getOne();

      if (duplicate) {
        throw new BadRequestException('Une administration destinataire avec ce nom existe deja.');
      }
    }

    Object.assign(administration, dto);
    try {
      return await this.recipientAdministrationRepository.save(administration);
    } catch (error) {
      if (this.isDuplicateEntryError(error)) {
        throw new BadRequestException('Une administration destinataire avec ce nom existe deja.');
      }
      throw error;
    }
  }

  async updateRecipientAdministrationLogo(id: string, logoPath: string) {
    const administration = await this.recipientAdministrationRepository.findOne({ where: { id } });
    if (!administration) {
      throw new NotFoundException('Recipient administration not found');
    }

    const metadata = (administration.metadata || {}) as Record<string, any>;
    administration.metadata = {
      ...metadata,
      logo: logoPath,
    };

    return this.recipientAdministrationRepository.save(administration);
  }

  async deleteRecipientAdministration(id: string) {
    const administration = await this.recipientAdministrationRepository.findOne({ where: { id } });
    if (!administration) {
      throw new NotFoundException('Recipient administration not found');
    }
    await this.recipientAdministrationRepository.remove(administration);
    return { message: 'Recipient administration deleted successfully' };
  }

  async findRoutingRules() {
    return this.routingRuleRepository.find({
      relations: ['recipientAdministration', 'template'],
      order: { priority: 'ASC', createdAt: 'DESC' },
    });
  }

  async createRoutingRule(dto: CreateRoutingRuleDto) {
    const recipient = await this.recipientAdministrationRepository.findOne({
      where: { id: dto.recipientAdministrationId },
    });
    if (!recipient) {
      throw new NotFoundException('Recipient administration not found');
    }

    if (dto.templateId) {
      const template = await this.templateRepository.findOne({ where: { id: dto.templateId } });
      if (!template) {
        throw new NotFoundException('Template not found');
      }
    }

    const rule = this.routingRuleRepository.create(dto);
    return this.routingRuleRepository.save(rule);
  }

  async updateRoutingRule(id: string, dto: UpdateRoutingRuleDto) {
    const rule = await this.routingRuleRepository.findOne({ where: { id } });
    if (!rule) {
      throw new NotFoundException('Routing rule not found');
    }

    if (dto.recipientAdministrationId) {
      const recipient = await this.recipientAdministrationRepository.findOne({
        where: { id: dto.recipientAdministrationId },
      });
      if (!recipient) {
        throw new NotFoundException('Recipient administration not found');
      }
    }

    if (dto.templateId) {
      const template = await this.templateRepository.findOne({ where: { id: dto.templateId } });
      if (!template) {
        throw new NotFoundException('Template not found');
      }
    }

    Object.assign(rule, dto);
    return this.routingRuleRepository.save(rule);
  }

  async deleteRoutingRule(id: string) {
    const rule = await this.routingRuleRepository.findOne({ where: { id } });
    if (!rule) {
      throw new NotFoundException('Routing rule not found');
    }
    await this.routingRuleRepository.remove(rule);
    return { message: 'Routing rule deleted successfully' };
  }

  // Multi-tenant hierarchy management
  async getAdministrationUsers(administrationId: string, role?: string) {
    const where: any = { administrationId };
    if (role) {
      where.adminRole = role;
    }
    return this.administrationUserRepository.find({
      where,
      relations: ['profile', 'administration'],
      order: { createdAt: 'DESC' },
    });
  }

  async getAdministrationAdmins(administrationId: string) {
    return this.getAdministrationUsers(administrationId, 'admin');
  }

  async getSignatureConfigByAdministration(administrationId: string) {
    return this.signatureProviderConfigRepository.findOne({
      where: { administrationId },
      relations: ['administration'],
    });
  }

  async listAllSignatureConfigs() {
    return this.signatureProviderConfigRepository.find({
      relations: ['administration'],
      order: { createdAt: 'DESC' },
    });
  }
}
