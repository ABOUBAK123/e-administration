import {
  Controller,
  Get,
  Post,
  Put,
  Delete,
  Param,
  Body,
  Query,
  Request,
  UseGuards,
  UseInterceptors,
  UploadedFiles,
  BadRequestException,
} from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { AuthGuard } from '@nestjs/passport';
import { FileFieldsInterceptor } from '@nestjs/platform-express';
import { diskStorage } from 'multer';
import { extname, join } from 'path';
import { existsSync, mkdirSync } from 'fs';
import { UsersService } from './users.service';
import { CreateUserDto, UpdateCurrentUserDto, UpdateUserDto } from './dto/user.dto';

@ApiTags('users')
@ApiBearerAuth()
@UseGuards(AuthGuard('jwt'))
@Controller('users')
export class UsersController {
  constructor(private readonly usersService: UsersService) {}

  private static ensureAvatarUploadDir() {
    const uploadDir = join(process.cwd(), 'storage', 'avatars');
    if (!existsSync(uploadDir)) {
      mkdirSync(uploadDir, { recursive: true });
    }
    return uploadDir;
  }

  @Get()
  @ApiOperation({ summary: 'Get all users' })
  findAll(@Request() req, @Query('page') page: number = 1, @Query('limit') limit: number = 10) {
    return this.usersService.findAll(req.user.id, page, limit);
  }

  @Get('search')
  @ApiOperation({ summary: 'Search users' })
  search(@Request() req, @Query('query') query: string) {
    return this.usersService.search(req.user.id, query);
  }

  @Get('profile')
  @ApiOperation({ summary: 'Get current user profile' })
  getProfile(@Request() req) {
    return this.usersService.findOne(req.user.id);
  }

  @Get('profile/permissions')
  @ApiOperation({ summary: 'Get effective menu permissions of current user' })
  getProfilePermissions(@Request() req) {
    return this.usersService.getCurrentUserMenuPermissions(req.user.id);
  }

  @Get('profile/theme')
  @ApiOperation({ summary: 'Get effective theme of current user based on linked administration' })
  getProfileTheme(@Request() req) {
    return this.usersService.getCurrentUserTheme(req.user.id);
  }

  @Put('profile')
  @ApiOperation({ summary: 'Update current user profile' })
  updateProfile(@Request() req, @Body() updateCurrentUserDto: UpdateCurrentUserDto) {
    return this.usersService.updateCurrentUser(req.user.id, updateCurrentUserDto);
  }

  @Put('profile/avatar')
  @ApiOperation({ summary: 'Upload current user avatar' })
  @UseInterceptors(
    FileFieldsInterceptor(
      [
        { name: 'file', maxCount: 1 },
        { name: 'avatar', maxCount: 1 },
      ],
      {
        storage: diskStorage({
          destination: () => UsersController.ensureAvatarUploadDir(),
          filename: (_, file, cb) => {
            const extension = extname(file.originalname || '').toLowerCase();
            const safeExt =
              extension && ['.png', '.jpg', '.jpeg', '.webp'].includes(extension)
                ? extension
                : '.png';
            cb(null, `avatar_${Date.now()}_${Math.random().toString(36).slice(2, 8)}${safeExt}`);
          },
        }),
        limits: { fileSize: 5 * 1024 * 1024 },
        fileFilter: (_, file, cb) => {
          if (!file.mimetype?.startsWith('image/')) {
            cb(new BadRequestException('Only image files are allowed'), false);
            return;
          }
          cb(null, true);
        },
      }
    )
  )
  uploadAvatar(
    @Request() req,
    @UploadedFiles() files: { file?: Express.Multer.File[]; avatar?: Express.Multer.File[] }
  ) {
    const file = files?.file?.[0] || files?.avatar?.[0];
    if (!file) {
      throw new BadRequestException('Avatar file is required');
    }

    return this.usersService.updateCurrentUserAvatar(req.user.id, file.filename);
  }

  @Put(':id/avatar')
  @ApiOperation({ summary: 'Upload avatar for a specific user' })
  @UseInterceptors(
    FileFieldsInterceptor(
      [
        { name: 'file', maxCount: 1 },
        { name: 'avatar', maxCount: 1 },
      ],
      {
        storage: diskStorage({
          destination: () => UsersController.ensureAvatarUploadDir(),
          filename: (_, file, cb) => {
            const extension = extname(file.originalname || '').toLowerCase();
            const safeExt =
              extension && ['.png', '.jpg', '.jpeg', '.webp'].includes(extension)
                ? extension
                : '.png';
            cb(null, `avatar_${Date.now()}_${Math.random().toString(36).slice(2, 8)}${safeExt}`);
          },
        }),
        limits: { fileSize: 5 * 1024 * 1024 },
        fileFilter: (_, file, cb) => {
          if (!file.mimetype?.startsWith('image/')) {
            cb(new BadRequestException('Only image files are allowed'), false);
            return;
          }
          cb(null, true);
        },
      }
    )
  )
  uploadUserAvatar(
    @Param('id') id: string,
    @UploadedFiles() files: { file?: Express.Multer.File[]; avatar?: Express.Multer.File[] }
  ) {
    const file = files?.file?.[0] || files?.avatar?.[0];
    if (!file) {
      throw new BadRequestException('Avatar file is required');
    }

    return this.usersService.updateUserAvatar(id, file.filename);
  }

  @Get('signataires')
  @ApiOperation({ summary: 'Get signataires from the same administration as the current user' })
  getSignataires(@Request() req) {
    return this.usersService.findSignatairesSameAdministration(req.user.id);
  }

  @Get(':id')
  @ApiOperation({ summary: 'Get user by ID' })
  findOne(@Param('id') id: string) {
    return this.usersService.findOne(id);
  }

  @Post()
  @ApiOperation({ summary: 'Create new user' })
  create(@Request() req, @Body() createUserDto: CreateUserDto) {
    return this.usersService.create(req.user.id, createUserDto);
  }

  @Put(':id')
  @ApiOperation({ summary: 'Update user' })
  update(@Request() req, @Param('id') id: string, @Body() updateUserDto: UpdateUserDto) {
    return this.usersService.update(req.user.id, id, updateUserDto);
  }

  @Put(':id/role')
  @ApiOperation({ summary: 'Update user role' })
  updateRole(@Param('id') id: string, @Body() body: { role: string }) {
    return this.usersService.updateRole(id, body.role);
  }

  @Put(':id/status')
  @ApiOperation({ summary: 'Update user status' })
  updateStatus(@Param('id') id: string, @Body() body: { status: string }) {
    return this.usersService.updateStatus(id, body.status);
  }

  @Delete(':id')
  @ApiOperation({ summary: 'Delete user' })
  delete(@Param('id') id: string) {
    return this.usersService.delete(id);
  }
}
