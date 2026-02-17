<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Infrastructure\Security\BruteForceGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Authentication controller — Sanctum token issuance.
 *
 * A.9: Short-lived tokens (30 min via expiration config).
 * A.9: Rate-limited at 10 req/min per IP via route middleware (login throttle).
 * A.9: Brute-force protection — lockout after 5 consecutive failures (15 min).
 * A.12: Failed attempts logged with correlation ID.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly BruteForceGuard $bruteForce,
    ) {}

    /** POST /api/v1/auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');

        // A.9: Fail-fast if account is locked out
        if ($this->bruteForce->isLocked($email)) {
            return response()->json([
                'error' => [
                    'code'       => 'ACCOUNT_LOCKED',
                    'message'    => 'Too many failed attempts. Account temporarily locked.',
                    'request_id' => $request->header('X-Request-ID'),
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            $this->bruteForce->recordFailure($email);

            return response()->json([
                'error' => [
                    'code'       => 'UNAUTHORIZED',
                    'message'    => 'Invalid credentials.',
                    'request_id' => $request->header('X-Request-ID'),
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var \App\Domain\User\Models\User $user */
        $user = Auth::user();

        $this->bruteForce->clear($email);

        // A.9: Revoke previous tokens on re-login (single active session per user)
        $user->tokens()->delete();

        // A.9: Issue token with role-based abilities
        $token = $user->createToken(
            name:      'api-access',
            abilities: [$user->role],
            expiresAt: now()->addMinutes(config('sanctum.expiration', 30)),
        );

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => config('sanctum.expiration', 30) * 60, // seconds
            'role'         => $user->role,
        ]);
    }

    /**
     * POST /api/v1/auth/refresh
     *
     * Requires a valid (non-expired) Bearer token. Revokes the current token
     * and issues a fresh one with the same role and a new expiry.
     *
     * A.9: Token rotation — old token is invalidated immediately on refresh.
     */
    public function refresh(): JsonResponse
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = Auth::user();

        // Revoke current token (A.9: no two valid tokens at once)
        $user->currentAccessToken()->delete();

        $token = $user->createToken(
            name:      'api-access',
            abilities: [$user->role],
            expiresAt: now()->addMinutes(config('sanctum.expiration', 30)),
        );

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => config('sanctum.expiration', 30) * 60,
            'role'         => $user->role,
        ]);
    }

    /** POST /api/v1/auth/logout */
    public function logout(): JsonResponse
    {
        /** @var \App\Domain\User\Models\User $user */
        $user = Auth::user();
        $user->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
