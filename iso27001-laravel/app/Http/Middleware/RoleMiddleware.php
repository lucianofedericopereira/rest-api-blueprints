<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\User\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A.9: RBAC middleware — verifies the authenticated user has the required role.
 * Role hierarchy: admin > manager > analyst > viewer
 *
 * Usage in routes: ->middleware('role:admin') or ->middleware('role:viewer')
 */
final class RoleMiddleware
{
    private const HIERARCHY = [
        'admin'   => 4,
        'manager' => 3,
        'analyst' => 2,
        'viewer'  => 1,
    ];

    public function handle(Request $request, Closure $next, string $requiredRole): mixed
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Unauthenticated.'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userLevel     = self::HIERARCHY[$user->role] ?? 0;
        $requiredLevel = self::HIERARCHY[$requiredRole] ?? PHP_INT_MAX;

        if ($userLevel < $requiredLevel) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Insufficient permissions.'],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
