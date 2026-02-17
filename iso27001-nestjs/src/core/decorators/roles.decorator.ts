import { SetMetadata } from '@nestjs/common';
import { UserRole } from '../../domain/users/user.entity';

export const ROLES_KEY = 'roles';
/** Require a minimum role on a route handler. */
export const Roles = (role: UserRole) => SetMetadata(ROLES_KEY, role);
