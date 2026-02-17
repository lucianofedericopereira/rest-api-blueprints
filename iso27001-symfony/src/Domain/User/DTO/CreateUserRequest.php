<?php

declare(strict_types=1);

namespace App\Domain\User\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Validated input DTO for user creation and full replacement.
 * A.14: Input validation — all fields are strictly typed and constrained.
 */
final class CreateUserRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email(mode: 'strict')]
        #[Assert\Length(max: 180)]
        public readonly string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 12, max: 128)]
        #[Assert\Regex(
            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/',
            message: 'Password must contain uppercase, lowercase, digit and special character.',
        )]
        public readonly string $password = '',

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_ANALYST', 'ROLE_VIEWER'])]
        public readonly string $role = 'ROLE_VIEWER',
    ) {}
}
