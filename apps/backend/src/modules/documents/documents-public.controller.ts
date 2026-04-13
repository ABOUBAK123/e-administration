import {
  BadRequestException,
  Body,
  Controller,
  Get,
  Param,
  Post,
  Res,
  UploadedFiles,
  UseInterceptors,
} from '@nestjs/common';
import { ApiOperation, ApiTags } from '@nestjs/swagger';
import { Response } from 'express';
import { DocumentsService } from './documents.service';
import { diskStorage } from 'multer';
import { existsSync, mkdirSync } from 'fs';
import { extname, join } from 'path';
import { FilesInterceptor } from '@nestjs/platform-express';
import { randomUUID } from 'crypto';

@ApiTags('documents-public')
@Controller('documents/public')
export class DocumentsPublicController {
  constructor(private readonly documentsService: DocumentsService) {}

  private static ensureUploadDir(): string {
    const uploadDir = join(process.cwd(), 'uploads');
    if (!existsSync(uploadDir)) {
      mkdirSync(uploadDir, { recursive: true });
    }
    return uploadDir;
  }

  private static readonly publicUploadStorage = diskStorage({
    destination: (_req, _file, cb) => {
      cb(null, DocumentsPublicController.ensureUploadDir());
    },
    filename: (_req, file, cb) => {
      const extension = extname(file.originalname || '').toLowerCase() || '.bin';
      const unique = randomUUID();
      cb(null, `public-act-request-${unique}${extension}`);
    },
  });

  private static readonly allowedExtensions = new Set([
    '.pdf',
    '.jpg',
    '.jpeg',
    '.png',
    '.gif',
    '.webp',
    '.doc',
    '.docx',
    '.xls',
    '.xlsx',
    '.ppt',
    '.pptx',
    '.txt',
  ]);

  private static readonly allowedMimeTypes = new Set([
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain',
  ]);

  @Get('act-requests/emitters')
  @ApiOperation({ summary: 'List issuing administrations exposed to citizens for act requests' })
  listPublicEmitters() {
    return this.documentsService.listPublicEmitterAdministrations();
  }

  @Get('act-requests/emitters/:emitterAdministrationId')
  @ApiOperation({ summary: 'List requested acts for a specific issuing administration' })
  listEmitterRequestedActs(@Param('emitterAdministrationId') emitterAdministrationId: string) {
    return this.documentsService.listPublicRequestedActsByEmitter(emitterAdministrationId);
  }

  @Post('act-requests/submit')
  @ApiOperation({ summary: 'Submit a public act request with required files' })
  @UseInterceptors(
    FilesInterceptor('files', 10, {
      storage: DocumentsPublicController.publicUploadStorage,
      limits: { fileSize: 20 * 1024 * 1024 },
      fileFilter: (_req, file, cb) => {
        const extension = extname(file.originalname || '').toLowerCase();
        const extAllowed = DocumentsPublicController.allowedExtensions.has(extension);
        const mimeAllowed = DocumentsPublicController.allowedMimeTypes.has(
          String(file.mimetype || '').toLowerCase()
        );

        if (extAllowed && mimeAllowed) {
          cb(null, true);
          return;
        }

        (cb as any)(new BadRequestException('Type de fichier non autorise.'), false);
      },
    })
  )
  submitPublicActRequest(
    @Body()
    body: {
      emitterAdministrationId?: string;
      requestedActId?: string;
      applicantFullName?: string;
      applicantEmail?: string;
      applicantPhone?: string;
      note?: string;
      fileLabels?: string | string[];
      applicantFieldValues?: string;
    },
    @UploadedFiles() files: Express.Multer.File[]
  ) {
    const rawFileLabels = body?.fileLabels;
    const fileLabels = Array.isArray(rawFileLabels)
      ? rawFileLabels.map((item) => String(item || '').trim())
      : String(rawFileLabels || '').trim()
        ? [String(rawFileLabels || '').trim()]
        : [];

    let applicantFieldValues: Record<string, string> = {};
    const rawApplicantFieldValues = String(body?.applicantFieldValues || '').trim();
    if (rawApplicantFieldValues) {
      try {
        const parsed = JSON.parse(rawApplicantFieldValues);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
          applicantFieldValues = Object.entries(parsed).reduce(
            (acc, [key, value]) => {
              const normalizedKey = String(key || '').trim();
              if (!normalizedKey) return acc;
              acc[normalizedKey] = String(value || '').trim();
              return acc;
            },
            {} as Record<string, string>
          );
        }
      } catch {
        throw new BadRequestException('Le format des champs usager est invalide.');
      }
    }

    return this.documentsService.createPublicActRequestSubmission({
      emitterAdministrationId: String(body?.emitterAdministrationId || ''),
      requestedActId: String(body?.requestedActId || ''),
      applicantFullName: String(body?.applicantFullName || ''),
      applicantEmail: String(body?.applicantEmail || ''),
      applicantPhone: String(body?.applicantPhone || ''),
      note: String(body?.note || ''),
      fileLabels,
      applicantFieldValues,
      attachments: Array.isArray(files) ? files : [],
    });
  }

  @Get(':id/digital-version')
  @ApiOperation({ summary: 'Public access to the digital version of a signed document' })
  async getDigitalVersion(@Param('id') id: string, @Res() res: Response) {
    const { absolutePath, fileName, mimeType } =
      await this.documentsService.resolveDigitalVersion(id);

    res.setHeader('Content-Type', mimeType);
    res.setHeader('Content-Disposition', `inline; filename="${fileName}"`);
    return res.sendFile(absolutePath);
  }
}
