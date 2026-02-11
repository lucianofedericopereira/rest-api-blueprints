<?php

declare(strict_types=1);

namespace App\Domain\User\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class UserDeleted
{
    use Dispatchable;

    public readonly string $eventId;
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly string $userId,
        public readonly string $correlationId = 'system',
    ) {
        $this->eventId    = (string) \Illuminate\Support\Str::uuid();
        $this->occurredAt = new \DateTimeImmutable();
    }
}
