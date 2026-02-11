<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\User;

/**
 * Contract for User data access.
 * Services depend on this interface, never on a concrete implementation.
 */
interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function findByEmail(string $email): ?User;

    /** @return array{items: User[], total: int} */
    public function findPaginated(int $page, int $perPage): array;

    public function save(User $user): void;
    public function remove(User $user): void;
}
