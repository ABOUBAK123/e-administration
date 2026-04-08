import { Injectable, NotFoundException, BadRequestException } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { ConfigService } from '@nestjs/config';
import { Repository } from 'typeorm';
import * as QRCode from 'qrcode';
import * as crypto from 'crypto';
import { QrCode } from './qrcode.entity';
import { Document } from '../documents/document.entity';
import { GenerateQrCodeDto } from './dto/qrcode.dto';
import { IssuingAdministration } from '../administration/entities/issuing-administration.entity';
import { Signature } from '../signatures/signature.entity';

@Injectable()
export class QrcodeService {
  constructor(
    @InjectRepository(QrCode)
    private qrcodeRepository: Repository<QrCode>,
    @InjectRepository(Document)
    private documentRepository: Repository<Document>,
    private configService: ConfigService,
    @InjectRepository(IssuingAdministration)
    private administrationRepository: Repository<IssuingAdministration>,
    @InjectRepository(Signature)
    private signatureRepository: Repository<Signature>,
  ) {}

  private getApiBaseUrl(): string {
    const configuredUrl = this.configService.get<string>('app.url') || 'http://localhost:3000';
    return configuredUrl.replace(/\/$/, '');
  }

  private getFrontendBaseUrl(): string {
    const configuredUrl = this.configService.get<string>('app.frontendUrl') || 'http://localhost:5173';
    return configuredUrl.replace(/\/$/, '');
  }

  extractVerificationCode(rawValue: string): string {
    if (!rawValue || typeof rawValue !== 'string') {
      throw new BadRequestException('Invalid QR data payload');
    }

    const trimmed = rawValue.trim();

    // Support plain verification code payload.
    if (/^[a-f0-9]{32}$/i.test(trimmed)) {
      return trimmed;
    }

    // Support direct scan URL payload.
    const scanPathMatch = trimmed.match(/\/qrcode\/scan\/([a-f0-9]{32})/i);
    if (scanPathMatch?.[1]) {
      return scanPathMatch[1];
    }

    // Support JSON payload from older QR schema.
    try {
      const parsed = JSON.parse(trimmed);
      if (parsed?.verificationCode && /^[a-f0-9]{32}$/i.test(parsed.verificationCode)) {
        return parsed.verificationCode;
      }
    } catch {
      // ignore parse errors and fall through
    }

    throw new BadRequestException('Unable to extract verification code from QR payload');
  }

  async generate(documentId: string, userId: string, generateData: GenerateQrCodeDto) {
    const document = await this.documentRepository.findOne({
      where: { id: documentId },
    });

    if (!document) {
      throw new NotFoundException('Document not found');
    }

    const expiresAt = new Date(generateData.expiresAt);
    if (expiresAt < new Date()) {
      throw new BadRequestException('Expiry date must be in the future');
    }

    // Generate unique verification code
    const verificationCode = crypto.randomBytes(16).toString('hex');

    const apiBaseUrl = this.getApiBaseUrl();
    const verificationUrl = `${apiBaseUrl}/api/v1/qrcode/scan/${verificationCode}`;
    const digitalVersionUrl = `${apiBaseUrl}/api/v1/documents/public/${documentId}/digital-version`;

    // The QR payload is a URL so camera scanners can open it directly.
    const qrcodeImage = await QRCode.toDataURL(verificationUrl);

    // Save QR code record
    const qrcode = this.qrcodeRepository.create({
      documentId,
      data: qrcodeImage,
      metadata: {
        ...(generateData.metadata || {}),
        verificationUrl,
        digitalVersionUrl,
      },
      verificationCode,
      expiresAt,
      createdBy: userId,
      status: 'active',
      scanCount: 0,
    });

    return await this.qrcodeRepository.save(qrcode);
  }

  async verify(verificationCode: string) {
    const qrcode = await this.qrcodeRepository.findOne({
      where: { verificationCode },
      relations: ['document'],
    });

    if (!qrcode) {
      throw new NotFoundException('QR code not found');
    }

    if (qrcode.status === 'revoked') {
      throw new BadRequestException('QR code has been revoked');
    }

    if (new Date() > qrcode.expiresAt) {
      qrcode.status = 'expired';
      await this.qrcodeRepository.save(qrcode);
      throw new BadRequestException('QR code has expired');
    }

    // Increment scan count
    qrcode.scanCount += 1;
    await this.qrcodeRepository.save(qrcode);

    return {
      verified: true,
      documentId: qrcode.documentId,
      document: qrcode.document,
      timestamp: new Date(),
      scanCount: qrcode.scanCount,
    };
  }

  async resolveDigitalVersionByVerificationCode(verificationCode: string) {
    const qrcode = await this.qrcodeRepository.findOne({
      where: { verificationCode },
      relations: ['document'],
    });

    if (!qrcode) {
      throw new NotFoundException('QR code not found');
    }

    if (qrcode.status === 'revoked') {
      throw new BadRequestException('QR code has been revoked');
    }

    if (new Date() > qrcode.expiresAt) {
      qrcode.status = 'expired';
      await this.qrcodeRepository.save(qrcode);
      throw new BadRequestException('QR code has expired');
    }

    qrcode.scanCount += 1;
    await this.qrcodeRepository.save(qrcode);

    if (!qrcode.document) {
      throw new NotFoundException('Linked document not found');
    }

    const documentNumber = String(qrcode.document.documentNumber || '').trim() || null;
    const digitalVersionUrl =
      qrcode.metadata?.digitalVersionUrl ||
      `${this.getApiBaseUrl()}/api/v1/documents/public/${qrcode.documentId}/digital-version`;

    const verificationPageUrl = documentNumber
      ? `${this.getFrontendBaseUrl()}/verify?documentNumber=${encodeURIComponent(documentNumber)}&autoOpen=1`
      : digitalVersionUrl;

    return {
      documentId: qrcode.documentId,
      documentNumber,
      digitalVersionUrl,
      verificationPageUrl,
      scanCount: qrcode.scanCount,
    };
  }

  async getQrCodesForDocument(documentId: string) {
    const qrcodes = await this.qrcodeRepository.find({
      where: { documentId },
    });

    return qrcodes;
  }

  async revoke(qrcodeId: string) {
    const qrcode = await this.qrcodeRepository.findOne({
      where: { id: qrcodeId },
    });

    if (!qrcode) {
      throw new NotFoundException('QR code not found');
    }

    qrcode.status = 'revoked';
    await this.qrcodeRepository.save(qrcode);

    return { message: 'QR code revoked successfully' };
  }

  async cleanupExpiredQrcodes() {
    const expiredQrcodes = await this.qrcodeRepository
      .createQueryBuilder('qr_code')
      .where('qr_code.status = :status', { status: 'active' })
      .andWhere('qr_code.expiresAt < NOW()')
      .getMany();

    for (const qrcode of expiredQrcodes) {
      qrcode.status = 'expired';
    }

    await this.qrcodeRepository.save(expiredQrcodes);
  }

  async verifyByDocumentNumber(documentNumber: string) {
    const normalised = documentNumber.trim().toUpperCase();

    const document = await this.documentRepository.findOne({
      where: { documentNumber: normalised },
      relations: ['owner', 'signatures'],
    });

    if (!document) {
      throw new NotFoundException('Aucun document trouve avec ce numero');
    }

    const signatures = await this.signatureRepository.find({
      where: { documentId: document.id },
      relations: ['signer'],
      order: { timestamp: 'DESC' },
    });

    let administration: IssuingAdministration | null = null;
    if (document.issuingAdministrationId) {
      administration = await this.administrationRepository.findOne({
        where: { id: document.issuingAdministrationId },
      });
    }

    const qrcode = await this.qrcodeRepository.findOne({
      where: { documentId: document.id, status: 'active' },
      order: { createdAt: 'DESC' } as any,
    });

    const apiBaseUrl = this.getApiBaseUrl();
    const pdfUrl = `${apiBaseUrl}/api/v1/documents/public/${document.id}/digital-version`;

    return {
      authentic: document.status === 'signed' && signatures.length > 0,
      documentNumber: document.documentNumber,
      documentId: document.id,
      title: document.title,
      description: document.description,
      status: document.status,
      signedAt: document.signedAt,
      subEntityCode: (document as any).subEntityCode ?? null,
      issuingAdministration: administration
        ? { id: administration.id, name: administration.name, code: administration.code }
        : null,
      signatures: signatures.map((sig) => ({
        id: sig.id,
        signerName: (sig as any).signer?.fullName || (sig as any).signer?.username || 'Inconnu',
        signerEmail: (sig as any).signer?.email || null,
        timestamp: sig.timestamp,
        isValid: sig.isValid,
        status: sig.status,
        reason: sig.reason,
        location: sig.location,
      })),
      pdfUrl,
      qrcodeVerificationCode: qrcode?.verificationCode ?? null,
    };
  }
}
