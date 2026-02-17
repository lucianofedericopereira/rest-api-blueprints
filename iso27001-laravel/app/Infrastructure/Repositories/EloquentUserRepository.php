<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\User\Contracts\UserRepositoryInterface;
use App\Domain\User\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Eloquent implementation of UserRepositoryInterface.
 * A.12: All queries exclude soft-deleted records by default (SoftDeletes trait).
 */
final class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(string $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /** @return LengthAwarePaginator<int, User> */
    public function paginate(int $page, int $perPage): LengthAwarePaginator
    {
        return User::query()
            ->orderBy('created_at', 'desc')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes);
        $user->save();
        /** @var User $fresh */
        $fresh = $user->fresh() ?? $user;
        return $fresh;
    }

    public function softDelete(User $user): void
    {
        $user->delete(); // SoftDeletes trait sets deleted_at
    }
}
