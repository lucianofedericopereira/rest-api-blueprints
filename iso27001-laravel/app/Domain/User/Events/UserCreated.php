<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Domain event emitted when a User aggregate is successfully persisted.
 *
 * A.12: emailHash — SHA-256 of the email address.
 *       Never include raw email in events or logs (PII).
 *       correlationId links the event to the originating HTTP request.
 */
final class UserCreated
{
    use Dispatchable;

    public readonly string $eventId;
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $userId,
        public readonly string $emailHash,   // A.12: SHA-256 hash only
        public readonly string $role,
        public readonly string $correlationId = 'system',
    ) {
        $this->eventId    = (string) \Illuminate\Support\Str::uuid();
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function toLogContext(): array
    {
        return [
            'event_type'      => self::class,
            'event_id'        => $this->eventId,
            'user_id'         => $this->userId,
            'email_hash'      => $this->emailHash,
            'role'            => $this->role,
            'correlation_id'  => $this->correlationId,
            'occurred_at'     => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
