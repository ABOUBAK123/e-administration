import {
  Body,
  Controller,
  Delete,
  Get,
  Param,
  Post,
  Put,
  Request,
  UseGuards,
  UseInterceptors,
  UploadedFile,
  BadRequestException,
} from '@nestjs/common';
import { ApiBearerAuth, ApiOperation, ApiTags } from '@nestjs/swagger';
import { AuthGuard } from '@nestjs/passport';
import { FileInterceptor } from '@nestjs/platform-express';
import { diskStorage } from 'multer';
import { extname, join } from 'path';
import { existsSync, mkdirSync } from 'fs';
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
  UpdateAdministrationProfileDto,
  UpdateAdministrationUserDto,
  UpdateDirectionTypeDto,
  UpdateIssuingAdministrationDto,
  UpdateRecipientAdministrationDto,
  UpdateRoutingRuleDto,
  UpdateTemplateDto,
  UpdateTemplateVariableDto,
  UpsertAppSettingDto,
  UpsertNotificationConfigDto,
  UpsertSignatureProviderConfigDto,
} from './dto/administration.dto';
import { AdministrationService } from './administration.service';

@ApiTags('administration')
@ApiBearerAuth()
@UseGuards(AuthGuard('jwt'))
@Controller('administration')
export class AdministrationController {
  constructor(private readonly administrationService: AdministrationService) {}

  @Get('requested-acts')
  @ApiOperation({ summary: 'Get requested acts configured from administration tab' })
  findRequestedActs(@Request() req) {
    return this.administrationService.findRequestedActs(req?.user?.id || null);
  }

  @Post('requested-acts')
  @ApiOperation({ summary: 'Create requested act configuration' })
  createRequestedAct(@Body() dto: Record<string, unknown>, @Request() req) {
    return this.administrationService.createRequestedAct(dto, req?.user?.id || null);
  }

  @Put('requested-acts/:id')
  @ApiOperation({ summary: 'Update requested act configuration' })
  updateRequestedAct(
    @Param('id') id: string,
    @Body() dto: Record<string, unknown>,
    @Request() req
  ) {
    return this.administrationService.updateRequestedAct(id, dto, req?.user?.id || null);
  }

  @Delete('requested-acts/:id')
  @ApiOperation({ summary: 'Delete requested act configuration' })
  deleteRequestedAct(@Param('id') id: string, @Request() req) {
    return this.administrationService.deleteRequestedAct(id, req?.user?.id || null);
  }

  @Get('direction-types')
  @ApiOperation({ summary: 'Get direction types' })
  findDirectionTypes() {
    return this.administrationService.findDirectionTypes();
  }

  @Post('direction-types')
  @ApiOperation({ summary: 'Create direction type' })
  createDirectionType(@Body() dto: CreateDirectionTypeDto) {
    return this.administrationService.createDirectionType(dto);
  }

  @Put('direction-types/:id')
  @ApiOperation({ summary: 'Update direction type' })
  updateDirectionType(@Param('id') id: string, @Body() dto: UpdateDirectionTypeDto) {
    return this.administrationService.updateDirectionType(id, dto);
  }

  @Delete('direction-types/:id')
  @ApiOperation({ summary: 'Delete direction type' })
  deleteDirectionType(@Param('id') id: string) {
    return this.administrationService.deleteDirectionType(id);
  }

  @Get('app-settings')
  @ApiOperation({ summary: 'Get all global app settings (chat, onlyoffice, etc.)' })
  getAppSettings() {
    return this.administrationService.getAppSettings();
  }

  @Get('app-settings/:key')
  @ApiOperation({ summary: 'Get a single global app setting by key' })
  getAppSetting(@Param('key') key: string) {
    return this.administrationService.getAppSetting(key);
  }

  @Put('app-settings/:key')
  @ApiOperation({ summary: 'Create or update a global app setting by key' })
  upsertAppSetting(@Param('key') key: string, @Body() dto: UpsertAppSettingDto) {
    return this.administrationService.upsertAppSetting(key, dto);
  }

  @Put('app-settings')
  @ApiOperation({ summary: 'Bulk upsert multiple global app settings' })
  upsertAppSettings(
    @Body() body: { entries: Array<{ key: string; value: string | null; description?: string }> }
  ) {
    return this.administrationService.upsertAppSettings(body.entries || []);
  }

  @Post('app-settings/theme-background')
  @ApiOperation({ summary: 'Upload login/background image for theming settings' })
  @UseInterceptors(
    FileInterceptor('file', {
      storage: diskStorage({
        destination: (_req, _file, cb) => {
          const uploadDir = join(process.cwd(), 'storage', 'theming');
          if (!existsSync(uploadDir)) mkdirSync(uploadDir, { recursive: true });
          cb(null, uploadDir);
        },
        filename: (_req, file, cb) => {
          const unique = `${Date.now()}-${Math.round(Math.random() * 1e6)}`;
          cb(null, `theme-bg-${unique}${extname(file.originalname)}`);
        },
      }),
      fileFilter: (_req, file, cb) => {
        const allowed = /\.(png|jpg|jpeg|webp)$/i;
        if (!allowed.test(file.originalname)) {
          return cb(
            new BadRequestException('Only image files are allowed (png, jpg, jpeg, webp)'),
            false
          );
        }
        cb(null, true);
      },
      limits: { fileSize: 8 * 1024 * 1024 },
    })
  )
  uploadThemeBackground(@UploadedFile() file: Express.Multer.File) {
    if (!file) throw new BadRequestException('No file uploaded');
    return { imagePath: `/storage/theming/${file.filename}` };
  }

  @Get('templates')
  @ApiOperation({ summary: 'Get all document templates' })
  findTemplates() {
    return this.administrationService.findTemplates();
  }

  @Post('templates')
  @ApiOperation({ summary: 'Create document template (master file metadata)' })
  createTemplate(@Body() dto: CreateTemplateDto, @Request() req) {
    return this.administrationService.createTemplate(dto, req.user?.id);
  }

  @Put('templates/:id')
  @ApiOperation({ summary: 'Update template' })
  updateTemplate(@Param('id') id: string, @Body() dto: UpdateTemplateDto) {
    return this.administrationService.updateTemplate(id, dto);
  }

  @Delete('templates/:id')
  @ApiOperation({ summary: 'Delete template' })
  deleteTemplate(@Param('id') id: string) {
    return this.administrationService.deleteTemplate(id);
  }

  @Post('templates/:id/generate')
  @ApiOperation({ summary: 'Generate document text from template and variables {{...}}' })
  generateTemplateDocument(@Param('id') id: string, @Body() dto: GenerateTemplateDocumentDto) {
    return this.administrationService.generateTemplateDocument(id, dto);
  }

  @Get('templates/:templateId/variables')
  @ApiOperation({ summary: 'Get template dynamic variables and form fields' })
  findTemplateVariables(@Param('templateId') templateId: string) {
    return this.administrationService.findTemplateVariables(templateId);
  }

  @Post('templates/:templateId/variables')
  @ApiOperation({ summary: 'Create dynamic variable and form field config' })
  createTemplateVariable(
    @Param('templateId') templateId: string,
    @Body() dto: CreateTemplateVariableDto
  ) {
    return this.administrationService.createTemplateVariable(templateId, dto);
  }

  @Put('templates/:templateId/variables/:variableId')
  @ApiOperation({ summary: 'Update dynamic variable configuration' })
  updateTemplateVariable(
    @Param('templateId') templateId: string,
    @Param('variableId') variableId: string,
    @Body() dto: UpdateTemplateVariableDto
  ) {
    return this.administrationService.updateTemplateVariable(templateId, variableId, dto);
  }

  @Delete('templates/:templateId/variables/:variableId')
  @ApiOperation({ summary: 'Delete dynamic variable configuration' })
  deleteTemplateVariable(
    @Param('templateId') templateId: string,
    @Param('variableId') variableId: string
  ) {
    return this.administrationService.deleteTemplateVariable(templateId, variableId);
  }

  @Get('emitters')
  @ApiOperation({ summary: 'Get issuing administrations' })
  findIssuingAdministrations() {
    return this.administrationService.findIssuingAdministrations();
  }

  @Post('emitters')
  @ApiOperation({ summary: 'Create issuing administration' })
  createIssuingAdministration(@Body() dto: CreateIssuingAdministrationDto) {
    return this.administrationService.createIssuingAdministration(dto);
  }

  @Put('emitters/:id')
  @ApiOperation({ summary: 'Update issuing administration' })
  updateIssuingAdministration(
    @Param('id') id: string,
    @Body() dto: UpdateIssuingAdministrationDto
  ) {
    return this.administrationService.updateIssuingAdministration(id, dto);
  }

  @Delete('emitters/:id')
  @ApiOperation({ summary: 'Delete issuing administration' })
  deleteIssuingAdministration(@Param('id') id: string) {
    return this.administrationService.deleteIssuingAdministration(id);
  }

  @Post('emitters/:id/logo')
  @ApiOperation({ summary: 'Upload logo for an issuing administration' })
  @UseInterceptors(
    FileInterceptor('file', {
      storage: diskStorage({
        destination: (_req, _file, cb) => {
          const uploadDir = join(process.cwd(), 'storage', 'logos');
          if (!existsSync(uploadDir)) mkdirSync(uploadDir, { recursive: true });
          cb(null, uploadDir);
        },
        filename: (_req, file, cb) => {
          const unique = `${Date.now()}-${Math.round(Math.random() * 1e6)}`;
          cb(null, `admin-logo-${unique}${extname(file.originalname)}`);
        },
      }),
      fileFilter: (_req, file, cb) => {
        const allowed = /\.(png|jpg|jpeg|jfif|svg|webp)$/i;
        if (!allowed.test(file.originalname)) {
          return cb(
            new BadRequestException(
              'Only image files are allowed (png, jpg, jpeg, jfif, svg, webp)'
            ),
            false
          );
        }
        cb(null, true);
      },
      limits: { fileSize: 2 * 1024 * 1024 },
    })
  )
  async uploadAdministrationLogo(
    @Param('id') id: string,
    @UploadedFile() file: Express.Multer.File
  ) {
    if (!file) throw new BadRequestException('No file uploaded');
    const logoPath = `/storage/logos/${file.filename}`;
    return this.administrationService.updateAdministrationLogo(id, logoPath);
  }

  @Post('emitters/:administrationId/profiles')
  @ApiOperation({ summary: 'Create profile for issuing administration' })
  createAdministrationProfile(
    @Param('administrationId') administrationId: string,
    @Body() dto: CreateAdministrationProfileDto
  ) {
    return this.administrationService.createAdministrationProfile(administrationId, dto);
  }

  @Put('emitters/:administrationId/profiles/:profileId')
  @ApiOperation({ summary: 'Update profile for issuing administration' })
  updateAdministrationProfile(
    @Param('administrationId') administrationId: string,
    @Param('profileId') profileId: string,
    @Body() dto: UpdateAdministrationProfileDto
  ) {
    return this.administrationService.updateAdministrationProfile(administrationId, profileId, dto);
  }

  @Delete('emitters/:administrationId/profiles/:profileId')
  @ApiOperation({ summary: 'Delete profile for issuing administration' })
  deleteAdministrationProfile(
    @Param('administrationId') administrationId: string,
    @Param('profileId') profileId: string
  ) {
    return this.administrationService.deleteAdministrationProfile(administrationId, profileId);
  }

  @Post('emitters/:administrationId/users')
  @ApiOperation({ summary: 'Create user for issuing administration' })
  createAdministrationUser(
    @Param('administrationId') administrationId: string,
    @Body() dto: CreateAdministrationUserDto
  ) {
    return this.administrationService.createAdministrationUser(administrationId, dto);
  }

  @Put('emitters/:administrationId/users/:userId')
  @ApiOperation({ summary: 'Update user for issuing administration' })
  updateAdministrationUser(
    @Param('administrationId') administrationId: string,
    @Param('userId') userId: string,
    @Body() dto: UpdateAdministrationUserDto
  ) {
    return this.administrationService.updateAdministrationUser(administrationId, userId, dto);
  }

  @Delete('emitters/:administrationId/users/:userId')
  @ApiOperation({ summary: 'Delete user for issuing administration' })
  deleteAdministrationUser(
    @Param('administrationId') administrationId: string,
    @Param('userId') userId: string
  ) {
    return this.administrationService.deleteAdministrationUser(administrationId, userId);
  }

  @Get('recipients')
  @ApiOperation({ summary: 'Get recipient administrations (API/Email/LER)' })
  findRecipientAdministrations() {
    return this.administrationService.findRecipientAdministrations();
  }

  @Post('recipients')
  @ApiOperation({ summary: 'Create recipient administration' })
  createRecipientAdministration(@Body() dto: CreateRecipientAdministrationDto) {
    return this.administrationService.createRecipientAdministration(dto);
  }

  @Put('recipients/:id')
  @ApiOperation({ summary: 'Update recipient administration' })
  updateRecipientAdministration(
    @Param('id') id: string,
    @Body() dto: UpdateRecipientAdministrationDto
  ) {
    return this.administrationService.updateRecipientAdministration(id, dto);
  }

  @Post('recipients/:id/logo')
  @ApiOperation({ summary: 'Upload logo for a recipient administration' })
  @UseInterceptors(
    FileInterceptor('file', {
      storage: diskStorage({
        destination: (_req, _file, cb) => {
          const uploadDir = join(process.cwd(), 'storage', 'logos');
          if (!existsSync(uploadDir)) mkdirSync(uploadDir, { recursive: true });
          cb(null, uploadDir);
        },
        filename: (_req, file, cb) => {
          const unique = `${Date.now()}-${Math.round(Math.random() * 1e6)}`;
          cb(null, `recipient-logo-${unique}${extname(file.originalname)}`);
        },
      }),
      fileFilter: (_req, file, cb) => {
        const allowed = /\.(png|jpg|jpeg|jfif|svg|webp)$/i;
        if (!allowed.test(file.originalname)) {
          return cb(
            new BadRequestException(
              'Only image files are allowed (png, jpg, jpeg, jfif, svg, webp)'
            ),
            false
          );
        }
        cb(null, true);
      },
      limits: { fileSize: 2 * 1024 * 1024 },
    })
  )
  async uploadRecipientAdministrationLogo(
    @Param('id') id: string,
    @UploadedFile() file: Express.Multer.File
  ) {
    if (!file) throw new BadRequestException('No file uploaded');
    const logoPath = `/storage/logos/${file.filename}`;
    return this.administrationService.updateRecipientAdministrationLogo(id, logoPath);
  }

  @Delete('recipients/:id')
  @ApiOperation({ summary: 'Delete recipient administration' })
  deleteRecipientAdministration(@Param('id') id: string) {
    return this.administrationService.deleteRecipientAdministration(id);
  }

  @Get('routing-rules')
  @ApiOperation({ summary: 'Get routing rules' })
  findRoutingRules() {
    return this.administrationService.findRoutingRules();
  }

  @Post('routing-rules')
  @ApiOperation({ summary: 'Create routing rule (If doc X then send to Y)' })
  createRoutingRule(@Body() dto: CreateRoutingRuleDto) {
    return this.administrationService.createRoutingRule(dto);
  }

  @Put('routing-rules/:id')
  @ApiOperation({ summary: 'Update routing rule' })
  updateRoutingRule(@Param('id') id: string, @Body() dto: UpdateRoutingRuleDto) {
    return this.administrationService.updateRoutingRule(id, dto);
  }

  @Delete('routing-rules/:id')
  @ApiOperation({ summary: 'Delete routing rule' })
  deleteRoutingRule(@Param('id') id: string) {
    return this.administrationService.deleteRoutingRule(id);
  }

  @Get('signature-provider-config')
  @ApiOperation({
    summary: 'Get external signature API provider configuration (global or by administration)',
  })
  getSignatureProviderConfig(@Request() req) {
    const administrationId = req.query?.administrationId;
    return this.administrationService.getSignatureProviderConfig(administrationId);
  }

  @Get('signature-provider-config/list')
  @ApiOperation({ summary: 'List all signature provider configs across administrations' })
  listSignatureConfigs() {
    return this.administrationService.listAllSignatureConfigs();
  }

  @Get('emitters/:administrationId/signature-config')
  @ApiOperation({ summary: 'Get signature provider config for specific administration' })
  getAdministrationSignatureConfig(@Param('administrationId') administrationId: string) {
    return this.administrationService.getSignatureConfigByAdministration(administrationId);
  }

  @Put('signature-provider-config')
  @ApiOperation({ summary: 'Update external signature API provider configuration' })
  upsertSignatureProviderConfig(@Body() dto: UpsertSignatureProviderConfigDto) {
    return this.administrationService.upsertSignatureProviderConfig(dto);
  }

  @Put('emitters/:administrationId/signature-config')
  @ApiOperation({ summary: 'Update signature provider config for specific administration' })
  upsertAdministrationSignatureConfig(
    @Param('administrationId') administrationId: string,
    @Body() dto: UpsertSignatureProviderConfigDto
  ) {
    return this.administrationService.upsertSignatureProviderConfig({ ...dto, administrationId });
  }

  @Get('emitters/:administrationId/notification-config')
  @ApiOperation({ summary: 'Get email notification config for specific administration' })
  getAdministrationNotificationConfig(@Param('administrationId') administrationId: string) {
    return this.administrationService.getNotificationConfigByAdministration(administrationId);
  }

  @Put('emitters/:administrationId/notification-config')
  @ApiOperation({ summary: 'Update email notification config for specific administration' })
  upsertAdministrationNotificationConfig(
    @Param('administrationId') administrationId: string,
    @Body() dto: UpsertNotificationConfigDto
  ) {
    return this.administrationService.upsertNotificationConfigByAdministration(
      administrationId,
      dto
    );
  }

  @Get('emitters/:administrationId/admins')
  @ApiOperation({ summary: 'Get admin users for an administration' })
  getAdministrationAdmins(@Param('administrationId') administrationId: string) {
    return this.administrationService.getAdministrationAdmins(administrationId);
  }

  @Get('emitters/:administrationId/users')
  @ApiOperation({ summary: 'Get all users for an administration with optional role filter' })
  getAdministrationUsers(@Param('administrationId') administrationId: string, @Request() req) {
    const role = req.query?.role;
    return this.administrationService.getAdministrationUsers(administrationId, role);
  }
}
