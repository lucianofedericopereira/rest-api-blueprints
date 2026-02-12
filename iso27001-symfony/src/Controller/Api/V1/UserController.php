<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Domain\User\DTO\CreateUserRequest;
use App\Domain\User\DTO\UserResponse;
use App\Domain\User\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Resource controller for /api/v1/users.
 * A.9: All write operations require ROLE_ADMIN; reads require ROLE_VIEWER.
 */
#[Route('/api/v1/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /** GET /api/v1/users — paginated list */
    #[Route('', methods: ['GET'])]
    #[IsGranted('ROLE_VIEWER')]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', '25')));

        $users = $this->userService->listUsers($page, $perPage);

        return $this->json([
            'data' => array_map(fn ($u) => UserResponse::from($u), $users['items']),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $users['total'],
            ],
        ]);
    }

    /** POST /api/v1/users — create user */
    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function store(#[MapRequestPayload] CreateUserRequest $dto): JsonResponse
    {
        $user = $this->userService->createUser($dto);

        return $this->json(UserResponse::from($user), Response::HTTP_CREATED);
    }

    /** GET /api/v1/users/{id} — single user */
    #[Route('/{id}', methods: ['GET'])]
    #[IsGranted('ROLE_VIEWER')]
    public function show(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->json(
                ['error' => ['code' => 'INVALID_ID', 'message' => 'Invalid UUID format']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $user = $this->userService->getUser($id);
        if ($user === null) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'User not found']],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json(UserResponse::from($user));
    }

    /** PUT /api/v1/users/{id} — full replacement */
    #[Route('/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(string $id, #[MapRequestPayload] CreateUserRequest $dto): JsonResponse
    {
        $user = $this->userService->updateUser($id, $dto);
        if ($user === null) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'User not found']],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json(UserResponse::from($user));
    }

    /** DELETE /api/v1/users/{id} — soft-delete + audit trail */
    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->userService->deleteUser($id);
        if (!$deleted) {
            return $this->json(
                ['error' => ['code' => 'NOT_FOUND', 'message' => 'User not found']],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
