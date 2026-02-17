<?php

declare(strict_types=1);

namespace App\Domain\User\DTO;

use App\Domain\User\Entity\User;

/**
 * Output DTO — controls exactly which fields are exposed.
 * A.14: Never return raw ORM entities or sensitive fields (password, deletedAt).
 */
final class UserResponse
{
    private function __construct(
        public readonly string $id,
        public readonly string $role,
        public readonly bool $active,
        public readonly string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function from(User $user): self
    {
        return new self(
            id: $user->getId(),
            role: $user->getRole(),
            active: $user->isActive(),
            createdAt: $user->getCreatedAt()->format('c'),
            updatedAt: $user->getUpdatedAt()?->format('c'),
        );
    }
}
