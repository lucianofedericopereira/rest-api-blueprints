<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Audit\AuditService;
use App\Domain\User\Event\UserCreated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A.12: Listens to domain events and records immutable audit entries.
 * Decouples the audit trail from business logic — UserService never calls AuditService directly for creation.
 */
final class AuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UserCreated::class => 'onUserCreated',
        ];
    }

    public function onUserCreated(UserCreated $event): void
    {
        $this->auditService->record(
            action: 'user.created',
            performedBy: $event->userId,
            resourceType: 'user',
            resourceId: $event->userId,
            changes: [
                'role' => $event->role,
                'email_hash' => $event->emailHash, // A.12: hash only, never raw email
            ],
        );
    }
}
