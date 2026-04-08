import {
  Controller,
  Get,
  Post,
  Put,
  Delete,
  Param,
  Body,
  UseInterceptors,
  UploadedFile,
  Query,
  BadRequestException,
  ForbiddenException,
  Request,
  UseGuards,
} from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { Options as MulterOptions } from 'multer';
import { diskStorage } from 'multer';
import { extname, join } from 'path';
import { existsSync, mkdirSync } from 'fs';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { AuthGuard } from '@nestjs/passport';
import { DocumentsService } from './documents.service';
import {
  CreateDocumentDto,
  UpdateDocumentDto,
  ShareDocumentDto,
  UpdateDocumentFavoriteDto,
  UpdateDocumentLabelCodesDto,
} from './dto/document.dto';

@ApiTags('documents')
@ApiBearerAuth()
@UseGuards(AuthGuard('jwt'))
@Controller('documents')
export class DocumentsController {
  constructor(private readonly documentsService: DocumentsService) {}

  private static ensureUploadDir(): string {
    const uploadDir = join(process.cwd(), 'uploads');
    if (!existsSync(uploadDir)) {
      mkdirSync(uploadDir, { recursive: true });
    }
    return uploadDir;
  }

  private static documentStorage = diskStorage({
    destination: (_req: any, _file: any, cb: any) => {
      cb(null, DocumentsController.ensureUploadDir());
    },
    filename: (_, file, cb) => {
      const extension = extname(file.originalname || '').toLowerCase();
      const safeExt = extension || '.bin';
      const unique = `${Date.now()}-${Math.round(Math.random() * 1e6)}`;
      cb(null, `document-${unique}${safeExt}`);
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
    'application/octet-stream',
  ]);

  private static readonly documentUploadOptions: MulterOptions = {
    storage: DocumentsController.documentStorage,
    limits: { fileSize: 20 * 1024 * 1024 },
    fileFilter: (_, file, cb) => {
      const extension = extname(file.originalname || '').toLowerCase();
      const extAllowed = DocumentsController.allowedExtensions.has(extension);
      const mimeAllowed = DocumentsController.allowedMimeTypes.has(String(file.mimetype || '').toLowerCase());

      if (extAllowed && mimeAllowed) {
        cb(null, true);
        return;
      }

      (cb as any)(new Error('Type de fichier non autorisé.'), false);
    },
  };

  @Get()
  @ApiOperation({ summary: 'Get all documents' })
  findAll(
    @Query('page') page: number = 1,
    @Query('limit') limit: number = 10,
    @Query('search') search?: string,
    @Request() req?,
  ) {
    return this.documentsService.findAll(page, limit, search, req?.user?.id);
  }

  @Get('my-documents')
  @ApiOperation({ summary: 'Get own documents' })
  getMyDocuments(@Request() req) {
    return this.documentsService.getDocumentsByOwner(req.user.id);
  }

  @Get('preferences')
  @ApiOperation({ summary: 'Get current user document preferences (favorites and label codes)' })
  getMyDocumentPreferences(@Request() req) {
    return this.documentsService.getUserDocumentPreferences(req.user.id);
  }

  @Get('reception')
  @ApiOperation({ summary: 'Get documents received by recipient administrations via application' })
  getReceptionDocuments(
    @Query('page') page: number = 1,
    @Query('limit') limit: number = 10,
    @Query('search') search?: string,
    @Request() req?,
  ) {
    return this.documentsService.getReceptionDocuments(req?.user?.id, page, limit, search);
  }

  @Post('reception/:id/zip-downloaded')
  @ApiOperation({ summary: 'Persist ZIP downloaded state for a reception document' })
  markReceptionZipDownloaded(@Param('id') id: string, @Request() req?) {
    return this.documentsService.markReceptionZipDownloaded(req?.user?.id, id);
  }

  @Get('act-requests')
  @ApiOperation({ summary: 'Get act requests filtered by recipient administration and user sub-entity' })
  getActRequests(
    @Query('page') page: number = 1,
    @Query('limit') limit: number = 10,
    @Query('search') search?: string,
    @Request() req?,
  ) {
    return this.documentsService.getActRequests(req?.user?.id, page, limit, search);
  }

  @Get('act-requests/:id/details')
  @ApiOperation({ summary: 'Get one act-request details with required vs received files' })
  getActRequestDetails(@Param('id') id: string, @Request() req?) {
    return this.documentsService.getActRequestDetails(req?.user?.id, id);
  }

  @Post('act-requests/:id/start-processing')
  @ApiOperation({ summary: 'Mark act request as in-progress and notify applicant by email' })
  startActRequestProcessing(@Param('id') id: string, @Request() req?) {
    return this.documentsService.startActRequestProcessing(req?.user?.id, id);
  }

  @Post('act-requests/:id/mark-treated')
  @ApiOperation({ summary: 'Mark act request as treated after signature and transmission' })
  markActRequestAsTreated(@Param('id') id: string, @Request() req?) {
    return this.documentsService.markActRequestAsTreated(req?.user?.id, id);
  }

  @Get('search')
  @ApiOperation({ summary: 'Search documents' })
  searchDocuments(@Query('query') query: string) {
    if (!query || query.length < 2) {
      throw new BadRequestException('Search query must be at least 2 characters');
    }
    return this.documentsService.searchDocuments(query);
  }

  @Get('audit/invalid-file-paths')
  @ApiOperation({ summary: 'Audit documents with invalid filePath values' })
  auditInvalidFilePaths(@Query('limit') limit: number = 500, @Request() req) {
    if (req?.user?.role !== 'admin') {
      throw new ForbiddenException('Admin access required');
    }
    return this.documentsService.auditInvalidFilePaths(limit);
  }

  @Post('audit/repair-invalid-file-paths')
  @ApiOperation({ summary: 'Repair invalid document filePath values in bulk using valid versions' })
  repairInvalidFilePaths(@Query('limit') limit: number = 500, @Request() req) {
    if (req?.user?.role !== 'admin') {
      throw new ForbiddenException('Admin access required');
    }
    return this.documentsService.repairInvalidFilePaths(limit);
  }

  @Get(':id')
  @ApiOperation({ summary: 'Get document by ID' })
  findOne(@Param('id') id: string) {
    return this.documentsService.findOne(id);
  }

  @Get(':id/versions')
  @ApiOperation({ summary: 'Get document versions' })
  getVersions(@Param('id') id: string) {
    return this.documentsService.getVersions(id);
  }

  @Post()
  @ApiOperation({ summary: 'Create new document' })
  create(@Body() createDocumentDto: CreateDocumentDto, @Request() req) {
    return this.documentsService.create(req.user.id, createDocumentDto);
  }

  @Post('new/upload')
  @UseInterceptors(FileInterceptor('file', DocumentsController.documentUploadOptions))
  @ApiOperation({ summary: 'Upload a new document file' })
  uploadNew(@UploadedFile() file: Express.Multer.File, @Request() req) {
    if (!file) {
      throw new BadRequestException('No file provided');
    }
    const body = req?.body || {};
    return this.documentsService.upload(req.user.id, file, {
      generatedFromSharedTemplate: String(body.generatedFromSharedTemplate || '').toLowerCase() === 'true',
      subEntityCode: body.subEntityCode,
      title: body.title,
    });
  }

  @Post(':id/upload')
  @UseInterceptors(FileInterceptor('file', DocumentsController.documentUploadOptions))
  @ApiOperation({ summary: 'Upload document file' })
  upload(@Param('id') id: string, @UploadedFile() file: Express.Multer.File, @Request() req) {
    if (!file) {
      throw new BadRequestException('No file provided');
    }
    const body = req?.body || {};
    return this.documentsService.upload(req.user.id, file, {
      generatedFromSharedTemplate: String(body.generatedFromSharedTemplate || '').toLowerCase() === 'true',
      subEntityCode: body.subEntityCode,
      title: body.title,
    });
  }

  @Post(':id/version')
  @UseInterceptors(FileInterceptor('file', DocumentsController.documentUploadOptions))
  @ApiOperation({ summary: 'Create new document version' })
  createNewVersion(
    @Param('id') id: string,
    @UploadedFile() file: Express.Multer.File,
    @Request() req,
  ) {
    if (!file) {
      throw new BadRequestException('No file provided');
    }
    return this.documentsService.createNewVersion(id, req.user.id, file);
  }

  @Put(':id')
  @ApiOperation({ summary: 'Update document' })
  update(@Param('id') id: string, @Body() updateDocumentDto: UpdateDocumentDto, @Request() req) {
    return this.documentsService.update(id, updateDocumentDto);
  }

  @Post(':id/share')
  @ApiOperation({ summary: 'Share document and notify recipient by email' })
  share(@Param('id') id: string, @Body() shareDocumentDto: ShareDocumentDto, @Request() req) {
    return this.documentsService.share(id, req.user.id, shareDocumentDto);
  }

  @Put(':id/preferences/favorite')
  @ApiOperation({ summary: 'Update current user favorite flag for a document' })
  updateFavorite(
    @Param('id') id: string,
    @Body() favoriteDto: UpdateDocumentFavoriteDto,
    @Request() req,
  ) {
    return this.documentsService.updateDocumentFavorite(req.user.id, id, favoriteDto.isFavorite);
  }

  @Put(':id/preferences/labels')
  @ApiOperation({ summary: 'Update current user label codes for a document' })
  updateLabelCodes(
    @Param('id') id: string,
    @Body() labelCodesDto: UpdateDocumentLabelCodesDto,
    @Request() req,
  ) {
    return this.documentsService.updateDocumentLabelCodes(req.user.id, id, labelCodesDto.codes);
  }

  @Delete(':id')
  @ApiOperation({ summary: 'Delete document' })
  delete(@Param('id') id: string, @Request() req) {
    return this.documentsService.delete(id);
  }
}
