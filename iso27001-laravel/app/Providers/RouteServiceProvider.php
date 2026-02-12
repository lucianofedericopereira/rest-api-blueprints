<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\User\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

final class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        });
    }

    /**
     * A.17: Rate limiting configuration.
     * A.9:  Login endpoint has stricter limit (brute-force protection).
     */
    private function configureRateLimiting(): void
    {
        // Global API: 100 req/min per IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        // Auth endpoints: 10 req/min per IP (A.9: brute-force protection)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Write operations: 30 req/min per authenticated user token
        RateLimiter::for('write', function (Request $request) {
            /** @var User|null $user */
            $user = $request->user();
            return Limit::perMinute(30)->by(
                $user !== null ? $user->id : $request->ip()
            );
        });
    }
}
