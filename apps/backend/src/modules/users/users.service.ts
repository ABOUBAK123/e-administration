import {
  Injectable,
  NotFoundException,
  BadRequestException,
  ConflictException,
} from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { In, QueryFailedError, Repository } from 'typeorm';
import * as bcrypt from 'bcryptjs';
import { User } from './user.entity';
import { CreateUserDto, UpdateCurrentUserDto, UpdateUserDto } from './dto/user.dto';
import { AdministrationUser } from '../administration/entities/administration-user.entity';
import { AdministrationProfile } from '../administration/entities/administration-profile.entity';
import { AppSetting } from '../administration/entities/app-setting.entity';
import { IssuingAdministration } from '../administration/entities/issuing-administration.entity';
import { RecipientAdministration } from '../administration/entities/recipient-administration.entity';
import { UserDirectionAssignment } from './user-direction-assignment.entity';

@Injectable()
export class UsersService {
  private static readonly ALLOWED_MENU_PERMISSIONS = new Set<string>([
    'dashboard',
    'templates-shared',
    'documents',
    'documents.view',
    'documents.upload',
    'documents.create-folder',
    'documents.share',
    'documents.edit-onlyoffice',
    'documents.delete',
    'workflows',
    'workflows.view',
    'workflows.create',
    'workflows.validate',
    'workflows.delete',
    'signatures',
    'signatures.view',
    'signatures.request',
    'signatures.sign',
    'signatures.reject',
    'reception',
    'act-requests',
    'act-requests.view',
    'act-requests.process',
    'administration',
    'administration.templates',
    'administration.emitters',
    'administration.recipients',
    'administration.requested-acts',
    'administration.routing',
    'administration.onlyoffice',
    'administration.users',
    'administration.theming',
    'administration.email-notifications',
    'administration.signature-provider',
    'administration.user-profiles',
    'qrcode',
  ]);

  constructor(
    @InjectRepository(User)
    private userRepository: Repository<User>,
    @InjectRepository(AdministrationUser)
    private administrationUserRepository: Repository<AdministrationUser>,
    @InjectRepository(AdministrationProfile)
    private administrationProfileRepository: Repository<AdministrationProfile>,
    @InjectRepository(AppSetting)
    private appSettingRepository: Repository<AppSetting>,
    @InjectRepository(IssuingAdministration)
    private issuingAdministrationRepository: Repository<IssuingAdministration>,
    @InjectRepository(RecipientAdministration)
    private recipientAdministrationRepository: Repository<RecipientAdministration>,
    @InjectRepository(UserDirectionAssignment)
    private userDirectionAssignmentRepository: Repository<UserDirectionAssignment>
  ) {}

  private readonly saltRounds = Number.parseInt(process.env.BCRYPT_SALT_ROUNDS || '12', 10);

  private normalizeRoleToken(value?: string | null): string {
    return String(value || '')
      .trim()
      .toLowerCase()
      .replace(/[-\s]+/g, '_');
  }

  private isSuperAdminRole(value?: string | null): boolean {
    const normalized = this.normalizeRoleToken(value);
    return normalized === 'super_admin' || normalized === 'superadmin';
  }

  private normalizeMenuPermissionToken(value: unknown): string | null {
    if (typeof value !== 'string') return null;
    const normalized = value.trim().toLowerCase();
    if (!normalized) return null;
    return normalized;
  }

  private normalizeMenuPermissionList(rawPermissions: unknown): string[] {
    if (!Array.isArray(rawPermissions)) return [];

    const normalized = rawPermissions
      .map((item) => this.normalizeMenuPermissionToken(item))
      .filter((item): item is string => Boolean(item))
      .filter((item) => UsersService.ALLOWED_MENU_PERMISSIONS.has(item));

    return Array.from(new Set(normalized));
  }

  private async resolveUserScopeByIdentity(identity: {
    email?: string | null;
    username?: string | null;
  }) {
    const normalizedEmail = String(identity.email || '')
      .trim()
      .toLowerCase();
    const normalizedUsername = String(identity.username || '')
      .trim()
      .toLowerCase();

    const directionAssignment = normalizedEmail
      ? await this.userDirectionAssignmentRepository
          .createQueryBuilder('assignment')
          .leftJoin(User, 'user', 'user.id = assignment.userId')
          .where('LOWER(user.email) = :email', { email: normalizedEmail })
          .orderBy('assignment.updatedAt', 'DESC')
          .getOne()
      : null;

    if (directionAssignment?.directionScopeType && directionAssignment?.directionScopeId) {
      return {
        scopeType: directionAssignment.directionScopeType,
        scopeId: directionAssignment.directionScopeId,
      } as { scopeType: 'emitter' | 'recipient'; scopeId: string };
    }

    const administrationUser = await this.administrationUserRepository
      .createQueryBuilder('administrationUser')
      .where('LOWER(administrationUser.email) = :email', { email: normalizedEmail })
      .orWhere('LOWER(administrationUser.username) = :username', { username: normalizedUsername })
      .orderBy('administrationUser.updatedAt', 'DESC')
      .getOne();

    if (administrationUser?.administrationId) {
      return {
        scopeType: 'emitter' as const,
        scopeId: administrationUser.administrationId,
      };
    }

    return null;
  }

  private resolvePayloadScope(payload: {
    administrationId?: string;
    directionScopeType?: string;
    directionScopeId?: string;
  }) {
    const normalizedScopeType = this.normalizeDirectionScopeType(payload.directionScopeType);
    const normalizedScopeId = String(payload.directionScopeId || '').trim();
    const normalizedAdministrationId = String(payload.administrationId || '').trim();

    if (normalizedScopeType && normalizedScopeId) {
      return {
        scopeType: normalizedScopeType,
        scopeId: normalizedScopeId,
      } as { scopeType: 'emitter' | 'recipient'; scopeId: string };
    }

    if (normalizedAdministrationId) {
      return {
        scopeType: 'emitter' as const,
        scopeId: normalizedAdministrationId,
      };
    }

    return null;
  }

  private ensureAdministrationIsMandatory(payload: {
    administrationId?: string;
    directionScopeType?: string;
    directionScopeId?: string;
  }) {
    const scope = this.resolvePayloadScope(payload);
    if (!scope) {
      throw new BadRequestException("L'administration est obligatoire pour ce compte");
    }
    return scope;
  }

  private ensureSameScope(
    actorScope: { scopeType: 'emitter' | 'recipient'; scopeId: string } | null,
    targetScope: { scopeType: 'emitter' | 'recipient'; scopeId: string }
  ) {
    if (!actorScope) {
      throw new BadRequestException("Votre compte n'est lié à aucune administration");
    }
    if (
      actorScope.scopeType !== targetScope.scopeType ||
      actorScope.scopeId !== targetScope.scopeId
    ) {
      throw new BadRequestException(
        'Vous ne pouvez gérer que les utilisateurs de votre administration'
      );
    }
  }

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

  private isForeignKeyError(error: unknown): boolean {
    if (!(error instanceof QueryFailedError)) return false;
    const driverError = (error as any).driverError || {};
    const code = String(driverError.code || '').toUpperCase();
    const message = String(driverError.sqlMessage || driverError.message || '').toLowerCase();
    return code === 'ER_NO_REFERENCED_ROW_2' || message.includes('foreign key constraint fails');
  }

  private isMissingRelationError(error: unknown): boolean {
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

  private isBcryptHash(value: string): boolean {
    return /^\$2[aby]\$\d{2}\$/.test(value || '');
  }

  private async hashPassword(password: string): Promise<string> {
    return bcrypt.hash(password, Number.isNaN(this.saltRounds) ? 12 : this.saltRounds);
  }

  private normalizePagination(page?: number | string, limit?: number | string) {
    const parsedPage = Number(page);
    const parsedLimit = Number(limit);

    const safePage = Number.isFinite(parsedPage) && parsedPage > 0 ? Math.floor(parsedPage) : 1;
    const safeLimit =
      Number.isFinite(parsedLimit) && parsedLimit > 0 ? Math.min(Math.floor(parsedLimit), 100) : 20;

    return { page: safePage, limit: safeLimit };
  }

  private normalizeDirectionScopeType(value?: string | null): 'emitter' | 'recipient' | null {
    const normalized = String(value || '')
      .trim()
      .toLowerCase();
    if (normalized === 'emitter' || normalized === 'recipient') {
      return normalized as 'emitter' | 'recipient';
    }
    return null;
  }

  private normalizeSubEntityCode(value?: string | null): string | null {
    const normalized = String(value || '')
      .trim()
      .toUpperCase();
    return normalized || null;
  }

  private async upsertUserDirectionAssignment(
    userId: string,
    payload: {
      directionLabel?: string;
      directionScopeType?: string;
      directionScopeId?: string;
      subEntityCode?: string;
    }
  ): Promise<UserDirectionAssignment | null> {
    const hasDirectionPayload =
      payload.directionLabel !== undefined ||
      payload.directionScopeType !== undefined ||
      payload.directionScopeId !== undefined ||
      payload.subEntityCode !== undefined;

    if (!hasDirectionPayload) {
      try {
        return this.userDirectionAssignmentRepository.findOne({ where: { userId } });
      } catch (error) {
        if (this.isMissingRelationError(error)) {
          return null;
        }
        throw error;
      }
    }

    const directionLabel = String(payload.directionLabel || '').trim();
    if (!directionLabel) {
      try {
        await this.userDirectionAssignmentRepository.delete({ userId });
      } catch (error) {
        if (!this.isMissingRelationError(error)) {
          throw error;
        }
      }
      return null;
    }

    const directionScopeType = this.normalizeDirectionScopeType(payload.directionScopeType);
    const directionScopeId = String(payload.directionScopeId || '').trim() || null;
    const subEntityCode = this.normalizeSubEntityCode(payload.subEntityCode);

    if (!directionScopeType || !directionScopeId || !subEntityCode) {
      throw new BadRequestException(
        'Le scope de direction et le code de sous-entité sont obligatoires pour ce compte'
      );
    }

    let assignment: UserDirectionAssignment | null = null;
    try {
      assignment = await this.userDirectionAssignmentRepository.findOne({ where: { userId } });
    } catch (error) {
      if (this.isMissingRelationError(error)) {
        return null;
      }
      throw error;
    }
    if (!assignment) {
      assignment = this.userDirectionAssignmentRepository.create({
        userId,
        directionLabel,
        directionScopeType,
        directionScopeId,
        subEntityCode,
      });
    } else {
      assignment.directionLabel = directionLabel;
      assignment.directionScopeType = directionScopeType;
      assignment.directionScopeId = directionScopeId;
      assignment.subEntityCode = subEntityCode;
    }

    try {
      return await this.userDirectionAssignmentRepository.save(assignment);
    } catch (error) {
      if (this.isMissingRelationError(error)) {
        return null;
      }
      throw error;
    }
  }

  async findAll(requesterUserId: string, page: number | string = 1, limit: number | string = 20) {
    const pagination = this.normalizePagination(page, limit);
    const requester = await this.findEntityById(requesterUserId);
    const requesterScope = await this.resolveUserScopeByIdentity({
      email: requester.email,
      username: requester.username,
    });
    const requesterIsSuperAdmin = this.isSuperAdminRole(requester.role);

    let users: Array<Partial<User> & { email: string }> = [];

    try {
      const resultUsers = await this.userRepository.find({
        order: { createdAt: 'DESC' },
        select: [
          'id',
          'username',
          'email',
          'fullName',
          'avatar',
          'role',
          'status',
          'quota',
          'createdAt',
          'updatedAt',
        ],
      });
      users = resultUsers;
    } catch {
      // Fallback for legacy schemas where optional profile columns may be missing.
      const resultUsers = await this.userRepository.find({
        order: { createdAt: 'DESC' },
        select: [
          'id',
          'username',
          'email',
          'fullName',
          'avatar',
          'role',
          'status',
          'createdAt',
          'updatedAt',
        ],
      });
      users = resultUsers.map((user) => ({ ...user, quota: null }));
    }

    // Attach administrationId by matching on email in administration_users
    const emails = users.map((u) => u.email);
    const adminUserMap = new Map<string, string>();
    if (emails.length > 0) {
      try {
        const adminUsers = await this.administrationUserRepository.find({
          where: { email: In(emails) },
          select: ['email', 'administrationId'],
        });
        for (const au of adminUsers) {
          adminUserMap.set(au.email, au.administrationId);
        }
      } catch {
        // Keep users API available even if legacy admin-link schema is inconsistent.
      }
    }

    const userIds = users.map((u) => String((u as any).id || '')).filter(Boolean);
    const directionAssignmentMap = new Map<string, UserDirectionAssignment>();
    if (userIds.length > 0) {
      try {
        const assignments = await this.userDirectionAssignmentRepository.find({
          where: { userId: In(userIds) },
        });
        for (const assignment of assignments) {
          directionAssignmentMap.set(assignment.userId, assignment);
        }
      } catch (error) {
        if (!this.isMissingRelationError(error)) {
          throw error;
        }
      }
    }

    const mappedUsers = users.map((u) => {
      const assignment = directionAssignmentMap.get(String((u as any).id || ''));
      const mappedAdministrationId = adminUserMap.get(u.email) || null;
      const administrationId =
        assignment?.directionScopeType === 'recipient' ? null : mappedAdministrationId;

      return {
        ...u,
        administrationId,
        directionLabel: assignment?.directionLabel || null,
        directionScopeType: assignment?.directionScopeType || null,
        directionScopeId: assignment?.directionScopeId || null,
        subEntityCode: assignment?.subEntityCode || null,
      };
    });

    const scopedUsers = requesterIsSuperAdmin
      ? mappedUsers
      : mappedUsers.filter((u) => {
          if (!requesterScope) return false;

          if (requesterScope.scopeType === 'emitter') {
            return (
              u.administrationId === requesterScope.scopeId ||
              (u.directionScopeType === 'emitter' && u.directionScopeId === requesterScope.scopeId)
            );
          }

          return (
            u.directionScopeType === 'recipient' && u.directionScopeId === requesterScope.scopeId
          );
        });

    const total = scopedUsers.length;
    const pageOffset = Math.max(0, (pagination.page - 1) * pagination.limit);
    const pagedUsers = scopedUsers.slice(pageOffset, pageOffset + pagination.limit);

    return {
      data: pagedUsers,
      pagination: {
        total,
        page: pagination.page,
        limit: pagination.limit,
        pages: Math.ceil(total / pagination.limit),
      },
    };
  }

  async findOne(id: string) {
    const user = await this.userRepository.findOne({
      where: { id },
      select: [
        'id',
        'username',
        'email',
        'fullName',
        'avatar',
        'role',
        'status',
        'createdAt',
        'updatedAt',
      ],
    });

    if (!user) {
      throw new NotFoundException('User not found');
    }

    const administrationUser = await this.administrationUserRepository.findOne({
      where: [{ email: user.email }, { username: user.username }],
      select: ['administrationId'],
    });

    let directionAssignment: UserDirectionAssignment | null = null;
    try {
      directionAssignment = await this.userDirectionAssignmentRepository.findOne({
        where: { userId: user.id },
      });
    } catch (error) {
      if (!this.isMissingRelationError(error)) {
        throw error;
      }
    }

    return {
      ...user,
      administrationId: administrationUser?.administrationId || null,
      directionLabel: directionAssignment?.directionLabel || null,
      directionScopeType: directionAssignment?.directionScopeType || null,
      directionScopeId: directionAssignment?.directionScopeId || null,
      subEntityCode: directionAssignment?.subEntityCode || null,
    };
  }

  async findByEmail(email: string) {
    return await this.userRepository.findOne({
      where: { email },
    });
  }

  async findByEmailInsensitive(email: string) {
    const normalizedEmail = email.trim().toLowerCase();
    return this.userRepository
      .createQueryBuilder('user')
      .where('LOWER(user.email) = :email', { email: normalizedEmail })
      .getOne();
  }

  async findByUsername(username: string) {
    return await this.userRepository.findOne({
      where: { username },
    });
  }

  async findEntityById(id: string) {
    const user = await this.userRepository.findOne({ where: { id } });

    if (!user) {
      throw new NotFoundException('User not found');
    }

    return user;
  }

  async create(requesterUserId: string | null, userData: CreateUserDto) {
    const requester = requesterUserId ? await this.findEntityById(requesterUserId) : null;
    const requesterIsSuperAdmin = requester ? this.isSuperAdminRole(requester.role) : true;
    const targetScope = this.ensureAdministrationIsMandatory(userData);

    if (requester && !requesterIsSuperAdmin) {
      const requesterScope = await this.resolveUserScopeByIdentity({
        email: requester.email,
        username: requester.username,
      });
      this.ensureSameScope(requesterScope, targetScope);
    }

    // Check if user exists
    const existingUser = await this.userRepository.findOne({
      where: [{ email: userData.email }, { username: userData.username }],
    });

    if (existingUser) {
      throw new ConflictException('User with this email or username already exists');
    }

    // Hash password before persistence.
    const hashedPassword = await this.hashPassword(userData.password);

    const user = this.userRepository.create({
      ...userData,
      passwordHash: hashedPassword,
      role: (userData.role as 'admin' | 'user' | 'signer' | 'manager') || 'user',
      status: (userData.status as 'active' | 'inactive' | 'suspended') || 'active',
      quota: userData.quota || '5 Go',
    });

    let savedUser: User;
    try {
      savedUser = await this.userRepository.save(user);
    } catch (error) {
      if (this.isDuplicateEntryError(error)) {
        throw new ConflictException('User with this email or username already exists');
      }
      if (this.isForeignKeyError(error)) {
        throw new BadRequestException('Invalid reference in user payload');
      }
      throw error;
    }

    // If administrationId is provided, create the corresponding AdministrationUser link
    if (userData.administrationId) {
      const existing = await this.administrationUserRepository.findOne({
        where: { email: savedUser.email },
      });
      if (!existing) {
        const adminUser = this.administrationUserRepository.create({
          administrationId: userData.administrationId,
          email: savedUser.email,
          username: savedUser.username || savedUser.email,
          fullName: savedUser.fullName,
          adminRole:
            (savedUser.role as 'super_admin' | 'admin' | 'manager' | 'user' | 'signer') || 'user',
          status: savedUser.status === 'active' ? 'active' : 'inactive',
        });
        try {
          await this.administrationUserRepository.save(adminUser);
        } catch (error) {
          // Avoid leaving a partially created user when the admin link is invalid.
          await this.userRepository.delete(savedUser.id);

          if (this.isDuplicateEntryError(error)) {
            throw new ConflictException(
              'Administration link already exists with same email or username'
            );
          }
          if (this.isForeignKeyError(error)) {
            throw new BadRequestException('Administration cible invalide');
          }
          throw error;
        }
      }
    }

    const directionAssignment = await this.upsertUserDirectionAssignment(savedUser.id, {
      directionLabel: userData.directionLabel,
      directionScopeType: userData.directionScopeType,
      directionScopeId: userData.directionScopeId,
      subEntityCode: userData.subEntityCode,
    });

    const result = { ...savedUser };
    delete (result as Partial<User> & { passwordHash?: string }).passwordHash;
    return {
      ...result,
      administrationId: userData.administrationId || null,
      directionLabel: directionAssignment?.directionLabel || null,
      directionScopeType: directionAssignment?.directionScopeType || null,
      directionScopeId: directionAssignment?.directionScopeId || null,
      subEntityCode: directionAssignment?.subEntityCode || null,
    };
  }

  async validatePassword(user: User, password: string): Promise<boolean> {
    if (this.isBcryptHash(user.passwordHash)) {
      return bcrypt.compare(password, user.passwordHash);
    }

    // Auto-migrate legacy clear-text values to bcrypt hash on successful login.
    if (user.passwordHash === password) {
      user.passwordHash = await this.hashPassword(password);
      await this.userRepository.save(user);
      return true;
    }

    return false;
  }

  async update(requesterUserId: string, id: string, userData: UpdateUserDto) {
    const requester = await this.findEntityById(requesterUserId);
    const requesterIsSuperAdmin = this.isSuperAdminRole(requester.role);
    const user = await this.findEntityById(id);

    const existingAssignment = await this.userDirectionAssignmentRepository.findOne({
      where: { userId: user.id },
    });
    const existingAdminLink = await this.administrationUserRepository.findOne({
      where: { email: user.email },
    });

    const targetScope = this.ensureAdministrationIsMandatory({
      administrationId: userData.administrationId ?? existingAdminLink?.administrationId,
      directionScopeType:
        userData.directionScopeType ?? existingAssignment?.directionScopeType ?? undefined,
      directionScopeId:
        userData.directionScopeId ?? existingAssignment?.directionScopeId ?? undefined,
    });

    if (!requesterIsSuperAdmin) {
      const requesterScope = await this.resolveUserScopeByIdentity({
        email: requester.email,
        username: requester.username,
      });
      this.ensureSameScope(requesterScope, targetScope);
    }

    if (userData.username && userData.username !== user.username) {
      const existingByUsername = await this.userRepository.findOne({
        where: { username: userData.username },
      });
      if (existingByUsername && existingByUsername.id !== user.id) {
        throw new ConflictException('Username already in use');
      }
    }

    if (userData.email && userData.email !== user.email) {
      const existingUser = await this.userRepository.findOne({
        where: { email: userData.email },
      });
      if (existingUser) {
        throw new ConflictException('Email already in use');
      }
    }

    const updatePayload: Partial<User> = {};
    if (userData.username !== undefined) updatePayload.username = userData.username;
    if (userData.email !== undefined) updatePayload.email = userData.email;
    if (userData.fullName !== undefined) updatePayload.fullName = userData.fullName;
    if (userData.role !== undefined)
      updatePayload.role = userData.role as 'admin' | 'user' | 'signer' | 'manager';
    if (userData.quota !== undefined) updatePayload.quota = userData.quota;

    if (userData.password) {
      updatePayload.passwordHash = await this.hashPassword(userData.password);
    }

    Object.assign(user, updatePayload);

    let savedUser: User;
    try {
      savedUser = await this.userRepository.save(user);
    } catch (error) {
      if (this.isDuplicateEntryError(error)) {
        throw new ConflictException('User with this email or username already exists');
      }
      if (this.isForeignKeyError(error)) {
        throw new BadRequestException('Invalid reference in user payload');
      }
      throw error;
    }

    // Upsert AdministrationUser link if administrationId is provided
    if (userData.administrationId) {
      const existing = await this.administrationUserRepository.findOne({
        where: { email: savedUser.email },
      });
      if (existing) {
        existing.administrationId = userData.administrationId;
        existing.fullName = savedUser.fullName || existing.fullName;
        existing.username = savedUser.username || existing.username;
        try {
          await this.administrationUserRepository.save(existing);
        } catch (error) {
          if (this.isDuplicateEntryError(error)) {
            throw new ConflictException(
              'Administration link already exists with same email or username'
            );
          }
          if (this.isForeignKeyError(error)) {
            throw new BadRequestException('Administration cible invalide');
          }
          throw error;
        }
      } else {
        const adminUser = this.administrationUserRepository.create({
          administrationId: userData.administrationId,
          email: savedUser.email,
          username: savedUser.username || savedUser.email,
          fullName: savedUser.fullName,
          adminRole:
            (savedUser.role as 'super_admin' | 'admin' | 'manager' | 'user' | 'signer') || 'user',
          status: savedUser.status === 'active' ? 'active' : 'inactive',
        });
        try {
          await this.administrationUserRepository.save(adminUser);
        } catch (error) {
          if (this.isDuplicateEntryError(error)) {
            throw new ConflictException(
              'Administration link already exists with same email or username'
            );
          }
          if (this.isForeignKeyError(error)) {
            throw new BadRequestException('Administration cible invalide');
          }
          throw error;
        }
      }
    } else if (this.normalizeDirectionScopeType(userData.directionScopeType) === 'recipient') {
      // Recipient-scoped direction should not keep a stale emitter administration link.
      await this.administrationUserRepository.delete({ email: savedUser.email });
    }

    const directionAssignment = await this.upsertUserDirectionAssignment(savedUser.id, {
      directionLabel: userData.directionLabel,
      directionScopeType: userData.directionScopeType,
      directionScopeId: userData.directionScopeId,
      subEntityCode: userData.subEntityCode,
    });

    const result = { ...savedUser } as Partial<User> & { passwordHash?: string };
    delete result.passwordHash;
    return {
      ...result,
      administrationId: userData.administrationId || null,
      directionLabel: directionAssignment?.directionLabel || null,
      directionScopeType: directionAssignment?.directionScopeType || null,
      directionScopeId: directionAssignment?.directionScopeId || null,
      subEntityCode: directionAssignment?.subEntityCode || null,
    };
  }

  async updateCurrentUser(id: string, userData: UpdateCurrentUserDto) {
    const user = await this.findEntityById(id);

    if (userData.email && userData.email !== user.email) {
      const normalizedEmail = userData.email.trim().toLowerCase();
      const existingUser = await this.userRepository.findOne({
        where: { email: normalizedEmail },
      });

      if (existingUser && existingUser.id !== user.id) {
        throw new ConflictException('Email already in use');
      }

      user.email = normalizedEmail;
    }

    if (userData.fullName !== undefined) {
      const nextFullName = userData.fullName.trim();
      if (!nextFullName) {
        throw new BadRequestException('Full name is required');
      }
      user.fullName = nextFullName;
    }

    if (userData.password) {
      if (!userData.currentPassword) {
        throw new BadRequestException('Current password is required to change password');
      }

      const isPasswordValid = await bcrypt.compare(userData.currentPassword, user.passwordHash);
      if (!isPasswordValid) {
        throw new BadRequestException('Current password is incorrect');
      }

      user.passwordHash = await this.hashPassword(userData.password);
    }

    const savedUser = await this.userRepository.save(user);
    const result = { ...savedUser };
    delete (result as Partial<User> & { passwordHash?: string }).passwordHash;
    return result;
  }

  async updateCurrentUserAvatar(id: string, filename: string) {
    return this.updateUserAvatar(id, filename);
  }

  async updateUserAvatar(id: string, filename: string) {
    const user = await this.findEntityById(id);
    user.avatar = `/storage/avatars/${filename}`;

    const savedUser = await this.userRepository.save(user);
    const result = { ...savedUser };
    delete (result as Partial<User> & { passwordHash?: string }).passwordHash;
    return result;
  }

  async updatePassword(id: string, password: string) {
    const user = await this.findEntityById(id);
    user.passwordHash = await this.hashPassword(password);
    await this.userRepository.save(user);
    return { message: 'Password updated successfully' };
  }

  async delete(id: string) {
    const user = await this.findEntityById(id);

    // Clean linked records managed outside SQL foreign keys.
    await this.administrationUserRepository.delete({ email: user.email });
    await this.userDirectionAssignmentRepository.delete({ userId: user.id });

    await this.userRepository.delete({ id: user.id });

    return { message: 'User deleted successfully' };
  }

  async updateRole(id: string, role: string) {
    const user = await this.findEntityById(id);

    user.role = role as 'admin' | 'user' | 'signer' | 'manager';

    return await this.userRepository.save(user);
  }

  async updateStatus(id: string, status: string) {
    const user = await this.findEntityById(id);

    user.status = status as 'active' | 'inactive' | 'suspended';

    return await this.userRepository.save(user);
  }

  /**
   * Return users from the same administration as the requesting user,
   * filtered to those whose administration role/profile is "signataire" / "signer".
   */
  async findSignatairesSameAdministration(requestingUserId: string) {
    // 1. Find the requesting user's email to resolve their administration
    const requestingUser = await this.userRepository.findOne({
      where: { id: requestingUserId },
      select: ['id', 'email'],
    });
    if (!requestingUser) {
      throw new NotFoundException('User not found');
    }

    const adminUserEntry = await this.administrationUserRepository.findOne({
      where: { email: requestingUser.email },
      select: ['administrationId'],
    });
    if (!adminUserEntry) {
      return [];
    }

    const administrationId = adminUserEntry.administrationId;

    // 2. Find all administration_users in the same administration that are signataires
    //    Match by adminRole containing 'signer'/'signataire' (case-insensitive) OR profile.name containing 'signataire'
    const adminUsers = await this.administrationUserRepository
      .createQueryBuilder('au')
      .leftJoinAndSelect('au.profile', 'profile')
      .where('au.administrationId = :administrationId', { administrationId })
      .andWhere('au.status = :status', { status: 'active' })
      .andWhere(
        '(LOWER(au.adminRole) IN (:...signerRoles) OR LOWER(profile.name) LIKE :profilePattern)',
        { signerRoles: ['signer', 'signataire'], profilePattern: '%signataire%' }
      )
      .getMany();

    if (adminUsers.length === 0) {
      return [];
    }

    // 3. Resolve actual user records by matching emails
    const emails = adminUsers.map((au) => au.email);
    const users = await this.userRepository.find({
      where: { email: In(emails), status: 'active' },
      select: ['id', 'username', 'email', 'fullName', 'avatar', 'role'],
    });

    return users.map((u) => ({
      id: u.id,
      username: u.username,
      email: u.email,
      fullName: u.fullName,
      avatar: u.avatar,
      role: u.role,
      administrationId,
    }));
  }

  async search(
    requesterUserId: string,
    query: string,
    page: number | string = 1,
    limit: number | string = 20
  ) {
    const pagination = this.normalizePagination(page, limit);
    const requester = await this.findEntityById(requesterUserId);
    const requesterScope = await this.resolveUserScopeByIdentity({
      email: requester.email,
      username: requester.username,
    });
    const requesterIsSuperAdmin = this.isSuperAdminRole(requester.role);

    const [users] = await this.userRepository.findAndCount({
      where: [{ username: query }, { email: query }, { fullName: query }],
      skip: (pagination.page - 1) * pagination.limit,
      take: pagination.limit,
      select: ['id', 'username', 'email', 'fullName', 'role', 'status', 'createdAt', 'updatedAt'],
    });

    const userEmails = users.map((u) => u.email);
    const adminLinks =
      userEmails.length > 0
        ? await this.administrationUserRepository.find({
            where: { email: In(userEmails) },
            select: ['email', 'administrationId'],
          })
        : [];
    const adminLinkMap = new Map(adminLinks.map((item) => [item.email, item.administrationId]));

    const userIds = users.map((u) => u.id);
    const assignments =
      userIds.length > 0
        ? await this.userDirectionAssignmentRepository.find({ where: { userId: In(userIds) } })
        : [];
    const assignmentMap = new Map(assignments.map((item) => [item.userId, item]));

    const scopedResults = users
      .map((u) => {
        const assignment = assignmentMap.get(u.id);
        const administrationId =
          assignment?.directionScopeType === 'recipient' ? null : adminLinkMap.get(u.email) || null;

        return {
          ...u,
          administrationId,
          directionScopeType: assignment?.directionScopeType || null,
          directionScopeId: assignment?.directionScopeId || null,
          directionLabel: assignment?.directionLabel || null,
          subEntityCode: assignment?.subEntityCode || null,
        };
      })
      .filter((u) => {
        if (requesterIsSuperAdmin) return true;
        if (!requesterScope) return false;
        if (requesterScope.scopeType === 'emitter') {
          return (
            u.administrationId === requesterScope.scopeId ||
            (u.directionScopeType === 'emitter' && u.directionScopeId === requesterScope.scopeId)
          );
        }
        return (
          u.directionScopeType === 'recipient' && u.directionScopeId === requesterScope.scopeId
        );
      });

    return {
      data: scopedResults,
      pagination: {
        total: scopedResults.length,
        page: pagination.page,
        limit: pagination.limit,
        pages: Math.ceil(scopedResults.length / pagination.limit),
      },
    };
  }

  private extractMenuPermissions(
    permissions: Record<string, unknown> | null | undefined
  ): string[] {
    if (!permissions) return [];

    if (Array.isArray(permissions)) {
      return this.normalizeMenuPermissionList(permissions);
    }

    const menuPermissions = (permissions as any).menuPermissions;
    if (Array.isArray(menuPermissions)) {
      return this.normalizeMenuPermissionList(menuPermissions);
    }

    const nestedPermissions = (permissions as any).permissions;
    if (Array.isArray(nestedPermissions)) {
      return this.normalizeMenuPermissionList(nestedPermissions);
    }

    const booleanMapPermissions = Object.entries(permissions)
      .filter(([, value]) => value === true)
      .map(([key]) => key);

    return this.normalizeMenuPermissionList(booleanMapPermissions);
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

  private sanitizeMenuPermissionsByRole(normalizedRole: string, permissions: string[]): string[] {
    if (!Array.isArray(permissions) || permissions.length === 0) {
      return [];
    }

    if (normalizedRole === 'assistant') {
      return permissions.filter(
        (permission) => permission !== 'signatures' && !permission.startsWith('signatures.')
      );
    }

    return permissions;
  }

  async getCurrentUserMenuPermissions(userId: string) {
    const user = await this.findEntityById(userId);
    const normalizedRole = this.normalizeRoleToken(user.role);

    const normalizedEmail = (user.email || '').trim().toLowerCase();
    const normalizedUsername = (user.username || '').trim().toLowerCase();

    const administrationUser = await this.administrationUserRepository
      .createQueryBuilder('administrationUser')
      .leftJoinAndSelect('administrationUser.profile', 'profile')
      .where('LOWER(administrationUser.email) = :email', { email: normalizedEmail })
      .orWhere('LOWER(administrationUser.username) = :username', { username: normalizedUsername })
      .orderBy('administrationUser.updatedAt', 'DESC')
      .getOne();

    const normalizedAdminRole = this.normalizeRoleToken(administrationUser?.adminRole);
    const isElevated =
      this.isSuperAdminRole(normalizedRole) || this.isSuperAdminRole(normalizedAdminRole);

    if (isElevated) {
      return {
        isElevated,
        permissions: [
          'dashboard',
          'templates-shared',
          'documents',
          'workflows',
          'signatures',
          'reception',
          'act-requests',
          'administration',
          'qrcode',
        ],
        source: 'elevated_role',
        debug: {
          userRole: user.role || null,
          normalizedUserRole: normalizedRole || null,
          adminRole: administrationUser?.adminRole || null,
          normalizedAdminRole: normalizedAdminRole || null,
          administrationProfileName: administrationUser?.profile?.name || null,
          administrationProfileId: administrationUser?.profile?.id || null,
        },
      };
    }

    const assignedProfilePermissions = this.extractMenuPermissions(
      administrationUser?.profile?.permissions as Record<string, unknown> | undefined
    );
    const sanitizedAssignedPermissions = this.sanitizeMenuPermissionsByRole(
      normalizedRole,
      assignedProfilePermissions
    );
    if (sanitizedAssignedPermissions.length > 0) {
      return {
        isElevated: false,
        permissions: sanitizedAssignedPermissions,
        source: 'administration_user_profile',
        debug: {
          userRole: user.role || null,
          normalizedUserRole: normalizedRole || null,
          adminRole: administrationUser?.adminRole || null,
          normalizedAdminRole: normalizedAdminRole || null,
          administrationProfileName: administrationUser?.profile?.name || null,
          administrationProfileId: administrationUser?.profile?.id || null,
        },
      };
    }

    const roleProfiles = (await this.administrationProfileRepository.find()).filter(
      (profile) => this.normalizeRoleToken(profile.name) === normalizedRole
    );

    const bestProfile = roleProfiles
      .map((profile) => ({
        profile,
        permissions: this.extractMenuPermissions(
          profile.permissions as Record<string, unknown> | undefined
        ),
      }))
      .sort((a, b) => b.permissions.length - a.permissions.length)[0];

    const roleProfilePermissions = this.sanitizeMenuPermissionsByRole(
      normalizedRole,
      bestProfile?.permissions || []
    );
    const defaultRolePermissions =
      roleProfilePermissions.length > 0 ? [] : this.getDefaultMenuPermissionsByRole(normalizedRole);

    return {
      isElevated: false,
      permissions:
        roleProfilePermissions.length > 0 ? roleProfilePermissions : defaultRolePermissions,
      source:
        roleProfilePermissions.length > 0
          ? 'role_profile'
          : defaultRolePermissions.length > 0
            ? 'role_default'
            : 'none',
      debug: {
        userRole: user.role || null,
        normalizedUserRole: normalizedRole || null,
        adminRole: administrationUser?.adminRole || null,
        normalizedAdminRole: normalizedAdminRole || null,
        administrationProfileName: administrationUser?.profile?.name || null,
        administrationProfileId: administrationUser?.profile?.id || null,
        roleProfileName: bestProfile?.profile?.name || null,
        roleProfileId: bestProfile?.profile?.id || null,
      },
    };
  }

  async getMenuPermissionsByIdentifier(identifier: string) {
    const normalized = (identifier || '').trim().toLowerCase();
    if (!normalized) {
      throw new BadRequestException('Identifier is required');
    }

    let user = await this.findByEmailInsensitive(normalized);
    if (!user) {
      user = await this.findByUsername(normalized);
    }

    if (!user) {
      user = await this.userRepository.findOne({ where: { id: identifier } });
    }

    if (!user) {
      throw new NotFoundException('User not found for provided identifier');
    }

    const permissions = await this.getCurrentUserMenuPermissions(user.id);
    return {
      identifier,
      user: {
        id: user.id,
        email: user.email,
        username: user.username,
        role: user.role,
        status: user.status,
      },
      ...permissions,
    };
  }

  async getCurrentUserTheme(userId: string) {
    const user = await this.findEntityById(userId);
    const scope = await this.resolveUserScopeByIdentity({
      email: user.email,
      username: user.username,
    });

    const scopeType: 'emitter' | 'recipient' | null = scope?.scopeType || null;
    const scopeId: string | null = scope?.scopeId || null;

    const settings = await this.appSettingRepository.find();
    const settingsMap = new Map(settings.map((item) => [item.key, item.value] as const));

    const getScopedValue = (suffix: string) => {
      if (!scopeType || !scopeId) {
        return null;
      }
      return settingsMap.get(`theme_${scopeType}_${scopeId}_${suffix}`) ?? null;
    };

    let administrationLogo: string | null = null;
    if (scopeType === 'emitter' && scopeId) {
      const emitter = await this.issuingAdministrationRepository.findOne({
        where: { id: scopeId },
        select: ['id', 'logo'],
      });
      administrationLogo = emitter?.logo || null;
    } else if (scopeType === 'recipient' && scopeId) {
      const recipient = await this.recipientAdministrationRepository.findOne({
        where: { id: scopeId },
        select: ['id', 'metadata'],
      });
      administrationLogo = String((recipient?.metadata as any)?.logo || '').trim() || null;
    }

    return {
      scopeType,
      scopeId,
      menuColor: getScopedValue('menu_color') || settingsMap.get('theme_menu_color') || '#173b9f',
      loginBackgroundImage:
        getScopedValue('login_background_image') ||
        settingsMap.get('theme_login_background_image') ||
        null,
      administrationLogo,
    };
  }
}
