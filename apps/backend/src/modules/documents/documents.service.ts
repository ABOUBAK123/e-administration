import {
  Injectable,
  NotFoundException,
  BadRequestException,
  ForbiddenException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository, Like, In, EntityManager } from 'typeorm';
import { existsSync } from 'fs';
import { join, basename } from 'path';
import { Document } from './document.entity';
import { DocumentVersion } from './document-version.entity';
import { CreateDocumentDto, UpdateDocumentDto, ShareDocumentDto } from './dto/document.dto';
import { User } from '../users/user.entity';
import { NotificationsService } from '../notifications/notifications.service';
import { AdministrationUser } from '../administration/entities/administration-user.entity';
import { AdministrationProfile } from '../administration/entities/administration-profile.entity';
import { RecipientAdministration } from '../administration/entities/recipient-administration.entity';
import { IssuingAdministration } from '../administration/entities/issuing-administration.entity';
import { RequestedAct } from '../administration/entities/requested-act.entity';
import { UserDirectionAssignment } from '../users/user-direction-assignment.entity';
import { QrcodeService } from '../qrcode/qrcode.service';
import { DocumentUserPreference } from './document-user-preference.entity';

@Injectable()
export class DocumentsService {
  constructor(
    @InjectRepository(Document)
    private documentRepository: Repository<Document>,
    @InjectRepository(DocumentVersion)
    private documentVersionRepository: Repository<DocumentVersion>,
    @InjectRepository(DocumentUserPreference)
    private documentUserPreferenceRepository: Repository<DocumentUserPreference>,
    @InjectRepository(User)
    private userRepository: Repository<User>,
    @InjectRepository(AdministrationUser)
    private administrationUserRepository: Repository<AdministrationUser>,
    @InjectRepository(AdministrationProfile)
    private administrationProfileRepository: Repository<AdministrationProfile>,
    @InjectRepository(UserDirectionAssignment)
    private userDirectionAssignmentRepository: Repository<UserDirectionAssignment>,
    @InjectRepository(RecipientAdministration)
    private recipientAdministrationRepository: Repository<RecipientAdministration>,
    @InjectRepository(IssuingAdministration)
    private issuingAdministrationRepository: Repository<IssuingAdministration>,
    @InjectRepository(RequestedAct)
    private requestedActRepository: Repository<RequestedAct>,
    private notificationsService: NotificationsService,
    private qrcodeService: QrcodeService
  ) {}

  private normalizePagination(page?: number | string, limit?: number | string) {
    const parsedPage = Number(page);
    const parsedLimit = Number(limit);

    const safePage = Number.isFinite(parsedPage) && parsedPage > 0 ? Math.floor(parsedPage) : 1;
    const safeLimit =
      Number.isFinite(parsedLimit) && parsedLimit > 0 ? Math.min(Math.floor(parsedLimit), 100) : 20;

    return { page: safePage, limit: safeLimit };
  }

  private parseRecipientShareMeta(description?: string | null): Record<string, unknown> {
    const marker = 'RECIPIENT_SHARE_META::';
    const raw = String(description || '');
    const line = raw.split('\n').find((item) => item.startsWith(marker));
    if (!line) return {};
    try {
      const parsed = JSON.parse(line.slice(marker.length).trim()) as Record<string, unknown>;
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
      return {};
    }
  }

  private upsertRecipientShareMeta(
    previousDescription: string | null | undefined,
    partialMeta: Record<string, unknown>
  ): string {
    const marker = 'RECIPIENT_SHARE_META::';
    const previous = String(previousDescription || '');
    const previousMeta = this.parseRecipientShareMeta(previous);
    const mergedMeta = {
      ...previousMeta,
      ...partialMeta,
    };

    const stripped = previous
      .split('\n')
      .filter((line) => !line.startsWith(marker))
      .join('\n')
      .trim();

    const line = `${marker}${JSON.stringify(mergedMeta)}`;
    return stripped ? `${line}\n${stripped}` : line;
  }

  private async resolveAllowedRecipientAdministrationIds(userId: string): Promise<string[]> {
    const user = await this.userRepository.findOne({ where: { id: userId } });
    if (!user) return [];

    const apiRecipients = await this.recipientAdministrationRepository.find({
      where: { channel: In(['api', 'application']), isActive: true },
      order: { name: 'ASC' },
    });

    const isAdmin = user.role === 'admin';
    let allowedRecipientIds = isAdmin
      ? apiRecipients.map((item) => item.id)
      : apiRecipients
          .filter(
            (item) =>
              (item.emailAddress || '').trim().toLowerCase() === user.email.trim().toLowerCase()
          )
          .map((item) => item.id);

    if (!isAdmin) {
      const assignment = await this.userDirectionAssignmentRepository.findOne({
        where: { userId },
      });
      if (assignment?.directionScopeType === 'recipient' && assignment.directionScopeId) {
        allowedRecipientIds = [assignment.directionScopeId];
      }
    }

    return Array.from(new Set(allowedRecipientIds));
  }

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

  private extractMenuPermissions(permissions: unknown): string[] {
    if (!permissions) return [];

    const canonicalizePermissionToken = (value: unknown): string => {
      const normalized = String(value || '')
        .trim()
        .toLowerCase()
        .replace(/_/g, '-')
        .replace(/\s+/g, '-');

      const plain = normalized
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9.-]+/g, '');

      if (
        plain === 'actrequests' ||
        plain === 'act-requests' ||
        plain === 'demandedactes' ||
        plain === 'demandesdactes'
      ) {
        return 'act-requests';
      }
      if (
        plain === 'act-requests.view' ||
        plain === 'actrequests.view' ||
        plain === 'act-requests-view'
      ) {
        return 'act-requests.view';
      }
      if (
        plain === 'act-requests.process' ||
        plain === 'actrequests.process' ||
        plain === 'act-requests-process'
      ) {
        return 'act-requests.process';
      }
      if (plain === 'receptionview' || plain === 'reception.view' || plain === 'reception-view') {
        return 'reception.view';
      }

      return plain;
    };

    const normalize = (values: unknown[]): string[] =>
      Array.from(new Set(values.map((item) => canonicalizePermissionToken(item)).filter(Boolean)));

    const collectObjectBooleanKeys = (value: unknown): string[] => {
      if (!value || typeof value !== 'object' || Array.isArray(value)) return [];
      return Object.entries(value as Record<string, unknown>)
        .filter(([, item]) => item === true)
        .map(([key]) => key);
    };

    if (Array.isArray(permissions)) {
      return normalize(permissions);
    }

    if (typeof permissions !== 'object') {
      return [];
    }

    const menuPermissions = (permissions as any)?.menuPermissions;
    if (Array.isArray(menuPermissions)) {
      return normalize(menuPermissions);
    }
    if (menuPermissions && typeof menuPermissions === 'object') {
      return normalize(collectObjectBooleanKeys(menuPermissions));
    }

    const nestedPermissions = (permissions as any)?.permissions;
    if (Array.isArray(nestedPermissions)) {
      return normalize(nestedPermissions);
    }
    if (nestedPermissions && typeof nestedPermissions === 'object') {
      return normalize(collectObjectBooleanKeys(nestedPermissions));
    }

    const booleanMapPermissions = Object.entries(permissions as Record<string, unknown>)
      .filter(([, value]) => value === true)
      .map(([key]) => key);

    return normalize(booleanMapPermissions);
  }

  private hasActRequestPermission(menuPermissions: string[]): boolean {
    return (
      menuPermissions.includes('act-requests') ||
      menuPermissions.includes('act-requests.view') ||
      menuPermissions.includes('act-requests.process') ||
      menuPermissions.includes('reception') ||
      menuPermissions.includes('reception.view')
    );
  }

  private hasActRequestProcessPermission(menuPermissions: string[]): boolean {
    return menuPermissions.includes('act-requests.process');
  }

  private normalizeRoleToken(value?: string | null): string {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[-\s]+/g, '_');
  }

  private getDefaultMenuPermissionsByRole(normalizedRole: string): string[] {
    switch (normalizedRole) {
      case 'assistant':
        return ['dashboard', 'templates-shared', 'documents', 'workflows', 'reception', 'qrcode'];
      case 'user':
      case 'manager':
        return [
          'dashboard',
          'templates-shared',
          'documents',
          'workflows',
          'signatures',
          'reception',
          'act-requests',
          'qrcode',
        ];
      case 'signer':
        return [
          'dashboard',
          'templates-shared',
          'documents',
          'signatures',
          'reception',
          'act-requests',
          'qrcode',
        ];
      default:
        return [];
    }
  }

  private isActRequestDocument(document: Document): boolean {
    const normalizedType = String((document as any)?.type || '')
      .trim()
      .toLowerCase();
    if (normalizedType === 'request' || normalizedType === 'demande_acte') {
      return true;
    }

    const title = String(document.title || '').toLowerCase();
    const description = String(document.description || '').toLowerCase();
    return title.includes('demande') || description.includes('demande');
  }

  private normalizeAttachmentName(value: string): string {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, ' ')
      .trim();
  }

  private parseActRequestDescription(description?: string | null): {
    requiredDocuments: string[];
    receivedDocuments: Array<{
      originalName: string;
      storedPath: string;
      requiredDocumentLabel?: string;
    }>;
    applicant: { fullName: string; email: string; phone?: string };
    note: string;
    applicantFieldValues: Record<string, string>;
  } {
    const raw = String(description || '');
    const marker = 'ACT_REQUEST_META::';
    const markerIndex = raw.indexOf(marker);

    if (markerIndex >= 0) {
      const jsonPart = raw.slice(markerIndex + marker.length).trim();
      try {
        const parsed = JSON.parse(jsonPart) as any;
        return {
          requiredDocuments: Array.isArray(parsed?.requiredDocuments)
            ? parsed.requiredDocuments
                .map((item: unknown) => String(item || '').trim())
                .filter(Boolean)
            : [],
          receivedDocuments: Array.isArray(parsed?.receivedDocuments)
            ? parsed.receivedDocuments
                .map((item: any) => ({
                  originalName: String(item?.originalName || '').trim(),
                  storedPath: String(item?.storedPath || '').trim(),
                  requiredDocumentLabel:
                    String(item?.requiredDocumentLabel || '').trim() || undefined,
                }))
                .filter(
                  (item: {
                    originalName: string;
                    storedPath: string;
                    requiredDocumentLabel?: string;
                  }) => item.originalName || item.storedPath
                )
            : [],
          applicant: {
            fullName: String(parsed?.applicant?.fullName || '').trim(),
            email: String(parsed?.applicant?.email || '').trim(),
            phone: String(parsed?.applicant?.phone || '').trim() || undefined,
          },
          note: String(parsed?.note || '').trim(),
          applicantFieldValues:
            parsed?.applicantFieldValues && typeof parsed.applicantFieldValues === 'object'
              ? Object.entries(parsed.applicantFieldValues as Record<string, unknown>).reduce(
                  (acc, [key, value]) => {
                    const label = String(key || '').trim();
                    if (!label) return acc;
                    acc[label] = String(value || '').trim();
                    return acc;
                  },
                  {} as Record<string, string>
                )
              : {},
        };
      } catch {
        // fallback on legacy parser below
      }
    }

    const lines = raw.split('\n').map((line) => line.trim());
    const requiredDocuments: string[] = [];
    const receivedDocuments: Array<{
      originalName: string;
      storedPath: string;
      requiredDocumentLabel?: string;
    }> = [];
    const applicant = { fullName: '', email: '', phone: '' as string | undefined };
    let note = '';
    const applicantFieldValues: Record<string, string> = {};

    const parseBulletLine = (value: string) => value.replace(/^-\s*/, '').trim();
    const lineByPrefix = (prefix: string) =>
      lines.find((line) => line.toLowerCase().startsWith(prefix.toLowerCase()));

    applicant.fullName = lineByPrefix('Nom complet:')?.split(':').slice(1).join(':').trim() || '';
    applicant.email = lineByPrefix('Email:')?.split(':').slice(1).join(':').trim() || '';
    applicant.phone = lineByPrefix('Telephone:')?.split(':').slice(1).join(':').trim() || undefined;

    const applicantFieldsStart = lines.findIndex((line) => line.toLowerCase() === 'champs usager:');
    if (applicantFieldsStart >= 0) {
      for (let i = applicantFieldsStart + 1; i < lines.length; i += 1) {
        const line = lines[i];
        if (!line) break;
        if (!line.startsWith('-')) break;
        const parsed = parseBulletLine(line);
        if (!parsed || parsed.toLowerCase() === 'aucune') continue;
        const [label, ...rest] = parsed.split(':');
        const normalizedLabel = String(label || '').trim();
        const value = rest.join(':').trim();
        if (normalizedLabel) {
          applicantFieldValues[normalizedLabel] = value;
        }
      }
    }

    const requiredStart = lines.findIndex((line) => line.toLowerCase() === 'pieces exigees:');
    if (requiredStart >= 0) {
      for (let i = requiredStart + 1; i < lines.length; i += 1) {
        const line = lines[i];
        if (!line) break;
        if (!line.startsWith('-')) break;
        const parsed = parseBulletLine(line);
        if (parsed && parsed.toLowerCase() !== 'aucune') requiredDocuments.push(parsed);
      }
    }

    const receivedStart = lines.findIndex(
      (line) => line.toLowerCase() === 'pieces jointes usager:'
    );
    if (receivedStart >= 0) {
      for (let i = receivedStart + 1; i < lines.length; i += 1) {
        const line = lines[i];
        if (!line) break;
        if (!line.startsWith('-')) break;
        const parsed = parseBulletLine(line);
        if (!parsed || parsed.toLowerCase() === 'aucune') continue;
        const labelMatch = parsed.match(/^\[(.*?)\]\s*(.*)$/);
        const requiredDocumentLabel = labelMatch?.[1]?.trim() || undefined;
        const filePart = (labelMatch?.[2] || parsed).trim();
        const match = filePart.match(/^(.*?)\s*\((\/uploads\/[^)]+)\)\s*$/);
        if (match) {
          receivedDocuments.push({
            originalName: match[1].trim(),
            storedPath: match[2].trim(),
            requiredDocumentLabel,
          });
        } else {
          receivedDocuments.push({ originalName: filePart, storedPath: '', requiredDocumentLabel });
        }
      }
    }

    const noteStart = lines.findIndex((line) => line.toLowerCase() === 'observation usager:');
    if (noteStart >= 0) {
      note = lines
        .slice(noteStart + 1)
        .join(' ')
        .trim();
      if (note === '-') note = '';
    }

    return {
      requiredDocuments,
      receivedDocuments,
      applicant,
      note,
      applicantFieldValues,
    };
  }

  private async resolveActRequestAccessContext(
    userId: string,
    options?: { requireProcess?: boolean }
  ) {
    const user = await this.userRepository.findOne({ where: { id: userId } });
    if (!user) {
      throw new NotFoundException('Utilisateur introuvable.');
    }

    const isAdmin = user.role === 'admin';
    if (isAdmin) {
      return {
        user,
        isAdmin: true,
        requesterAdministrationId: null as string | null,
        allowedSubEntityCodes: [] as string[],
      };
    }

    const administrationUserProfile = await this.administrationUserRepository
      .createQueryBuilder('administrationUser')
      .leftJoinAndSelect('administrationUser.profile', 'profile')
      .where('LOWER(administrationUser.email) = :email', {
        email: (user.email || '').trim().toLowerCase(),
      })
      .orWhere('LOWER(administrationUser.username) = :username', {
        username: (user.username || '').trim().toLowerCase(),
      })
      .orderBy('administrationUser.updatedAt', 'DESC')
      .getOne();

    let menuPermissions = this.extractMenuPermissions(
      administrationUserProfile?.profile?.permissions
    );
    if (menuPermissions.length === 0) {
      const normalizedRole = this.normalizeRoleToken(user.role);
      const roleProfiles = (await this.administrationProfileRepository.find())
        .filter((profile) => this.normalizeRoleToken(profile.name) === normalizedRole)
        .map((profile) => this.extractMenuPermissions(profile.permissions))
        .filter((items) => items.length > 0)
        .sort((a, b) => b.length - a.length);

      menuPermissions = roleProfiles[0] || this.getDefaultMenuPermissionsByRole(normalizedRole);
    }

    const requireProcess = Boolean(options?.requireProcess);
    if (requireProcess && !this.hasActRequestProcessPermission(menuPermissions)) {
      throw new ForbiddenException(
        "Le droit act-requests.process est requis pour traiter les demandes d'actes."
      );
    }

    if (!requireProcess && !this.hasActRequestPermission(menuPermissions)) {
      throw new ForbiddenException(
        "Le droit act-requests.view est requis pour consulter les demandes d'actes."
      );
    }

    const requesterAdministrationId = await this.resolveUserAdministrationId(user.id);
    if (!requesterAdministrationId) {
      throw new ForbiddenException('Aucune administration associée à cet utilisateur.');
    }

    const managedRecipients = await this.recipientAdministrationRepository.find({
      where: { id: requesterAdministrationId as any },
      take: 1,
    });

    const allowedSubEntityCodes = await this.resolveAuthorizedSubEntityCodes(
      user,
      managedRecipients
    );

    return {
      user,
      isAdmin: false,
      requesterAdministrationId,
      allowedSubEntityCodes,
    };
  }

  private buildActRequestDescription(payload: {
    applicantFullName: string;
    applicantEmail: string;
    applicantPhone?: string;
    note?: string;
    requestedActId: string;
    requestedDocuments: string[];
    attachments: Array<{
      originalName: string;
      storedPath: string;
      requiredDocumentLabel?: string;
    }>;
    applicantFieldValues?: Record<string, string>;
  }): string {
    const prettyList = payload.attachments
      .map((file) => {
        const labelPrefix = file.requiredDocumentLabel ? `[${file.requiredDocumentLabel}] ` : '';
        return `- ${labelPrefix}${file.originalName} (${file.storedPath})`;
      })
      .join('\n');
    const requiredDocs = payload.requestedDocuments.map((name) => `- ${name}`).join('\n');

    const customFields = Object.entries(payload.applicantFieldValues || {})
      .map(([label, value]) => `- ${label}: ${value}`)
      .join('\n');

    return [
      'Demande acte usager',
      `Nom complet: ${payload.applicantFullName}`,
      `Email: ${payload.applicantEmail}`,
      `Telephone: ${payload.applicantPhone || '-'}`,
      `Reference acte: ${payload.requestedActId}`,
      '',
      'Champs usager:',
      customFields || '- Aucune',
      '',
      'Pieces exigees:',
      requiredDocs || '- Aucune',
      '',
      'Pieces jointes usager:',
      prettyList || '- Aucune',
      '',
      'Observation usager:',
      payload.note || '-',
    ].join('\n');
  }

  private async resolveSystemOwnerUserId(): Promise<string> {
    const adminUser = await this.userRepository.findOne({
      where: { role: 'admin' as any },
      order: { createdAt: 'ASC' },
      select: ['id'],
    });
    if (adminUser?.id) return adminUser.id;

    const fallbackUser = await this.userRepository.findOne({
      order: { createdAt: 'ASC' },
      select: ['id'],
    });
    if (!fallbackUser?.id) {
      throw new NotFoundException(
        'Aucun utilisateur interne disponible pour enregistrer la demande.'
      );
    }
    return fallbackUser.id;
  }

  async listPublicEmitterAdministrations() {
    const emitters = await this.issuingAdministrationRepository.find({
      where: { isActive: true as any },
      order: { name: 'ASC' },
      select: ['id', 'name', 'code', 'logo'],
    });

    const requestedActs = await this.requestedActRepository.find({
      where: { administrationScopeType: 'emitter' as any },
      select: ['administrationScopeId'],
    });

    const emitterIdsWithActs = new Set(requestedActs.map((item) => item.administrationScopeId));

    return emitters
      .filter((item) => emitterIdsWithActs.has(item.id))
      .map((item) => ({ id: item.id, name: item.name, code: item.code, logo: item.logo || null }));
  }

  async listPublicRequestedActsByEmitter(emitterAdministrationId: string) {
    const emitter = await this.issuingAdministrationRepository.findOne({
      where: { id: emitterAdministrationId, isActive: true as any },
      select: ['id', 'name', 'code'],
    });

    if (!emitter) {
      throw new NotFoundException('Administration emettrice introuvable.');
    }

    const acts = await this.requestedActRepository.find({
      where: {
        administrationScopeType: 'emitter' as any,
        administrationScopeId: emitterAdministrationId,
      },
      order: { createdAt: 'DESC' },
    });

    return acts.map((item) => ({
      id: item.id,
      emitterAdministrationId,
      administrationLabel: item.administrationLabel,
      directionCode: item.directionCode,
      directionLabel: item.directionLabel,
      documentName: item.documentName,
      requiredDocuments: Array.isArray(item.requiredDocuments) ? item.requiredDocuments : [],
      applicantFields: Array.isArray((item as any).applicantFields)
        ? (item as any).applicantFields
        : [],
    }));
  }

  async createPublicActRequestSubmission(payload: {
    emitterAdministrationId: string;
    requestedActId: string;
    applicantFullName: string;
    applicantEmail: string;
    applicantPhone?: string;
    note?: string;
    fileLabels?: string[];
    applicantFieldValues?: Record<string, string>;
    attachments: Express.Multer.File[];
  }) {
    const normalizedEmitterId = String(payload.emitterAdministrationId || '').trim();
    const normalizedRequestedActId = String(payload.requestedActId || '').trim();
    const normalizedFullName = String(payload.applicantFullName || '').trim();
    const normalizedEmail = String(payload.applicantEmail || '')
      .trim()
      .toLowerCase();

    if (
      !normalizedEmitterId ||
      !normalizedRequestedActId ||
      !normalizedFullName ||
      !normalizedEmail
    ) {
      throw new BadRequestException('Informations obligatoires manquantes.');
    }

    const attachments = Array.isArray(payload.attachments) ? payload.attachments : [];
    const fileLabels = Array.isArray(payload.fileLabels)
      ? payload.fileLabels.map((item) => String(item || '').trim())
      : [];
    const applicantFieldValues =
      payload.applicantFieldValues && typeof payload.applicantFieldValues === 'object'
        ? payload.applicantFieldValues
        : {};
    if (attachments.length === 0) {
      throw new BadRequestException(
        'Veuillez joindre au moins un fichier pour soumettre la demande.'
      );
    }

    const requestedAct = await this.requestedActRepository.findOne({
      where: {
        id: normalizedRequestedActId,
        administrationScopeType: 'emitter' as any,
        administrationScopeId: normalizedEmitterId,
      },
    });

    if (!requestedAct) {
      throw new NotFoundException('Acte demande introuvable pour cette administration.');
    }

    const requiredDocumentSet = new Set(
      (Array.isArray(requestedAct.requiredDocuments) ? requestedAct.requiredDocuments : [])
        .map((item) => String(item || '').trim())
        .filter(Boolean)
    );

    if (requiredDocumentSet.size > 0) {
      if (fileLabels.length !== attachments.length) {
        throw new BadRequestException('Chaque fichier doit être associé à une pièce exigée.');
      }

      const hasInvalidLabel = fileLabels.some(
        (label) => !requiredDocumentSet.has(String(label || '').trim())
      );
      if (hasInvalidLabel) {
        throw new BadRequestException(
          "Un ou plusieurs fichiers n'ont pas de pièce valide associée."
        );
      }
    }

    const configuredApplicantFields = Array.isArray((requestedAct as any).applicantFields)
      ? (requestedAct as any).applicantFields
      : [];

    const normalizedApplicantFieldValues = configuredApplicantFields.reduce(
      (acc, field) => {
        const label = String(field?.label || '').trim();
        if (!label) return acc;
        const value = String((applicantFieldValues as Record<string, unknown>)[label] || '').trim();
        if (!value) {
          throw new BadRequestException(`Le champ usager "${label}" est obligatoire.`);
        }
        acc[label] = value;
        return acc;
      },
      {} as Record<string, string>
    );

    const ownerUserId = await this.resolveSystemOwnerUserId();

    const primaryFile = attachments[0];
    const primaryStoredFileName = this.resolveStoredFileName(primaryFile);
    const primaryPath = `/uploads/${primaryStoredFileName}`;

    const attachmentSummaries = attachments.map((file, index) => {
      const fileName = this.resolveStoredFileName(file);
      const requiredDocumentLabel = String(fileLabels[index] || '').trim() || undefined;
      return {
        originalName: String(file.originalname || fileName),
        storedPath: `/uploads/${fileName}`,
        requiredDocumentLabel,
      };
    });

    const title = `Demande acte - ${requestedAct.documentName}`;
    const publicMeta = {
      applicant: {
        fullName: normalizedFullName,
        email: normalizedEmail,
        phone: String(payload.applicantPhone || '').trim() || undefined,
      },
      requiredDocuments: Array.isArray(requestedAct.requiredDocuments)
        ? requestedAct.requiredDocuments
        : [],
      receivedDocuments: attachmentSummaries,
      applicantFieldValues: normalizedApplicantFieldValues,
      note: String(payload.note || '').trim() || undefined,
      requestedActId: requestedAct.id,
      directionCode: this.normalizeSubEntityCode(requestedAct.directionCode),
    };

    const description = `${this.buildActRequestDescription({
      applicantFullName: normalizedFullName,
      applicantEmail: normalizedEmail,
      applicantPhone: String(payload.applicantPhone || '').trim() || undefined,
      note: String(payload.note || '').trim() || undefined,
      requestedActId: requestedAct.id,
      requestedDocuments: Array.isArray(requestedAct.requiredDocuments)
        ? requestedAct.requiredDocuments
        : [],
      attachments: attachmentSummaries,
      applicantFieldValues: normalizedApplicantFieldValues,
    })}\n\nACT_REQUEST_META::${JSON.stringify(publicMeta)}`;

    const document = this.documentRepository.create({
      title,
      description,
      filePath: primaryPath,
      fileSize: Number(primaryFile.size || 0),
      mimeType: String(primaryFile.mimetype || 'application/octet-stream'),
      status: 'draft',
      createdBy: ownerUserId,
      ownerId: ownerUserId,
      issuingAdministrationId: normalizedEmitterId,
      recipientAdministrationId: null,
      subEntityCode: this.normalizeSubEntityCode(requestedAct.directionCode),
      documentNumber: null,
      signedAt: null,
    });

    const savedDocument = await this.documentRepository.save(document);

    const firstVersion = this.documentVersionRepository.create({
      documentId: savedDocument.id,
      version: 1,
      filePath: primaryPath,
      creatorId: ownerUserId,
      changeLog: 'Creation demande acte usager',
    });
    await this.documentVersionRepository.save(firstVersion);

    return {
      message: 'Votre demande a ete enregistree avec succes.',
      requestId: savedDocument.id,
      directionCode: savedDocument.subEntityCode,
      actName: requestedAct.documentName,
      attachments: attachmentSummaries,
    };
  }

  private async resolveAuthorizedSubEntityCodes(
    user: User,
    allowedRecipients: RecipientAdministration[]
  ): Promise<string[]> {
    const explicitAssignment = await this.userDirectionAssignmentRepository.findOne({
      where: { userId: user.id },
    });
    const explicitSubEntityCode = this.normalizeSubEntityCode(
      explicitAssignment?.subEntityCode || ''
    );
    if (explicitSubEntityCode) {
      return [explicitSubEntityCode];
    }

    const normalizedEmail = (user.email || '').trim().toLowerCase();
    const normalizedUsername = (user.username || '').trim().toLowerCase();
    const normalizedFullName = (user.fullName || '').trim().toLowerCase();

    const administrationUser = await this.administrationUserRepository
      .createQueryBuilder('administrationUser')
      .leftJoinAndSelect('administrationUser.profile', 'profile')
      .where('LOWER(administrationUser.email) = :email', { email: normalizedEmail })
      .orWhere('LOWER(administrationUser.username) = :username', { username: normalizedUsername })
      .orderBy('administrationUser.updatedAt', 'DESC')
      .getOne();

    const fromProfile = this.extractSubEntityCodesFromProfilePermissions(
      administrationUser?.profile?.permissions
    );

    const fromRecipients = allowedRecipients.flatMap((recipient) => {
      const metadata = ((recipient as any)?.metadata || {}) as Record<string, unknown>;
      const subEntities = this.normalizeSubEntities(
        metadata.subEntities || (metadata as any).sousTutelles
      );

      return subEntities
        .filter((entity) => {
          const byEmail = Boolean(entity.managerEmail) && entity.managerEmail === normalizedEmail;
          const byName =
            Boolean(entity.managerName) &&
            (entity.managerName === normalizedFullName ||
              entity.managerName === normalizedUsername);
          return byEmail || byName;
        })
        .map((entity) => entity.code);
    });

    return Array.from(
      new Set(
        [...fromProfile, ...fromRecipients]
          .map((code) => this.normalizeSubEntityCode(code))
          .filter(Boolean)
      )
    );
  }

  private resolveStoredFileName(file: Express.Multer.File): string {
    const fromFilename = String(file?.filename || '').trim();
    if (fromFilename && fromFilename !== 'undefined') {
      return fromFilename;
    }

    const fromPath = basename(String((file as any)?.path || '')).trim();
    if (fromPath && fromPath !== 'undefined') {
      return fromPath;
    }

    throw new BadRequestException(
      'Le fichier téléversé est invalide (nom de stockage introuvable).'
    );
  }

  private isInvalidDocumentPath(path?: string | null): boolean {
    const normalized = String(path || '').trim();
    return !normalized || /\/undefined$/i.test(normalized);
  }

  private async findLatestValidVersionPath(documentId: string): Promise<string | null> {
    const versions = await this.documentVersionRepository.find({
      where: { documentId },
      order: { version: 'DESC', createdAt: 'DESC' },
      take: 20,
    });

    const validVersion = versions.find((version) => !this.isInvalidDocumentPath(version.filePath));
    return validVersion ? String(validVersion.filePath) : null;
  }

  async auditInvalidFilePaths(limit: number | string = 500) {
    const parsedLimit = Number(limit);
    const safeLimit =
      Number.isFinite(parsedLimit) && parsedLimit > 0
        ? Math.min(Math.floor(parsedLimit), 5000)
        : 500;

    const invalidDocs = await this.documentRepository
      .createQueryBuilder('doc')
      .where('doc.filePath IS NULL')
      .orWhere("TRIM(doc.filePath) = ''")
      .orWhere('doc.filePath LIKE :invalidSuffix', { invalidSuffix: '%/undefined' })
      .orderBy('doc.updatedAt', 'DESC')
      .take(safeLimit)
      .getMany();

    const rows = await Promise.all(
      invalidDocs.map(async (doc) => {
        const fallbackPath = await this.findLatestValidVersionPath(doc.id);
        return {
          id: doc.id,
          title: doc.title,
          filePath: doc.filePath,
          updatedAt: doc.updatedAt,
          recoverable: Boolean(fallbackPath),
          suggestedPath: fallbackPath,
        };
      })
    );

    return {
      total: rows.length,
      recoverable: rows.filter((row) => row.recoverable).length,
      unrecoverable: rows.filter((row) => !row.recoverable).length,
      data: rows,
    };
  }

  async repairInvalidFilePaths(limit: number | string = 500) {
    const audit = await this.auditInvalidFilePaths(limit);
    let repaired = 0;

    for (const row of audit.data) {
      if (!row.suggestedPath) continue;
      await this.documentRepository.update({ id: row.id }, { filePath: row.suggestedPath });
      repaired += 1;
    }

    return {
      scanned: audit.total,
      repaired,
      remainingInvalid: audit.total - repaired,
      unrecoverable: audit.unrecoverable,
    };
  }

  async findAll(
    page: number | string = 1,
    limit: number | string = 20,
    search?: string,
    ownerId?: string
  ) {
    const pagination = this.normalizePagination(page, limit);
    const query = this.documentRepository.createQueryBuilder('doc');

    if (ownerId) {
      query.where('doc.ownerId = :ownerId', { ownerId });
    }

    if (search && search.trim()) {
      const searchClause =
        'LOWER(doc.title) LIKE LOWER(:search) OR LOWER(doc.description) LIKE LOWER(:search)';
      if (ownerId) {
        query.andWhere(`(${searchClause})`, { search: `%${search}%` });
      } else {
        query.where(searchClause, { search: `%${search}%` });
      }
    }

    const [documents, total] = await query
      .orderBy('doc.createdAt', 'DESC')
      .skip((pagination.page - 1) * pagination.limit)
      .take(pagination.limit)
      .getManyAndCount();

    return {
      data: documents,
      pagination: {
        total,
        page: pagination.page,
        limit: pagination.limit,
        pages: Math.ceil(total / pagination.limit),
      },
    };
  }

  async findOne(id: string) {
    const document = await this.documentRepository.findOne({
      where: { id },
      relations: ['owner', 'signatures', 'versions', 'qrcodes'],
    });

    if (!document) {
      throw new NotFoundException('Document not found');
    }

    if (this.isInvalidDocumentPath(document.filePath)) {
      const fallbackPath = await this.findLatestValidVersionPath(document.id);
      if (fallbackPath) {
        document.filePath = fallbackPath;
        await this.documentRepository.save(document);
      }
    }

    return document;
  }

  async create(userId: string, documentData: CreateDocumentDto) {
    const issuingAdministrationId = await this.resolveUserAdministrationId(userId);

    const document = this.documentRepository.create({
      ...documentData,
      createdBy: userId,
      ownerId: userId,
      issuingAdministrationId,
      filePath: '',
      fileSize: 0,
      mimeType: 'application/octet-stream',
    });

    return await this.documentRepository.save(document);
  }

  async update(id: string, documentData: UpdateDocumentDto) {
    const document = await this.findOne(id);

    Object.assign(document, documentData);

    return await this.documentRepository.save(document);
  }

  private async ensureDocumentWritableByUser(
    userId: string,
    documentId: string
  ): Promise<Document> {
    const document = await this.documentRepository.findOne({ where: { id: documentId } });
    if (!document) {
      throw new NotFoundException('Document not found');
    }

    if (document.ownerId !== userId) {
      throw new ForbiddenException("Vous n'avez pas acces a ce document.");
    }

    return document;
  }

  private normalizeLabelCodes(codes: string[]): string[] {
    return Array.from(
      new Set(
        (Array.isArray(codes) ? codes : [])
          .map((code) =>
            String(code || '')
              .trim()
              .toUpperCase()
          )
          .filter(Boolean)
      )
    );
  }

  private parseStoredLabelCodes(value: unknown): string[] {
    if (Array.isArray(value)) {
      return this.normalizeLabelCodes(value.map((item) => String(item || '')));
    }

    const raw = String(value || '').trim();
    if (!raw) return [];

    try {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        return this.normalizeLabelCodes(parsed.map((item) => String(item || '')));
      }
    } catch {
      // Fallback below for legacy comma-separated values.
    }

    return this.normalizeLabelCodes(raw.split(','));
  }

  private async getPreferenceRaw(
    userId: string,
    documentId: string
  ): Promise<{
    id: string;
    isFavorite: boolean;
    labelCodes: string[];
  } | null> {
    const row = await this.documentUserPreferenceRepository
      .createQueryBuilder('pref')
      .select('pref.id', 'id')
      .addSelect('pref.isFavorite', 'isFavorite')
      .addSelect('pref.labelCodes', 'labelCodes')
      .where('pref.userId = :userId', { userId })
      .andWhere('pref.documentId = :documentId', { documentId })
      .getRawOne();

    if (!row) return null;

    return {
      id: String(row.id || ''),
      isFavorite:
        row.isFavorite === true ||
        String(row.isFavorite || '').toLowerCase() === 'true' ||
        String(row.isFavorite || '') === '1',
      labelCodes: this.parseStoredLabelCodes(row.labelCodes),
    };
  }

  private isPreferenceTableMissingError(error: any): boolean {
    const code = String(error?.code || '').toUpperCase();
    const message = String(error?.message || '').toLowerCase();
    return code === '42P01' || code === '1146' || message.includes('document_user_preferences');
  }

  async getUserDocumentPreferences(userId: string) {
    let rows: Array<{
      id: string;
      isFavorite: unknown;
      labelCodes: unknown;
      documentId: string;
      updatedAt: Date | string;
    }> = [];
    try {
      rows = await this.documentUserPreferenceRepository
        .createQueryBuilder('pref')
        .select('pref.id', 'id')
        .addSelect('pref.documentId', 'documentId')
        .addSelect('pref.isFavorite', 'isFavorite')
        .addSelect('pref.labelCodes', 'labelCodes')
        .addSelect('pref.updatedAt', 'updatedAt')
        .where('pref.userId = :userId', { userId })
        .orderBy('pref.updatedAt', 'DESC')
        .getRawMany();
    } catch (error: any) {
      if (this.isPreferenceTableMissingError(error)) {
        return [];
      }
      throw error;
    }

    return rows.map((row) => ({
      documentId: row.documentId,
      isFavorite:
        row.isFavorite === true ||
        String(row.isFavorite || '').toLowerCase() === 'true' ||
        String(row.isFavorite || '') === '1',
      labelCodes: this.parseStoredLabelCodes(row.labelCodes),
      updatedAt: row.updatedAt,
    }));
  }

  async updateDocumentFavorite(userId: string, documentId: string, isFavorite: boolean) {
    await this.ensureDocumentWritableByUser(userId, documentId);

    let existing: { id: string; isFavorite: boolean; labelCodes: string[] } | null = null;
    try {
      existing = await this.getPreferenceRaw(userId, documentId);
    } catch (error: any) {
      if (this.isPreferenceTableMissingError(error)) {
        return {
          documentId,
          isFavorite: Boolean(isFavorite),
          labelCodes: [],
        };
      }
      throw error;
    }

    const preference = this.documentUserPreferenceRepository.create({
      id: existing?.id,
      userId,
      documentId,
      isFavorite: false,
      labelCodes: existing?.labelCodes || [],
    });

    preference.isFavorite = Boolean(isFavorite);
    preference.labelCodes = Array.isArray(preference.labelCodes) ? preference.labelCodes : [];

    if (!preference.isFavorite && (preference.labelCodes || []).length === 0) {
      if (existing) {
        await this.documentUserPreferenceRepository.delete({ id: existing.id });
      }
      return {
        documentId,
        isFavorite: false,
        labelCodes: [],
      };
    }

    const saved = await this.documentUserPreferenceRepository.save(preference);
    return {
      documentId: saved.documentId,
      isFavorite: Boolean(saved.isFavorite),
      labelCodes: Array.isArray(saved.labelCodes) ? saved.labelCodes : [],
    };
  }

  async updateDocumentLabelCodes(userId: string, documentId: string, codes: string[]) {
    await this.ensureDocumentWritableByUser(userId, documentId);

    const normalizedCodes = this.normalizeLabelCodes(codes);

    let existing: { id: string; isFavorite: boolean; labelCodes: string[] } | null = null;
    try {
      existing = await this.getPreferenceRaw(userId, documentId);
    } catch (error: any) {
      if (this.isPreferenceTableMissingError(error)) {
        return {
          documentId,
          isFavorite: false,
          labelCodes: normalizedCodes,
        };
      }
      throw error;
    }

    const preference = this.documentUserPreferenceRepository.create({
      id: existing?.id,
      userId,
      documentId,
      isFavorite: existing?.isFavorite || false,
      labelCodes: existing?.labelCodes || [],
    });

    preference.labelCodes = normalizedCodes;

    if (!preference.isFavorite && normalizedCodes.length === 0) {
      if (existing) {
        await this.documentUserPreferenceRepository.delete({ id: existing.id });
      }
      return {
        documentId,
        isFavorite: false,
        labelCodes: [],
      };
    }

    const saved = await this.documentUserPreferenceRepository.save(preference);
    return {
      documentId: saved.documentId,
      isFavorite: Boolean(saved.isFavorite),
      labelCodes: Array.isArray(saved.labelCodes) ? saved.labelCodes : [],
    };
  }

  async delete(id: string) {
    const document = await this.findOne(id);

    document.deletedAt = new Date();

    await this.documentRepository.save(document);

    return { message: 'Document deleted successfully' };
  }

  async upload(
    userId: string,
    file: Express.Multer.File,
    options?: { generatedFromSharedTemplate?: boolean; subEntityCode?: string; title?: string }
  ) {
    if (!file) {
      throw new NotFoundException('No file provided');
    }

    const storedFileName = this.resolveStoredFileName(file);

    const issuingAdministrationId = await this.resolveUserAdministrationId(userId);

    const requestedSubEntityCode =
      this.normalizeSubEntityCode(String(options?.subEntityCode || '')) || null;
    const isGeneratedFromSharedTemplate = Boolean(options?.generatedFromSharedTemplate);
    const requestedTitle = String(options?.title || '').trim();

    const savedDocument = await this.documentRepository.manager.transaction(
      async (manager: EntityManager) => {
        let resolvedDocumentNumber: string | null = null;
        if (isGeneratedFromSharedTemplate && issuingAdministrationId) {
          resolvedDocumentNumber = await this.generateNextDocumentNumberForTemplateUpload(
            manager,
            issuingAdministrationId,
            requestedSubEntityCode
          );
        }

        const documentRepository = manager.getRepository(Document);
        const versionRepository = manager.getRepository(DocumentVersion);

        const document = documentRepository.create({
          title: requestedTitle || file.originalname.split('.')[0],
          filePath: `/uploads/${storedFileName}`,
          fileSize: file.size,
          mimeType: file.mimetype,
          createdBy: userId,
          ownerId: userId,
          issuingAdministrationId,
          subEntityCode: requestedSubEntityCode,
          documentNumber: resolvedDocumentNumber,
          status: 'draft',
        });

        const saved = await documentRepository.save(document);

        await versionRepository.save({
          documentId: saved.id,
          version: 1,
          filePath: `/uploads/${storedFileName}`,
          creatorId: userId,
          changeLog: 'Initial upload',
        });

        return saved;
      }
    );

    if (isGeneratedFromSharedTemplate && savedDocument.documentNumber) {
      try {
        await this.qrcodeService.generate(savedDocument.id, userId, {
          documentId: savedDocument.id,
          type: 'generated-document',
          metadata: {
            documentNumber: savedDocument.documentNumber,
            issuingAdministrationId: savedDocument.issuingAdministrationId,
            generatedFromSharedTemplate: true,
          },
          expiresAt: new Date(new Date().setFullYear(new Date().getFullYear() + 10)).toISOString(),
        });
      } catch {
        // Non-blocking: document creation remains successful even if QR generation fails.
      }
    }

    return savedDocument;
  }

  private async generateNextDocumentNumberForTemplateUpload(
    manager: EntityManager,
    administrationId: string,
    subEntityCode: string | null
  ): Promise<string> {
    const updateResult = await manager
      .createQueryBuilder()
      .update(IssuingAdministration)
      .set({
        documentNumberSequence: () => '"documentNumberSequence" + 1',
      })
      .where('id = :id', { id: administrationId })
      .returning([
        'id',
        'code',
        'documentNumberPrefix',
        'documentNumberPadding',
        'documentNumberSequence',
      ])
      .execute();

    const row = updateResult.raw?.[0];
    if (!row) {
      throw new NotFoundException('Issuing administration not found for document numbering');
    }

    const prefix = String(row.documentNumberPrefix || row.code || 'DOC')
      .trim()
      .toUpperCase();
    const normalizedSubEntity = this.normalizeSubEntityCode(subEntityCode || '') || 'ENTITE';
    const padding = Math.max(Number(row.documentNumberPadding || 5), 5);
    const sequence = String(Number(row.documentNumberSequence || 1)).padStart(padding, '0');
    const year = new Date().getFullYear();

    return `${prefix}-${normalizedSubEntity}-${sequence}-${year}`;
  }

  async getVersions(id: string) {
    const versions = await this.documentVersionRepository.find({
      where: { documentId: id },
      order: { createdAt: 'DESC' },
      relations: ['creator'],
    });

    return versions;
  }

  async createNewVersion(documentId: string, userId: string, file: Express.Multer.File) {
    const document = await this.findOne(documentId);
    const storedFileName = this.resolveStoredFileName(file);

    const versions = await this.documentVersionRepository.find({
      where: { documentId },
    });

    const nextVersion = versions.length + 1;

    const newVersion = this.documentVersionRepository.create({
      documentId,
      version: nextVersion,
      filePath: `/uploads/${storedFileName}`,
      creatorId: userId,
      changeLog: `Updated to version ${nextVersion}`,
    });

    await this.documentVersionRepository.save(newVersion);

    // Update document
    document.filePath = `/uploads/${storedFileName}`;
    document.fileSize = file.size;
    document.mimeType = file.mimetype;

    return await this.documentRepository.save(document);
  }

  async getDocumentsByOwner(userId: string, page = 1, limit = 20) {
    const query = this.documentRepository
      .createQueryBuilder('doc')
      .where('doc.ownerId = :userId', { userId });

    const [documents, total] = await query
      .orderBy('doc.createdAt', 'DESC')
      .skip((page - 1) * limit)
      .take(limit)
      .getManyAndCount();

    return {
      data: documents,
      pagination: {
        total,
        page,
        limit,
        pages: Math.ceil(total / limit),
      },
    };
  }

  async getReceptionDocuments(
    userId: string,
    page: number | string = 1,
    limit: number | string = 20,
    search?: string
  ) {
    const pagination = this.normalizePagination(page, limit);
    const allowedRecipientIds = await this.resolveAllowedRecipientAdministrationIds(userId);
    if (allowedRecipientIds.length === 0) {
      return {
        data: [],
        pagination: { total: 0, page: pagination.page, limit: pagination.limit, pages: 0 },
      };
    }

    const queryBuilder = this.documentRepository
      .createQueryBuilder('doc')
      .where('doc.recipientAdministrationId IN (:...recipientIds)', {
        recipientIds: allowedRecipientIds,
      });

    if (search && search.trim()) {
      queryBuilder.andWhere(
        '(LOWER(doc.title) LIKE LOWER(:search) OR LOWER(doc.description) LIKE LOWER(:search))',
        {
          search: `%${search.trim()}%`,
        }
      );
    }

    const [documents, total] = await queryBuilder
      .orderBy('doc.createdAt', 'DESC')
      .skip((pagination.page - 1) * pagination.limit)
      .take(pagination.limit)
      .getManyAndCount();

    return {
      data: documents,
      pagination: {
        total,
        page: pagination.page,
        limit: pagination.limit,
        pages: Math.ceil(total / pagination.limit),
      },
    };
  }

  async markReceptionZipDownloaded(userId: string, documentId: string) {
    const allowedRecipientIds = await this.resolveAllowedRecipientAdministrationIds(userId);
    if (allowedRecipientIds.length === 0) {
      throw new ForbiddenException(
        "Vous n'êtes pas autorisé à traiter les documents de réception."
      );
    }

    const document = await this.findOne(documentId);
    if (
      !document.recipientAdministrationId ||
      !allowedRecipientIds.includes(document.recipientAdministrationId)
    ) {
      throw new ForbiddenException(
        "Ce document n'appartient pas à votre administration destinataire."
      );
    }

    const currentMeta = this.parseRecipientShareMeta(document.description);
    const existingFirstDownload = String(currentMeta.zipDownloadedAt || '').trim();
    if (!existingFirstDownload) {
      document.description = this.upsertRecipientShareMeta(document.description, {
        zipDownloadedAt: new Date().toISOString(),
      });
    }
    await this.documentRepository.save(document);

    return {
      id: document.id,
      zipDownloadedAt: this.parseRecipientShareMeta(document.description).zipDownloadedAt || null,
      message: 'Téléchargement ZIP marqué comme effectué.',
    };
  }

  async getActRequests(
    userId: string,
    page: number | string = 1,
    limit: number | string = 20,
    search?: string
  ) {
    const pagination = this.normalizePagination(page, limit);
    const access = await this.resolveActRequestAccessContext(userId, { requireProcess: false });
    const isAdmin = access.isAdmin;
    const requesterAdministrationId = access.requesterAdministrationId;

    const queryBuilder = this.documentRepository
      .createQueryBuilder('doc')
      .where(
        isAdmin
          ? '1 = 1'
          : '(doc.recipientAdministrationId = :administrationId OR doc.issuingAdministrationId = :administrationId)',
        isAdmin ? {} : { administrationId: requesterAdministrationId }
      )
      .andWhere(
        `(
          LOWER(COALESCE(doc.title, '')) LIKE :requestLike
          OR LOWER(COALESCE(doc.description, '')) LIKE :requestLike
        )`,
        { requestLike: '%demande%' }
      );

    if (!isAdmin) {
      const allowedSubEntityCodes = access.allowedSubEntityCodes;
      if (allowedSubEntityCodes.length > 0) {
        queryBuilder.andWhere("UPPER(COALESCE(doc.subEntityCode, '')) IN (:...subEntityCodes)", {
          subEntityCodes: allowedSubEntityCodes,
        });
      } else {
        queryBuilder.andWhere('1 = 0');
      }
    }

    if (search && search.trim()) {
      queryBuilder.andWhere(
        `(
          LOWER(COALESCE(doc.title, '')) LIKE LOWER(:search)
          OR LOWER(COALESCE(doc.description, '')) LIKE LOWER(:search)
          OR LOWER(COALESCE(doc.subEntityCode, '')) LIKE LOWER(:search)
          OR LOWER(COALESCE(doc.documentNumber, '')) LIKE LOWER(:search)
        )`,
        { search: `%${search.trim()}%` }
      );
    }

    const [documents, total] = await queryBuilder
      .orderBy('doc.createdAt', 'DESC')
      .skip((pagination.page - 1) * pagination.limit)
      .take(pagination.limit)
      .getManyAndCount();

    return {
      data: documents,
      pagination: {
        total,
        page: pagination.page,
        limit: pagination.limit,
        pages: Math.ceil(total / pagination.limit),
      },
    };
  }

  async getActRequestDetails(userId: string, documentId: string) {
    const access = await this.resolveActRequestAccessContext(userId, { requireProcess: true });
    const document = await this.findOne(documentId);

    if (!this.isActRequestDocument(document)) {
      throw new NotFoundException("Demande d'acte introuvable.");
    }

    if (!access.isAdmin) {
      const administrationId = access.requesterAdministrationId;
      const scopedByAdministration =
        document.issuingAdministrationId === administrationId ||
        document.recipientAdministrationId === administrationId;
      if (!scopedByAdministration) {
        throw new ForbiddenException("Cette demande n'appartient pas à votre administration.");
      }

      if (access.allowedSubEntityCodes.length === 0) {
        throw new ForbiddenException(
          'Aucune entité sous tutelle autorisée pour traiter cette demande.'
        );
      }

      const documentSubEntityCode = this.normalizeSubEntityCode(document.subEntityCode || '');
      if (!access.allowedSubEntityCodes.includes(documentSubEntityCode)) {
        throw new ForbiddenException(
          "Cette demande n'est pas affectée à votre entité sous tutelle."
        );
      }
    }

    const parsed = this.parseActRequestDescription(document.description);
    const normalizedReceivedNames = parsed.receivedDocuments.map((item) =>
      this.normalizeAttachmentName(item.originalName)
    );

    const requiredDocuments = parsed.requiredDocuments.map((name) => {
      const normalizedRequired = this.normalizeAttachmentName(name);
      const matchedFiles = parsed.receivedDocuments
        .filter((file) => {
          const normalizedLabel = this.normalizeAttachmentName(file.requiredDocumentLabel || '');
          if (normalizedLabel && normalizedLabel === normalizedRequired) {
            return true;
          }
          const normalizedReceived = this.normalizeAttachmentName(file.originalName);
          return (
            normalizedReceived.includes(normalizedRequired) ||
            normalizedRequired.includes(normalizedReceived)
          );
        })
        .map((file) => file.originalName);

      return {
        name,
        received: matchedFiles.length > 0,
        matchedFiles,
      };
    });

    return {
      id: document.id,
      title: document.title,
      status: document.status,
      createdAt: document.createdAt,
      subEntityCode: document.subEntityCode,
      issuingAdministrationId: document.issuingAdministrationId,
      recipientAdministrationId: document.recipientAdministrationId,
      applicant: parsed.applicant,
      applicantFieldValues: parsed.applicantFieldValues,
      note: parsed.note,
      requiredDocuments,
      receivedDocuments: parsed.receivedDocuments,
      completeness: {
        requiredTotal: requiredDocuments.length,
        requiredReceived: requiredDocuments.filter((item) => item.received).length,
        receivedTotal: parsed.receivedDocuments.length,
      },
      debug: {
        normalizedReceivedNames,
      },
    };
  }

  async startActRequestProcessing(userId: string, documentId: string) {
    const access = await this.resolveActRequestAccessContext(userId, { requireProcess: true });
    const document = await this.findOne(documentId);

    if (!this.isActRequestDocument(document)) {
      throw new NotFoundException("Demande d'acte introuvable.");
    }

    if (!access.isAdmin) {
      const administrationId = access.requesterAdministrationId;
      const scopedByAdministration =
        document.issuingAdministrationId === administrationId ||
        document.recipientAdministrationId === administrationId;
      if (!scopedByAdministration) {
        throw new ForbiddenException("Cette demande n'appartient pas à votre administration.");
      }

      if (access.allowedSubEntityCodes.length === 0) {
        throw new ForbiddenException(
          'Aucune entité sous tutelle autorisée pour traiter cette demande.'
        );
      }

      const documentSubEntityCode = this.normalizeSubEntityCode(document.subEntityCode || '');
      if (!access.allowedSubEntityCodes.includes(documentSubEntityCode)) {
        throw new ForbiddenException(
          "Cette demande n'est pas affectée à votre entité sous tutelle."
        );
      }
    }

    const previousStatus = String(document.status || '')
      .trim()
      .toLowerCase();
    if (previousStatus !== 'active') {
      document.status = 'active';
      await this.documentRepository.save(document);
    }

    const parsed = this.parseActRequestDescription(document.description);
    const applicantEmail = String(parsed.applicant?.email || '').trim();
    let emailSent = false;

    if (applicantEmail) {
      const mail = await this.notificationsService.sendEmailNotification({
        to: applicantEmail,
        subject: `Votre demande d'acte est en cours de traitement`,
        text: [
          `Bonjour ${parsed.applicant?.fullName || ''},`,
          '',
          `Votre demande "${document.title}" est désormais en cours de traitement.`,
          `Référence: ${document.id}`,
          '',
          'Vous recevrez une notification une fois le traitement finalisé.',
          '',
          'E-Parapheur',
        ].join('\n'),
      });
      emailSent = Boolean(mail?.sent);
    }

    return {
      id: document.id,
      status: document.status,
      emailSent,
      applicantEmail: applicantEmail || null,
      message: 'Demande marquée en cours de traitement.',
    };
  }

  async markActRequestAsTreated(userId: string, documentId: string) {
    const access = await this.resolveActRequestAccessContext(userId, { requireProcess: true });
    const document = await this.findOne(documentId);

    if (!this.isActRequestDocument(document)) {
      throw new NotFoundException("Demande d'acte introuvable.");
    }

    if (!access.isAdmin) {
      const administrationId = access.requesterAdministrationId;
      const scopedByAdministration =
        document.issuingAdministrationId === administrationId ||
        document.recipientAdministrationId === administrationId;
      if (!scopedByAdministration) {
        throw new ForbiddenException("Cette demande n'appartient pas à votre administration.");
      }

      if (access.allowedSubEntityCodes.length === 0) {
        throw new ForbiddenException(
          'Aucune entité sous tutelle autorisée pour traiter cette demande.'
        );
      }

      const documentSubEntityCode = this.normalizeSubEntityCode(document.subEntityCode || '');
      if (!access.allowedSubEntityCodes.includes(documentSubEntityCode)) {
        throw new ForbiddenException(
          "Cette demande n'est pas affectée à votre entité sous tutelle."
        );
      }
    }

    const previousStatus = String(document.status || '')
      .trim()
      .toLowerCase();
    if (previousStatus === 'archived') {
      return {
        id: document.id,
        status: document.status,
        message: 'Demande déjà marquée comme traitée.',
      };
    }

    if (previousStatus !== 'signed') {
      throw new BadRequestException(
        "La demande doit être signée avant d'être marquée comme traitée."
      );
    }

    document.status = 'archived';
    await this.documentRepository.save(document);

    return {
      id: document.id,
      status: document.status,
      message: 'Demande marquée comme traitée.',
    };
  }

  async searchDocuments(query: string, page = 1, limit = 20) {
    const [documents, total] = await this.documentRepository.findAndCount({
      where: [{ title: Like(`%${query}%`) }, { description: Like(`%${query}%`) }],
      order: { createdAt: 'DESC' },
      skip: (page - 1) * limit,
      take: limit,
    });

    return {
      data: documents,
      pagination: {
        total,
        page,
        limit,
        pages: Math.ceil(total / limit),
      },
    };
  }

  async share(documentId: string, actorUserId: string, shareData: ShareDocumentDto) {
    const document = await this.findOne(documentId);

    const actor = await this.userRepository.findOne({ where: { id: actorUserId } });
    const actorName = actor?.fullName || actor?.username || 'Un utilisateur';

    let expiresAt: Date | null = null;
    if (shareData.hasDelay && shareData.delayValue && shareData.delayUnit) {
      expiresAt = new Date();
      if (shareData.delayUnit === 'hours') {
        expiresAt.setHours(expiresAt.getHours() + shareData.delayValue);
      } else {
        expiresAt.setDate(expiresAt.getDate() + shareData.delayValue);
      }
    }

    const recipientEmail = shareData.recipientEmail || null;

    if (shareData.mode === 'recipient_administration') {
      const recipientAdministrationId = String(shareData.recipientAdministrationId || '').trim();
      const applicantFullName = String(shareData.applicantFullName || '').trim();
      const applicantMatricule = String(shareData.applicantMatricule || '').trim();
      const applicantEmail = String(shareData.applicantEmail || '').trim();

      if (!recipientAdministrationId) {
        throw new BadRequestException('Administration destinataire requise.');
      }
      if (!applicantFullName || !applicantMatricule || !applicantEmail) {
        throw new BadRequestException(
          'Nom et prénoms, matricule et email usager sont obligatoires.'
        );
      }

      const recipientAdministration = await this.recipientAdministrationRepository.findOne({
        where: { id: recipientAdministrationId, isActive: true },
      });
      if (!recipientAdministration) {
        throw new NotFoundException('Administration destinataire introuvable ou inactive.');
      }

      document.recipientAdministrationId = recipientAdministrationId;
      document.description = this.upsertRecipientShareMeta(document.description, {
        sharedAt: new Date().toISOString(),
        applicantFullName,
        applicantMatricule,
        applicantEmail,
      });
      await this.documentRepository.save(document);
    }

    if (shareData.mode === 'internal') {
      const normalizedRecipientName = (shareData.recipientName || '').trim().toLowerCase();
      const normalizedRecipientEmail = (recipientEmail || '').trim().toLowerCase();

      if (normalizedRecipientName || normalizedRecipientEmail) {
        const match = await this.recipientAdministrationRepository
          .createQueryBuilder('recipient')
          .where('recipient.channel = :channel', { channel: 'api' })
          .andWhere('recipient.isActive = :isActive', { isActive: true })
          .andWhere(
            '(LOWER(recipient.name) = :recipientName OR LOWER(recipient.emailAddress) = :recipientEmail)',
            {
              recipientName: normalizedRecipientName || '__none__',
              recipientEmail: normalizedRecipientEmail || '__none__',
            }
          )
          .getOne();

        if (match) {
          document.recipientAdministrationId = match.id;
          await this.documentRepository.save(document);
        }
      }
    }
    if (recipientEmail) {
      const shareTypeLabel =
        shareData.mode === 'internal'
          ? 'interne'
          : shareData.mode === 'recipient_administration'
            ? 'administration destinataire'
            : 'externe';
      const permissionLabel = shareData.permission || 'lecture';
      const expiryLine = expiresAt
        ? `\nCe partage expire le ${expiresAt.toLocaleString('fr-FR')}.`
        : "\nCe partage n'a pas de date d'expiration.";

      const shareLink = `${process.env.API_URL || 'http://localhost:3000'}/documents/${document.id}`;

      await this.notificationsService.sendEmailNotification({
        to: recipientEmail,
        subject: `Partage de document: ${document.title}`,
        text:
          `${actorName} a partagé le document "${document.title}" avec vous.` +
          `\nType de partage: ${shareTypeLabel}` +
          `\nDroit: ${permissionLabel}` +
          `${expiryLine}` +
          `\nLien: ${shareLink}`,
      });
    }

    return {
      message: 'Partage enregistré et notification email traitée.',
      documentId: document.id,
      recipientEmail,
      expiresAt,
    };
  }

  async resolveDigitalVersion(documentId: string) {
    const document = await this.findOne(documentId);

    if (this.isInvalidDocumentPath(document.filePath)) {
      const fallbackPath = await this.findLatestValidVersionPath(document.id);
      if (fallbackPath) {
        document.filePath = fallbackPath;
        await this.documentRepository.save(document);
      }
    }

    if (this.isInvalidDocumentPath(document.filePath)) {
      throw new NotFoundException('Document file is not available');
    }

    const normalizedPath = document.filePath.replace(/\\/g, '/');
    const fileName = basename(normalizedPath);

    // Keep multiple candidates to support existing upload storage layouts.
    const candidates = [
      join(process.cwd(), normalizedPath.replace(/^\//, '')),
      join(process.cwd(), 'uploads', fileName),
      join(process.cwd(), 'storage', 'uploads', fileName),
    ];

    const absolutePath = candidates.find((candidate) => existsSync(candidate));
    if (!absolutePath) {
      throw new NotFoundException('Digital document file not found on server');
    }

    return {
      absolutePath,
      fileName,
      mimeType: document.mimeType || 'application/octet-stream',
      document,
    };
  }
}
