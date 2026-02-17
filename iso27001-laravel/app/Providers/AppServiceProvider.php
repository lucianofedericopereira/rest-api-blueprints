<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Shared\Contracts\AuditServiceInterface;
use App\Domain\Shared\Contracts\MetricsCollectorInterface;
use App\Domain\User\Contracts\UserRepositoryInterface;
use App\Infrastructure\Audit\AuditService;
use App\Infrastructure\Repositories\EloquentUserRepository;
use App\Infrastructure\Telemetry\MetricsCollector;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind repository interface to Eloquent implementation
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);

        // Bind cross-cutting concern interfaces to Infrastructure implementations
        $this->app->bind(AuditServiceInterface::class, AuditService::class);
        $this->app->bind(MetricsCollectorInterface::class, MetricsCollector::class);
    }

    public function boot(): void {}
}
