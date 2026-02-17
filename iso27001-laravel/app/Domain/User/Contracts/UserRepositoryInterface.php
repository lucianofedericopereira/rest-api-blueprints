<?php

declare(strict_types=1);

namespace App\Domain\User\Contracts;

use App\Domain\User\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contract for User data access.
 * Services depend on this interface — never on Eloquent directly.
 * Enables in-memory test doubles without touching the database.
 */
interface UserRepositoryInterface
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    /** @return LengthAwarePaginator<int, User> */
    public function paginate(int $page, int $perPage): LengthAwarePaginator;

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): User;

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(User $user, array $attributes): User;

    public function softDelete(User $user): void;
}
