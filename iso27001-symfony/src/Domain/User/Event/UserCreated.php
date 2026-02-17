<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

/**
 * Domain event emitted when a new User is successfully persisted.
 *
 * A.12: emailHash — SHA-256 of the email address.
 *       Never include raw email in events (PII).
 *       correlationId links the event to the originating HTTP request.
 */
final readonly class UserCreated
{
    public string $eventId;
    public \DateTimeImmutable $occurredAt;

    public function __construct(
        public string $userId,
        public string $emailHash,
        public string $role,
        public string $correlationId = 'system',
    ) {
        $this->eventId = \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
        $this->occurredAt = new \DateTimeImmutable();
    }
}
