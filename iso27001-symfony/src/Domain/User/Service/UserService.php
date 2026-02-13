<?php

declare(strict_types=1);

namespace App\Domain\User\Service;

use App\Audit\AuditServiceInterface;
use App\Domain\User\DTO\CreateUserRequest;
use App\Domain\User\Entity\User;
use App\Domain\User\Event\UserCreated;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Application service for User bounded context.
 * Orchestrates business logic, delegates data access to the repository,
 * and emits domain events for cross-cutting concerns (audit, cache, telemetry).
 */
final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AuditServiceInterface $auditService,
    ) {}

    public function createUser(CreateUserRequest $dto): User
    {
        if ($this->repository->findByEmail($dto->email) !== null) {
            throw new \DomainException('A user with this email already exists.');
        }

        $user = new User(
            id: Uuid::v4()->toRfc4122(),
            email: $dto->email,
            password: '',
            role: $dto->role,
        );

        // A.10: Hash password with Argon2/Bcrypt via Symfony's hasher
        $hashed = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashed);

        $this->repository->save($user);

        // Dispatch domain event — listeners handle audit, cache, telemetry
        $this->eventDispatcher->dispatch(new UserCreated(
            userId: $user->getId(),
            emailHash: hash('sha256', $dto->email), // A.12: never log raw email
            role: $user->getRole(),
        ));

        return $user;
    }

    public function getUser(string $id): ?User
    {
        return $this->repository->findById($id);
    }

    /** @return array{items: User[], total: int} */
    public function listUsers(int $page = 1, int $perPage = 25): array
    {
        return $this->repository->findPaginated($page, $perPage);
    }

    public function updateUser(string $id, CreateUserRequest $dto): ?User
    {
        $user = $this->repository->findById($id);
        if ($user === null) {
            return null;
        }

        $user->setEmail($dto->email);
        $user->setRole($dto->role);

        if ($dto->password !== '') {
            $hashed = $this->passwordHasher->hashPassword($user, $dto->password);
            $user->setPassword($hashed);
        }

        $user->touch();
        $this->repository->save($user);

        $this->auditService->record(
            action: 'user.updated',
            performedBy: $id,
            resourceType: 'user',
            resourceId: $user->getId(),
        );

        return $user;
    }

    public function deleteUser(string $id): bool
    {
        $user = $this->repository->findById($id);
        if ($user === null) {
            return false;
        }

        // A.12: Soft delete preserves audit trail
        $user->softDelete();
        $this->repository->save($user);

        $this->auditService->record(
            action: 'user.deleted',
            performedBy: $id,
            resourceType: 'user',
            resourceId: $user->getId(),
        );

        return true;
    }
}
