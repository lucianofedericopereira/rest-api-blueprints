<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Contracts\UserRepositoryInterface;
use App\Domain\User\DTOs\CreateUserDTO;
use App\Domain\User\DTOs\UpdateUserDTO;
use App\Domain\User\Events\UserCreated;
use App\Domain\User\Events\UserDeleted;
use App\Domain\User\Events\UserUpdated;
use App\Domain\User\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Event;

/**
 * Application service for the User bounded context.
 * Orchestrates business logic, delegates data access to the repository,
 * and dispatches domain events for audit, cache, and telemetry.
 *
 * A.9: Enforces business invariants (duplicate email check).
 */
final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
    ) {}

    public function createUser(CreateUserDTO $dto, string $correlationId = 'system'): User
    {
        if ($this->repository->findByEmail($dto->email) !== null) {
            throw new \DomainException('A user with this email already exists.');
        }

        $user = $this->repository->create([
            'email'    => $dto->email,
            'password' => $dto->password,
            'role'     => $dto->role,
        ]);

        // Dispatch domain event — listeners handle audit, telemetry, notifications
        Event::dispatch(new UserCreated(
            userId:        $user->id,
            emailHash:     $user->emailHash(), // A.12: SHA-256 only
            role:          $user->role,
            correlationId: $correlationId,
        ));

        return $user;
    }

    public function getUser(string $id): ?User
    {
        return $this->repository->findById($id);
    }

    /** @return LengthAwarePaginator<int, User> */
    public function listUsers(int $page = 1, int $perPage = 25): LengthAwarePaginator
    {
        return $this->repository->paginate($page, $perPage);
    }

    public function updateUser(string $id, UpdateUserDTO $dto, string $correlationId = 'system'): ?User
    {
        $user = $this->repository->findById($id);
        if ($user === null) {
            return null;
        }

        $changes = array_filter([
            'email' => $dto->email,
            'role'  => $dto->role,
        ]);

        if ($dto->password !== null) {
            $changes['password'] = $dto->password;
        }

        $user = $this->repository->update($user, $changes);

        Event::dispatch(new UserUpdated(
            userId:        $user->id,
            changes:       array_keys($changes), // log keys only, not values (A.12)
            correlationId: $correlationId,
        ));

        return $user;
    }

    public function deleteUser(string $id, string $correlationId = 'system'): bool
    {
        $user = $this->repository->findById($id);
        if ($user === null) {
            return false;
        }

        // A.12: Soft delete — record preserved for audit trail
        $this->repository->softDelete($user);

        Event::dispatch(new UserDeleted(
            userId:        $user->id,
            correlationId: $correlationId,
        ));

        return true;
    }
}
