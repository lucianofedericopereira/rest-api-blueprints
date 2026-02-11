<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\User\Events\UserCreated;
use App\Domain\User\Events\UserDeleted;
use App\Domain\User\Events\UserUpdated;
use App\Listeners\AuditUserCreated;
use App\Listeners\AuditUserDeleted;
use App\Listeners\AuditUserUpdated;
use App\Listeners\TelemetryDomainEventListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * Domain event → listener mappings.
     * A.12: Audit listeners are always registered regardless of environment.
     */
    protected $listen = [
        UserCreated::class => [
            AuditUserCreated::class,
            TelemetryDomainEventListener::class,
        ],
        UserUpdated::class => [
            AuditUserUpdated::class,
            TelemetryDomainEventListener::class,
        ],
        UserDeleted::class => [
            AuditUserDeleted::class,
            TelemetryDomainEventListener::class,
        ],
    ];

    public function boot(): void {}

    public function shouldDiscoverEvents(): bool
    {
        return false; // Explicit registration only — no magic discovery
    }
}
