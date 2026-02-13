<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\User\Events\UserCreated;
use App\Domain\User\Events\UserDeleted;
use App\Domain\User\Events\UserUpdated;
use App\Domain\Shared\Contracts\MetricsCollectorInterface;
use Illuminate\Support\Facades\Log;

/**
 * A.12: Automatically emits a metric and structured log for every domain event.
 */
final readonly class TelemetryDomainEventListener
{
    public function __construct(private MetricsCollectorInterface $metrics) {}

    public function handle(UserCreated|UserUpdated|UserDeleted $event): void
    {
        $eventType = class_basename($event);

        $this->metrics->increment('domain_events_total', ['event_type' => $eventType]);

        Log::info('domain_event.dispatched', [
            'event_type'     => $eventType,
            'event_id'       => $event->eventId,
            'correlation_id' => $event->correlationId,
            'occurred_at'    => $event->occurredAt->format(\DateTimeInterface::ATOM),
        ]);
    }
}
