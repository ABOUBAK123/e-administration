import { Injectable, UnauthorizedException, BadRequestException, Logger } from '@nestjs/common';
import { JwtService } from '@nestjs/jwt';
import { ConfigService } from '@nestjs/config';
import { UsersService } from '../users/users.service';
import { LoginDto } from '../users/dto/user.dto';
import { NotificationsService } from '../notifications/notifications.service';
import { ForgotPasswordDto, ResetPasswordDto } from './dto/password-reset.dto';

@Injectable()
export class AuthService {
  private readonly logger = new Logger(AuthService.name);

  constructor(
    private readonly jwtService: JwtService,
    private readonly configService: ConfigService,
    private readonly usersService: UsersService,
    private readonly notificationsService: NotificationsService
  ) {}

  async register(createUserDto: any) {
    const existingUser = await this.usersService.findByEmail(createUserDto.email);
    if (existingUser) {
      throw new BadRequestException('Email already registered');
    }

    const user = await this.usersService.create(null, createUserDto);
    return this.generateTokens(user);
  }

  async registerInvited(createUserDto: any) {
    const normalizedEmail = createUserDto.email?.trim().toLowerCase();
    const normalizedUsername = createUserDto.username?.trim().toLowerCase();

    const existingUser =
      (await this.usersService.findByEmail(normalizedEmail)) ||
      (await this.usersService.findByUsername(normalizedUsername));

    if (existingUser) {
      throw new BadRequestException('Email or username already registered');
    }

    const user = await this.usersService.create(null, {
      ...createUserDto,
      email: normalizedEmail,
      username: normalizedUsername,
      status: 'inactive',
    });

    return {
      message: 'Votre compte a été créé et reste en attente d’activation par un administrateur.',
      user: {
        id: user.id,
        email: user.email,
        username: user.username,
        fullName: user.fullName,
        role: user.role,
        status: user.status,
      },
    };
  }

  async login(loginDto: LoginDto) {
    const identifier = loginDto.email?.trim().toLowerCase();
    const user =
      (await this.usersService.findByEmail(identifier)) ||
      (await this.usersService.findByUsername(identifier));
    if (!user) {
      throw new UnauthorizedException('Invalid credentials');
    }

    if (user.status !== 'active') {
      throw new UnauthorizedException('Your account is awaiting activation by an administrator');
    }

    const isPasswordValid = await this.usersService.validatePassword(user, loginDto.password);
    if (!isPasswordValid) {
      throw new UnauthorizedException('Invalid credentials');
    }

    return this.generateTokens(user);
  }

  async validateUser(id: string) {
    const user = await this.usersService.findOne(id);
    if (!user) {
      throw new UnauthorizedException('User not found');
    }
    return user;
  }

  private generateTokens(user: any) {
    const payload = {
      sub: user.id,
      id: user.id,
      email: user.email,
      role: user.role,
    };

    const accessToken = this.jwtService.sign(payload, {
      expiresIn: '15m',
    });

    const refreshToken = this.jwtService.sign(payload, {
      expiresIn: '7d',
    });

    return {
      accessToken,
      refreshToken,
      user: {
        id: user.id,
        email: user.email,
        username: user.username,
        fullName: user.fullName,
        role: user.role,
        avatar: user.avatar,
      },
    };
  }

  async refreshTokens(refreshToken: string) {
    try {
      const payload = this.jwtService.verify(refreshToken);
      const user = await this.validateUser(payload?.id || payload?.sub);
      return this.generateTokens(user);
    } catch (error) {
      throw new UnauthorizedException('Invalid refresh token');
    }
  }

  async forgotPassword(forgotPasswordDto: ForgotPasswordDto) {
    const normalizedEmail = forgotPasswordDto.email.trim().toLowerCase();
    const user = await this.usersService.findByEmailInsensitive(normalizedEmail);

    if (!user) {
      return {
        message: 'Si cette adresse email existe, un lien de reinitialisation a ete envoye.',
      };
    }

    const resetToken = this.jwtService.sign(
      {
        sub: user.id,
        email: user.email,
        type: 'password_reset',
      },
      {
        expiresIn: '30m',
      }
    );

    const frontendBaseUrl =
      this.configService.get<string>('FRONTEND_URL') ||
      process.env.FRONTEND_URL ||
      'http://localhost:5173';
    const resetUrl = `${frontendBaseUrl}/reset-password?token=${encodeURIComponent(resetToken)}`;

    await this.notificationsService.sendEmailNotification({
      to: user.email,
      subject: 'Reinitialisation de votre mot de passe',
      text: `Une demande de reinitialisation de mot de passe a ete recue.\n\nOuvrez ce lien pour definir un nouveau mot de passe : ${resetUrl}\n\nCe lien expire dans 30 minutes.`,
      html: `<p>Une demande de reinitialisation de mot de passe a ete recue.</p><p><a href="${resetUrl}">Definir un nouveau mot de passe</a></p><p>Ce lien expire dans 30 minutes.</p>`,
    });

    this.logger.log(`Password reset requested for email=${normalizedEmail}`);

    return {
      message: 'Si cette adresse email existe, un lien de reinitialisation a ete envoye.',
    };
  }

  async resetPassword(resetPasswordDto: ResetPasswordDto) {
    const { token, newPassword } = resetPasswordDto;

    let payload: any;
    try {
      payload = this.jwtService.verify(token);
    } catch {
      throw new BadRequestException('Le lien de reinitialisation est invalide ou expire.');
    }

    if (payload?.type !== 'password_reset' || !payload?.sub) {
      throw new BadRequestException('Le lien de reinitialisation est invalide.');
    }

    const user = await this.usersService.findEntityById(payload.sub);
    const isSamePassword = await this.usersService.validatePassword(user, newPassword);
    if (isSamePassword) {
      throw new BadRequestException("Le nouveau mot de passe doit etre different de l'ancien.");
    }

    await this.usersService.updatePassword(user.id, newPassword);

    return { message: 'Mot de passe reinitialise avec succes.' };
  }

  async debugPermissionsByIdentifier(identifier: string, key?: string) {
    const debugKey =
      this.configService.get<string>('PERMISSIONS_DEBUG_KEY') || process.env.PERMISSIONS_DEBUG_KEY;
    const isProduction = (process.env.NODE_ENV || '').toLowerCase() === 'production';
    const providedKey = (key || '').trim();

    if (!debugKey) {
      throw new UnauthorizedException('Debug endpoint disabled');
    }

    if (isProduction && providedKey !== debugKey) {
      throw new UnauthorizedException('Invalid debug key');
    }

    if (!isProduction && providedKey && providedKey !== debugKey) {
      throw new UnauthorizedException('Invalid debug key');
    }

    return this.usersService.getMenuPermissionsByIdentifier(identifier);
  }
}
