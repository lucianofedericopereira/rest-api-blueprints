<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\User\Events\UserCreated;
use App\Domain\Shared\Contracts\AuditServiceInterface;

/**
 * A.12: Records an immutable audit entry when a user is created.
 * Decoupled from UserService via the domain event — no direct dependency.
 */
final readonly class AuditUserCreated
{
    public function __construct(private AuditServiceInterface $auditService) {}

    public function handle(UserCreated $event): void
    {
        $this->auditService->record(
            action:       'user.created',
            performedBy:  $event->userId,
            resourceType: 'user',
            resourceId:   $event->userId,
            changes:      [
                'role'       => $event->role,
                'email_hash' => $event->emailHash, // A.12: hash only
            ],
        );
    }
}
