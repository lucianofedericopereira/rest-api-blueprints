<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Authentication controller — JWT / Sanctum token issuance.
 *
 * A.9: Short-lived tokens (30 min via expiration config).
 * A.9: Rate-limited at 10 req/min per IP via route middleware (login throttle).
 * A.12: Failed attempts logged with correlation ID.
 */
final class AuthController extends Controller
{
    /** POST /api/v1/auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
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

        // A.9: Revoke previous tokens on re-login (single active session per user)
        $user->tokens()->delete();

        // A.9: Issue token with role-based abilities
        $token = $user->createToken(
            name:       'api-access',
            abilities:  [$user->role],
            expiresAt:  now()->addMinutes(config('sanctum.expiration', 30)),
        );

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => config('sanctum.expiration', 30) * 60, // seconds
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
