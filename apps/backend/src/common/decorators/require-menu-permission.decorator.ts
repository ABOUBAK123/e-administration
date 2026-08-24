import { SetMetadata } from '@nestjs/common';

export const MENU_PERMISSION_KEY = 'menuPermission';

/**
 * Requires the connected user's resolved menu permissions (same source as the
 * frontend menu/tab visibility, see UsersService.getCurrentUserMenuPermissions)
 * to include the given permission id, e.g. 'administration.users'.
 * Elevated roles (super admin) always pass.
 */
export const RequireMenuPermission = (permission: string) =>
  SetMetadata(MENU_PERMISSION_KEY, permission);
