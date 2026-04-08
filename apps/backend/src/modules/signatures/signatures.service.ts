import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { EntityManager, Repository } from 'typeorm';
import * as crypto from 'crypto';
import { readFile as fsReadFile } from 'fs/promises';
import { existsSync } from 'fs';
import { join, basename } from 'path';
import { Signature } from './signature.entity';
import { SignatureRequest } from './signature-request.entity';
import { Document } from '../documents/document.entity';
import { CreateSignatureDto, RequestSignatureDto } from './dto/signature.dto';
import { User } from '../users/user.entity';
import { NotificationsService } from '../notifications/notifications.service';
import { AdministrationUser } from '../administration/entities/administration-user.entity';
import { IssuingAdministration } from '../administration/entities/issuing-administration.entity';
import { SignatureProviderConfig } from '../administration/entities/signature-provider-config.entity';
import { AppSetting } from '../administration/entities/app-setting.entity';
import { QrcodeService } from '../qrcode/qrcode.service';

@Injectable()
export class SignaturesService {
  private static readonly SIGNATURE_QR_POSITION_SETTING_KEY = 'signature_qr_position';

  constructor(
    @InjectRepository(Signature)
    private signatureRepository: Repository<Signature>,
    @InjectRepository(SignatureRequest)
    private signatureRequestRepository: Repository<SignatureRequest>,
    @InjectRepository(Document)
    private documentRepository: Repository<Document>,
    @InjectRepository(User)
    private userRepository: Repository<User>,
    @InjectRepository(AdministrationUser)
    private administrationUserRepository: Repository<AdministrationUser>,
    @InjectRepository(IssuingAdministration)
    private issuingAdministrationRepository: Repository<IssuingAdministration>,
    @InjectRepository(SignatureProviderConfig)
    private signatureProviderConfigRepository: Repository<SignatureProviderConfig>,
    @InjectRepository(AppSetting)
    private appSettingRepository: Repository<AppSetting>,
    private notificationsService: NotificationsService,
    private qrcodeService: QrcodeService,
  ) {}

  private async resolveSignatureQrPosition(): Promise<{
    imagePage: number;
    imageX: number;
    imageY: number;
    imageWidth: number;
    imageHeight: number;
  }> {
    const defaults = {
      imagePage: -1,
      imageX: 390,
      imageY: 710,
      imageWidth: 150,
      imageHeight: 80,
    };

    const setting = await this.appSettingRepository.findOne({
      where: { key: SignaturesService.SIGNATURE_QR_POSITION_SETTING_KEY },
    });

    if (!setting?.value) return defaults;

    try {
      const parsed = JSON.parse(setting.value) as Record<string, unknown>;
      const toNumber = (value: unknown, fallback: number) => {
        const parsedValue = Number(value);
        return Number.isFinite(parsedValue) ? parsedValue : fallback;
      };

      return {
        imagePage: toNumber(parsed.imagePage, defaults.imagePage),
        imageX: toNumber(parsed.imageX, defaults.imageX),
        imageY: toNumber(parsed.imageY, defaults.imageY),
        imageWidth: toNumber(parsed.imageWidth, defaults.imageWidth),
        imageHeight: toNumber(parsed.imageHeight, defaults.imageHeight),
      };
    } catch {
      return defaults;
    }
  }

  /**
   * Full multi-step integration with the signature platform.
   * Flow (based on collection Postman UVCI):
   *   1. GET  /api/users?items.email={signerEmail}         → resolve signer's userId on provider
   *   2. GET  /api/users/me  (or use stored providerOwnerUserId) → resolve workflow creator
   *   3. POST /api/users/{ownerUserId}/workflows            → create parapheur
   *   4. POST /api/workflows/{workflowId}/parts             → upload PDF binary
   *   5. POST /api/workflows/{workflowId}/documents         → link part to workflow
   *   6. PATCH /api/workflows/{workflowId}                  → start workflow
   *   7. POST /api/workflows/{workflowId}/invite            → send invite URL to signer
   */
  private async callExternalSignatureProvider(
    document: Document,
    userId: string,
    administrationId: string | null,
    _signatureData: CreateSignatureDto,
  ): Promise<Record<string, any> | null> {
    let providerConfig: SignatureProviderConfig | null = null;

    // 1) Prefer active config bound to the issuing administration.
    if (administrationId) {
      providerConfig = await this.signatureProviderConfigRepository.findOne({
        where: { administrationId, isActive: true },
        order: { createdAt: 'ASC' },
      });
    }

    // 2) Fallback to active global config.
    if (!providerConfig) {
      providerConfig = await this.signatureProviderConfigRepository.findOne({
        where: { administrationId: null as any, isActive: true },
        order: { createdAt: 'ASC' },
      });
    }

    // 3) Do not silently skip external call.
    if (!providerConfig) {
      throw new BadRequestException(
        'Aucune configuration API Signature active trouvée (administration ou globale).',
      );
    }

    const endpoint = (providerConfig.endpoint || '').trim().replace(/\/+$/, '');
    const apiBase = /\/api$/i.test(endpoint) ? endpoint : `${endpoint}/api`;
    const apiKey = (providerConfig.apiKey || '').trim();
    const consentPageId = (providerConfig.consentPageId || '').trim();
    const signatureProfileId = (providerConfig.signatureProfileId || '').trim();
    const configuredOwnerUserId = (providerConfig.providerOwnerUserId || '').trim();
    const timeoutMs = providerConfig.timeoutMs || 30000;

    if (!endpoint || !apiKey || !consentPageId || !signatureProfileId) {
      throw new BadRequestException(
        'Signature API configuration is incomplete. Please set endpoint, API key, consent page ID and signature profile ID.',
      );
    }

    const baseHeaders: Record<string, string> = {
      Authorization: `Bearer ${apiKey}`,
    };

    const fetchStep = async (url: string, opts: RequestInit): Promise<any> => {
      const ctrl = new AbortController();
      const timer = setTimeout(() => ctrl.abort(), timeoutMs);
      let res: Response;
      try {
        res = await fetch(url, { ...opts, signal: ctrl.signal });
      } catch (err: any) {
        if (err?.name === 'AbortError') {
          throw new BadRequestException(`Signature provider timeout at: ${url}`);
        }
        throw new BadRequestException(`Signature provider network error: ${err?.message || 'Unknown'}`);
      } finally {
        clearTimeout(timer);
      }
      const text = await res.text();
      let body: any = text;
      try {
        body = text ? JSON.parse(text) : {};
      } catch {
        // keep raw text
      }
      if (!res.ok) {
        const detail = typeof body === 'string' ? body : JSON.stringify(body);
        throw new BadRequestException(`Signature API (${res.status}) at ${url}: ${detail}`);
      }
      return body;
    };

    // ── Step 1: Resolve signer email ────────────────────────────────────────
    const signer = await this.userRepository.findOne({ where: { id: userId } });
    const signerEmail = signer?.email;
    if (!signerEmail) {
      throw new BadRequestException(
        'Le signataire ne possède pas d\'adresse e-mail. Un e-mail est requis pour l\'intégration avec la plateforme de signature.',
      );
    }

    // ── Step 2: Look up signer's userId on provider platform ─────────────────
    const userSearch = await fetchStep(
      `${apiBase}/users?items.email=${encodeURIComponent(signerEmail)}`,
      { headers: baseHeaders },
    );
    const providerSignerUserId: string | undefined =
      userSearch?.items?.[0]?.id ?? userSearch?.[0]?.id;
    if (!providerSignerUserId) {
      throw new BadRequestException(
        `L'utilisateur ${signerEmail} n'est pas trouvé sur la plateforme de signature. Vérifiez que son compte existe sur la plateforme.`,
      );
    }

    // ── Step 3: Resolve workflow owner (API token holder) ──────────────────
    let ownerUserId = configuredOwnerUserId;
    if (!ownerUserId) {
      const meBody = await fetchStep(`${apiBase}/users/me`, { headers: baseHeaders });
      ownerUserId = meBody?.id;
      if (!ownerUserId) {
        throw new BadRequestException(
          'Impossible de déterminer l\'identifiant du propriétaire API. Veuillez le configurer dans les paramètres API Signature.',
        );
      }
    }

    // ── Step 4: Create workflow (parapheur) ────────────────────────────────
    const workflowBody = await fetchStep(
      `${apiBase}/users/${ownerUserId}/workflows`,
      {
        method: 'POST',
        headers: { ...baseHeaders, 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: document.title || 'Document',
          steps: [
            {
              stepType: 'signature',
              recipients: [
                {
                  consentPageId,
                  userId: providerSignerUserId,
                },
              ],
              sendDownloadLink: false,
              hideWorkflowRecipients: true,
            },
          ],
          notifiedEvents: [],
        }),
      },
    );
    const workflowId: string = workflowBody?.id;
    if (!workflowId) {
      throw new BadRequestException(
        'La plateforme de signature n\'a pas retourné d\'identifiant de workflow.',
      );
    }

    // ── Step 5: Upload document as binary part ─────────────────────────────
    const normalizedPath = String(document.filePath || '').replace(/\\/g, '/');
    const fileName = basename(normalizedPath || '');
    const candidates = [
      join(process.cwd(), normalizedPath.replace(/^\//, '')),
      fileName ? join(process.cwd(), 'uploads', fileName) : '',
      fileName ? join(process.cwd(), 'storage', 'uploads', fileName) : '',
    ].filter(Boolean);
    const absolutePath = candidates.find((candidate) => existsSync(candidate));

    const fileBuffer = absolutePath ? await fsReadFile(absolutePath).catch(() => null) : null;
    if (!fileBuffer) {
      throw new BadRequestException(
        `Le fichier document est inaccessible : ${document.filePath}`,
      );
    }
    const fileHash = crypto.createHash('sha256').update(fileBuffer).digest('base64');
    const fileSize = fileBuffer.length;
    const contentType = document.mimeType || 'application/pdf';

    await fetchStep(`${apiBase}/workflows/${workflowId}/parts`, {
      method: 'POST',
      headers: { ...baseHeaders, 'Content-Type': contentType },
      body: fileBuffer,
    });

    // ── Step 6: Link document to workflow ──────────────────────────────────
    const qrPosition = await this.resolveSignatureQrPosition();

    await fetchStep(`${apiBase}/workflows/${workflowId}/documents`, {
      method: 'POST',
      headers: { ...baseHeaders, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        parts: [
          {
            filename: document.title || 'Document',
            contentType,
            size: fileSize,
            hash: fileHash,
          },
        ],
        signatureProfileId,
        pdfSignatureFields: [
          {
            imagePage: qrPosition.imagePage,
            imageX: qrPosition.imageX,
            imageY: qrPosition.imageY,
            imageWidth: qrPosition.imageWidth,
            imageHeight: qrPosition.imageHeight,
          },
        ],
      }),
    });

    // ── Step 7: Start workflow ─────────────────────────────────────────────
    await fetchStep(`${apiBase}/workflows/${workflowId}`, {
      method: 'PATCH',
      headers: { ...baseHeaders, 'Content-Type': 'application/json' },
      body: JSON.stringify({ workflowStatus: 'started' }),
    });

    // ── Step 8: Send invite to signer ──────────────────────────────────────
    const inviteBody = await fetchStep(`${apiBase}/workflows/${workflowId}/invite`, {
      method: 'POST',
      headers: { ...baseHeaders, 'Content-Type': 'application/json' },
      body: JSON.stringify({ recipientEmail: signerEmail }),
    });

    return {
      workflowId,
      inviteUrl: inviteBody?.inviteUrl ?? null,
      signerEmail,
      providerSignerUserId,
    };
  }

  private async resolveAdministrationIdForDocument(document: Document, fallbackUserId: string): Promise<string | null> {
    if (document.issuingAdministrationId) {
      return document.issuingAdministrationId;
    }

    const candidateUserIds = [document.ownerId, document.createdBy, fallbackUserId].filter(Boolean);

    for (const candidateUserId of candidateUserIds) {
      const user = await this.userRepository.findOne({ where: { id: candidateUserId } });
      if (!user) continue;

      let administrationUser = await this.administrationUserRepository.findOne({
        where: [{ email: user.email }, { username: user.username }],
      });

      // Fallback to case-insensitive match when data was saved with inconsistent casing.
      if (!administrationUser) {
        const normalizedEmail = (user.email || '').trim().toLowerCase();
        const normalizedUsername = (user.username || '').trim().toLowerCase();
        administrationUser = await this.administrationUserRepository
          .createQueryBuilder('au')
          .where('LOWER(au.email) = :email', { email: normalizedEmail })
          .orWhere('LOWER(au.username) = :username', { username: normalizedUsername })
          .getOne();
      }

      if (administrationUser?.administrationId) {
        return administrationUser.administrationId;
      }
    }

    // Fallback through direction code -> parent issuing administration mapping.
    const normalizedSubEntityCode = (document.subEntityCode || '').trim().toUpperCase();
    if (normalizedSubEntityCode) {
      const administrations = await this.issuingAdministrationRepository.find({
        select: ['id', 'metadata'],
      });

      for (const administration of administrations) {
        const metadata = (administration.metadata || {}) as Record<string, any>;
        const subEntities = Array.isArray(metadata.subEntities)
          ? metadata.subEntities
          : Array.isArray(metadata.sousTutelles)
            ? metadata.sousTutelles
            : [];

        const match = subEntities.find((item: any) =>
          String(item?.code || '').trim().toUpperCase() === normalizedSubEntityCode,
        );

        if (match) {
          return administration.id;
        }
      }
    }

    return null;
  }

  private async generateNextDocumentNumber(
    manager: EntityManager,
    administrationId: string,
  ): Promise<string> {
    return this.generateNextDocumentNumberWithSubEntity(manager, administrationId, null);
  }

  private async generateNextDocumentNumberWithSubEntity(
    manager: EntityManager,
    administrationId: string,
    subEntityCode: string | null,
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

    const prefix = String(row.documentNumberPrefix || row.code || 'DOC').toUpperCase();
    const padding = Math.max(Number(row.documentNumberPadding || 6), 3);
    const sequence = String(Number(row.documentNumberSequence || 1)).padStart(padding, '0');
    const year = new Date().getFullYear();

    const normalizedSubEntity = (subEntityCode || 'ENTITE').trim().toUpperCase();
    return `${prefix}-${normalizedSubEntity}-${sequence}-${year}`;
  }

  async sign(documentId: string, userId: string, signatureData: CreateSignatureDto) {
    const initialDocument = await this.documentRepository.findOne({
      where: { id: documentId },
      relations: ['qrcodes'],
    });

    if (!initialDocument) {
      throw new NotFoundException('Document not found');
    }

    const resolvedAdministrationId = await this.resolveAdministrationIdForDocument(initialDocument, userId);

    const externalSignatureResult = await this.callExternalSignatureProvider(
      initialDocument,
      userId,
      resolvedAdministrationId,
      signatureData,
    );

    let savedSignature: Signature | null = null;
    let assignedDocumentNumber = initialDocument.documentNumber || null;
    let assignedAdministrationId = resolvedAdministrationId || initialDocument.issuingAdministrationId || null;
    let shouldGenerateQrCode = false;

    await this.documentRepository.manager.transaction(async (manager) => {
      const documentRepository = manager.getRepository(Document);
      const signatureRepository = manager.getRepository(Signature);

      const document = await documentRepository.findOne({ where: { id: documentId } });
      if (!document) {
        throw new NotFoundException('Document not found');
      }

      const signatureBuffer = crypto.randomBytes(256);
      const signature = signatureRepository.create({
        documentId,
        signerId: userId,
        signature: signatureBuffer,
        certificate: signatureData.certificate,
        timestamp: new Date(),
        reason: signatureData.reason,
        location: signatureData.location,
        isValid: true,
        status: 'valid',
        signatureAlgorithm: 'SHA256',
      });

      savedSignature = await signatureRepository.save(signature);

      const administrationId = await this.resolveAdministrationIdForDocument(document, userId);

      if (!administrationId) {
        throw new BadRequestException(
          'Impossible de codifier le numéro du document signé: aucune administration émettrice liée au signataire/direction.',
        );
      }

      if (!document.documentNumber) {
        document.documentNumber = await this.generateNextDocumentNumberWithSubEntity(
          manager,
          administrationId,
          document.subEntityCode ?? null,
        );
        shouldGenerateQrCode = true;
      }

      document.issuingAdministrationId = administrationId;
      document.status = 'signed';
      document.signedAt = new Date();
      await documentRepository.save(document);

      assignedDocumentNumber = document.documentNumber;
      assignedAdministrationId = administrationId;
    });

    if (!savedSignature) {
      throw new BadRequestException('Signature could not be created');
    }

    let qrCode: any = null;
    if (shouldGenerateQrCode && assignedDocumentNumber) {
      qrCode = await this.qrcodeService.generate(documentId, userId, {
        documentId,
        type: 'signed-document',
        metadata: {
          documentNumber: assignedDocumentNumber,
          issuingAdministrationId: assignedAdministrationId,
          signedBy: userId,
          externalSignature: externalSignatureResult,
        },
        expiresAt: new Date(new Date().setFullYear(new Date().getFullYear() + 10)).toISOString(),
      });
    }

    return {
      ...savedSignature,
      documentNumber: assignedDocumentNumber,
      qrcode: qrCode,
    };
  }

  async verify(documentId: string, signatureId: string) {
    const signature = await this.signatureRepository.findOne({
      where: { id: signatureId, documentId },
    });

    if (!signature) {
      throw new NotFoundException('Signature not found');
    }

    return {
      isValid: signature.isValid,
      status: signature.status,
      signer: signature.signer,
      timestamp: signature.timestamp,
      algorithm: signature.signatureAlgorithm,
    };
  }

  async getSignatures(documentId: string) {
    const signatures = await this.signatureRepository.find({
      where: { documentId },
      relations: ['signer'],
    });

    return signatures;
  }

  async requestSignature(documentId: string, userId: string, requestData: RequestSignatureDto) {
    const document = await this.documentRepository.findOne({
      where: { id: documentId },
    });

    if (!document) {
      throw new NotFoundException('Document not found');
    }

    const expiryDate = requestData.expiryDate
      ? new Date(requestData.expiryDate)
      : new Date(Date.now() + 30 * 24 * 60 * 60 * 1000); // default: 30 days
    if (expiryDate < new Date()) {
      throw new BadRequestException('Expiry date must be in the future');
    }

    const requester = await this.userRepository.findOne({ where: { id: userId } });
    const recipient = await this.userRepository.findOne({ where: { email: requestData.recipientEmail } });

    if (!recipient) {
      throw new NotFoundException('Recipient user not found for this email');
    }

    const signatureRequest = this.signatureRequestRepository.create({
      documentId,
      requestedBy: userId,
      requestedTo: recipient.id,
      message: requestData.message,
      expiryDate,
    });

    const savedRequest = await this.signatureRequestRepository.save(signatureRequest);

    await this.notificationsService.sendEmailNotification({
      to: requestData.recipientEmail,
      subject: `Demande de signature: ${document.title}`,
      text:
        `${requester?.fullName || requester?.username || 'Un utilisateur'} vous a envoyé une demande de signature.` +
        `\nDocument: ${document.title}` +
        `\nMessage: ${requestData.message}` +
        `\nDate limite: ${expiryDate.toLocaleString('fr-FR')}`,
    });

    return savedRequest;
  }

  async deleteSignature(signatureId: string) {
    const signature = await this.signatureRepository.findOne({
      where: { id: signatureId },
    });

    if (!signature) {
      throw new NotFoundException('Signature not found');
    }

    signature.status = 'revoked';
    await this.signatureRepository.save(signature);

    return { message: 'Signature revoked successfully' };
  }

  async getPendingSignatures(userId: string) {
    return await this.signatureRequestRepository.find({
      where: {
        requestedTo: userId,
        status: 'pending',
      },
      relations: ['document', 'requester'],
    });
  }

  async respondToSignatureRequest(requestId: string, accepted: boolean) {
    const request = await this.signatureRequestRepository.findOne({
      where: { id: requestId },
      relations: ['document'],
    });

    if (!request) {
      throw new NotFoundException('Signature request not found');
    }

    request.status = accepted ? 'signed' : 'declined';
    request.respondedAt = new Date();
    const updated = await this.signatureRequestRepository.save(request);

    const requester = await this.userRepository.findOne({ where: { id: request.requestedBy } });
    const recipient = await this.userRepository.findOne({ where: { id: request.requestedTo } });
    if (requester?.email) {
      await this.notificationsService.sendEmailNotification({
        to: requester.email,
        subject: `Réponse à votre demande de signature: ${request.document?.title || 'Document'}`,
        text:
          `${recipient?.fullName || recipient?.username || 'Le destinataire'} a ` +
          `${accepted ? 'accepté/signé' : 'refusé'} votre demande de signature.` +
          `\nDocument: ${request.document?.title || request.documentId}`,
      });
    }

    return updated;
  }
}
