import { Controller, Post, Body, UseGuards, Get, Request, Query, Response } from '@nestjs/common';
import { ApiTags, ApiOperation, ApiBearerAuth } from '@nestjs/swagger';
import { Response as ExpressResponse } from 'express';
import { AuthService } from './auth.service';
import { CreateUserDto, LoginDto } from '../users/dto/user.dto';
import { AuthGuard } from '@nestjs/passport';
import { ForgotPasswordDto, ResetPasswordDto } from './dto/password-reset.dto';

@ApiTags('auth')
@Controller('auth')
export class AuthController {
  constructor(private readonly authService: AuthService) {}

  private setRefreshCookie(res: ExpressResponse, refreshToken: string) {
    const isProduction = (process.env.NODE_ENV || '').toLowerCase() === 'production';
    res.cookie('refreshToken', refreshToken, {
      httpOnly: true,
      secure: isProduction,
      sameSite: isProduction ? 'none' : 'lax',
      maxAge: 7 * 24 * 60 * 60 * 1000,
      path: '/',
    });
  }

  private extractRefreshTokenFromCookie(req: any): string | null {
    const rawCookie = String(req?.headers?.cookie || '');
    if (!rawCookie) return null;
    const entries = rawCookie.split(';').map((part) => part.trim());
    const match = entries.find((entry) => entry.startsWith('refreshToken='));
    if (!match) return null;
    return decodeURIComponent(match.slice('refreshToken='.length));
  }

  @Post('register')
  @ApiOperation({ summary: 'User registration' })
  async register(
    @Body() createUserDto: CreateUserDto,
    @Response({ passthrough: true }) res: ExpressResponse
  ) {
    const authResult = await this.authService.register(createUserDto);
    this.setRefreshCookie(res, authResult.refreshToken);
    return authResult;
  }

  @Post('register-invited')
  @ApiOperation({ summary: 'Invited user registration with pending activation' })
  async registerInvited(@Body() createUserDto: CreateUserDto) {
    return this.authService.registerInvited(createUserDto);
  }

  @Post('login')
  @ApiOperation({ summary: 'User login' })
  async login(@Body() loginDto: LoginDto, @Response({ passthrough: true }) res: ExpressResponse) {
    const authResult = await this.authService.login(loginDto);
    this.setRefreshCookie(res, authResult.refreshToken);
    return authResult;
  }

  @Post('refresh')
  @ApiOperation({ summary: 'Refresh access token' })
  async refresh(
    @Request() req,
    @Body() body: { refreshToken?: string },
    @Response({ passthrough: true }) res: ExpressResponse
  ) {
    const fromBody = body?.refreshToken?.trim() || null;
    const fromCookie = this.extractRefreshTokenFromCookie(req);
    const token = fromBody || fromCookie;
    const authResult = await this.authService.refreshTokens(token || '');
    this.setRefreshCookie(res, authResult.refreshToken);
    return authResult;
  }

  @Post('forgot-password')
  @ApiOperation({ summary: 'Request a password reset link' })
  async forgotPassword(@Body() forgotPasswordDto: ForgotPasswordDto) {
    return this.authService.forgotPassword(forgotPasswordDto);
  }

  @Post('reset-password')
  @ApiOperation({ summary: 'Reset password using reset token' })
  async resetPassword(@Body() resetPasswordDto: ResetPasswordDto) {
    return this.authService.resetPassword(resetPasswordDto);
  }

  @Get('me')
  @UseGuards(AuthGuard('jwt'))
  @ApiBearerAuth()
  @ApiOperation({ summary: 'Get current user profile' })
  async getMe(@Request() req) {
    return {
      id: req.user.id,
      email: req.user.email,
      username: req.user.username,
      fullName: req.user.fullName,
      role: req.user.role,
      avatar: req.user.avatar,
    };
  }

  @Get('debug/permissions')
  @ApiOperation({
    summary: 'Temporary debug endpoint for effective menu permissions by identifier',
  })
  async debugPermissions(@Query('identifier') identifier: string, @Query('key') key?: string) {
    return this.authService.debugPermissionsByIdentifier(identifier, key);
  }
}
