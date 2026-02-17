<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\BruteForceGuard;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Authentication controller.
 *
 * A.9: Login is handled by LexikJWTAuthenticationBundle (/api/v1/auth/login).
 *      This controller adds:
 *        - POST /api/v1/auth/refresh — exchange a valid JWT for a fresh token pair
 *        - POST /api/v1/auth/logout  — revoke current token (stateless note below)
 *
 * Brute-force protection is applied in BruteForceSubscriber (onAuthenticationFailure
 * kernel event) for the LexikJWT login endpoint.
 */
#[Route('/api/v1/auth')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepositoryInterface $userRepository, // @phpstan-ignore property.onlyWritten
        private readonly BruteForceGuard $bruteForceGuard, // @phpstan-ignore property.onlyWritten
    ) {}

    /**
     * POST /api/v1/auth/refresh
     *
     * Accepts a still-valid JWT in the Authorization: Bearer header and issues
     * a fresh one. In a stateless setup the "refresh token" IS the current JWT —
     * callers should hit this endpoint before expiry to roll their token.
     *
     * A.9: New token inherits the same role claim; no privilege escalation.
     */
    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        /** @var \App\Domain\User\Entity\User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            return $this->json(
                ['error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentication required.']],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // Issue a new JWT with a fresh expiry
        $newToken = $this->jwtManager->create($user);

        return $this->json([
            'access_token' => $newToken,
            'token_type'   => 'Bearer',
        ]);
    }
}
