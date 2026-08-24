import { ExecutionContext, ForbiddenException } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { MenuPermissionGuard } from './menu-permission.guard';
import { MENU_PERMISSION_KEY } from '../decorators/require-menu-permission.decorator';

describe('MenuPermissionGuard', () => {
  let usersService: { getCurrentUserMenuPermissions: jest.Mock };
  let reflector: Reflector;
  let guard: MenuPermissionGuard;

  const buildContext = (userId: string | undefined): ExecutionContext => {
    const handler = jest.fn();
    const klass = jest.fn();
    return {
      getHandler: () => handler,
      getClass: () => klass,
      switchToHttp: () => ({
        getRequest: () => ({ user: userId ? { id: userId } : undefined }),
      }),
    } as unknown as ExecutionContext;
  };

  beforeEach(() => {
    usersService = { getCurrentUserMenuPermissions: jest.fn() };
    reflector = new Reflector();
    guard = new MenuPermissionGuard(reflector, usersService as any);
  });

  it('allows the request when the route has no @RequireMenuPermission metadata', async () => {
    jest.spyOn(reflector, 'getAllAndOverride').mockReturnValue(undefined);

    await expect(guard.canActivate(buildContext('user-1'))).resolves.toBe(true);
    expect(usersService.getCurrentUserMenuPermissions).not.toHaveBeenCalled();
  });

  it('rejects with ForbiddenException when there is no authenticated user', async () => {
    jest.spyOn(reflector, 'getAllAndOverride').mockReturnValue('administration.users');

    await expect(guard.canActivate(buildContext(undefined))).rejects.toThrow(ForbiddenException);
    expect(usersService.getCurrentUserMenuPermissions).not.toHaveBeenCalled();
  });

  it('allows elevated (super admin) users regardless of their resolved permissions', async () => {
    jest.spyOn(reflector, 'getAllAndOverride').mockReturnValue('administration.user-profiles');
    usersService.getCurrentUserMenuPermissions.mockResolvedValue({
      isElevated: true,
      permissions: [],
      source: 'elevated_role',
    });

    await expect(guard.canActivate(buildContext('user-1'))).resolves.toBe(true);
  });

  it('allows the request when the exact required permission is present', async () => {
    jest.spyOn(reflector, 'getAllAndOverride').mockReturnValue('administration.users');
    usersService.getCurrentUserMenuPermissions.mockResolvedValue({
      isElevated: false,
      permissions: ['dashboard', 'administration.users'],
      source: 'role_profile',
    });

    await expect(guard.canActivate(buildContext('user-1'))).resolves.toBe(true);
  });

  it('allows the request when a parent permission covers the required child permission', async () => {
    jest.spyOn(reflector, 'getAllAndOverride').mockReturnValue('administration.user-profiles');
    usersService.getCurrentUserMenuPermissions.mockResolvedValue({
      isElevated: false,
      permissions: ['administration'],
      source: 'role_profile',
    });

    await expect(guard.canActivate(buildContext('user-1'))).resolves.toBe(true);
  });

  it('rejects with ForbiddenException when the required permission is missing', async () => {
    jest.spyOn(reflector, 'getAllAndOverride').mockReturnValue('administration.user-profiles');
    usersService.getCurrentUserMenuPermissions.mockResolvedValue({
      isElevated: false,
      permissions: ['dashboard', 'administration.users'],
      source: 'role_profile',
    });

    await expect(guard.canActivate(buildContext('user-1'))).rejects.toThrow(ForbiddenException);
  });

  it('reads the permission metadata using the MENU_PERMISSION_KEY', async () => {
    const spy = jest.spyOn(reflector, 'getAllAndOverride').mockReturnValue(undefined);
    const context = buildContext('user-1');

    await guard.canActivate(context);

    expect(spy).toHaveBeenCalledWith(MENU_PERMISSION_KEY, [
      context.getHandler(),
      context.getClass(),
    ]);
  });
});
