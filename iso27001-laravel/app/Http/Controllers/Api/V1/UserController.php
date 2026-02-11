<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\User\DTOs\CreateUserDTO;
use App\Domain\User\DTOs\UpdateUserDTO;
use App\Domain\User\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Resource controller for /api/v1/users.
 *
 * A.9: All write endpoints require 'admin' role.
 *      All read endpoints require 'viewer' role (via route middleware).
 * A.14: Input validated by FormRequest before reaching the controller.
 */
final class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    /** GET /api/v1/users — paginated list */
    public function index(Request $request): AnonymousResourceCollection
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $paginator = $this->userService->listUsers($page, $perPage);

        return UserResource::collection($paginator);
    }

    /** POST /api/v1/users — create user */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $dto = CreateUserDTO::fromArray($request->validated());

        $user = $this->userService->createUser(
            $dto,
            correlationId: $request->header('X-Request-ID', 'system'),
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** GET /api/v1/users/{id} — single user */
    public function show(string $id): JsonResponse
    {
        $user = $this->userService->getUser($id);

        if ($user === null) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'User not found'],
            ], Response::HTTP_NOT_FOUND);
        }

        return (new UserResource($user))->response();
    }

    /** PUT /api/v1/users/{id} — full replacement */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $dto  = UpdateUserDTO::fromArray($request->validated());
        $user = $this->userService->updateUser(
            $id,
            $dto,
            correlationId: $request->header('X-Request-ID', 'system'),
        );

        if ($user === null) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'User not found'],
            ], Response::HTTP_NOT_FOUND);
        }

        return (new UserResource($user))->response();
    }

    /** DELETE /api/v1/users/{id} — soft-delete + audit trail */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $this->userService->deleteUser(
            $id,
            correlationId: $request->header('X-Request-ID', 'system'),
        );

        if (!$deleted) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'User not found'],
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
