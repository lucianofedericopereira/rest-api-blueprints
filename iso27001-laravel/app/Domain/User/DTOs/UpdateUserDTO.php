<?php

declare(strict_types=1);

namespace App\Domain\User\DTOs;

final readonly class UpdateUserDTO
{
    public function __construct(
        public ?string $email = null,
        public ?string $password = null,
        public ?string $role = null,
    ) {}

    /**
     * @param array{email?: string|null, password?: string|null, role?: string|null} $data validated request input
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'] ?? null,
            password: $data['password'] ?? null,
            role: $data['role'] ?? null,
        );
    }
}
