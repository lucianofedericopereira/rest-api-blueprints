<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

/**
 * Immutable data transfer object for user creation.
 * A.14: Validated at the HTTP layer before reaching the service.
 */
final readonly class CreateUserDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $role = 'viewer',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            role: $data['role'] ?? 'viewer',
        );
    }
}
