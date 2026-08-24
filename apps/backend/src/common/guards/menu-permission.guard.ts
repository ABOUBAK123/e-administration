import { CanActivate, ExecutionContext, ForbiddenException, Injectable } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { UsersService } from '../../modules/users/users.service';
import { MENU_PERMISSION_KEY } from '../decorators/require-menu-permission.decorator';

/**
 * Server-side counterpart to the frontend menu/tab masking (Layout.tsx canAccessMenu,
 * Settings.tsx visibleSettingsTabs): rejects requests whose caller does not have the
 * required permission id in their resolved menu permissions, instead of relying only
 * on the UI to hide the corresponding tab.
 */
@Injectable()
export class MenuPermissionGuard implements CanActivate {
  constructor(
    private readonly reflector: Reflector,
    private readonly usersService: UsersService
  ) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const requiredPermission = this.reflector.getAllAndOverride<string | undefined>(
      MENU_PERMISSION_KEY,
      [context.getHandler(), context.getClass()]
    );
    if (!requiredPermission) return true;

    const request = context.switchToHttp().getRequest();
    const userId = request?.user?.id;
    if (!userId) {
      throw new ForbiddenException('Authentication required');
    }

    const { isElevated, permissions } = await this.usersService.getCurrentUserMenuPermissions(
      userId
    );
    if (isElevated) return true;

    const hasAccess = (permissions || []).some(
      (permission: string) =>
        permission === requiredPermission || requiredPermission.startsWith(`${permission}.`)
    );

    if (!hasAccess) {
      throw new ForbiddenException(`Accès refusé : permission "${requiredPermission}" requise`);
    }

    return true;
  }
}
