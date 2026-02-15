import { Injectable, CanActivate, ExecutionContext, ForbiddenException } from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { ROLES_KEY } from '../decorators/roles.decorator';
import { UserRole, ROLE_HIERARCHY } from '../../domain/users/user.entity';

/**
 * A.9: RBAC guard.
 * Enforces minimum role requirement on routes decorated with @Roles().
 * Uses ROLE_HIERARCHY so a higher role satisfies a lower requirement.
 */
@Injectable()
export class RolesGuard implements CanActivate {
  constructor(private readonly reflector: Reflector) {}

  canActivate(context: ExecutionContext): boolean {
    const requiredRole = this.reflector.getAllAndOverride<UserRole>(ROLES_KEY, [
      context.getHandler(),
      context.getClass(),
    ]);

    if (!requiredRole) return true;

    const request = context.switchToHttp().getRequest<{ user?: { role: UserRole } }>();
    const user = request.user;

    if (!user) {
      throw new ForbiddenException({ code: 'FORBIDDEN', message: 'Access denied' });
    }

    const userLevel = ROLE_HIERARCHY[user.role] ?? -1;
    const requiredLevel = ROLE_HIERARCHY[requiredRole] ?? 0;

    if (userLevel < requiredLevel) {
      throw new ForbiddenException({ code: 'FORBIDDEN', message: 'Insufficient role' });
    }

    return true;
  }
}
