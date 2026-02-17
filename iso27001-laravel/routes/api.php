<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Versioned under /api/v1
|--------------------------------------------------------------------------
|
| Middleware pipeline (applied globally via bootstrap/app.php):
|   1. CorrelationIdMiddleware   — A.12: assigns X-Request-ID
|   2. SecurityHeadersMiddleware — A.10: HSTS, CSP, X-Frame-Options
|   3. throttle:api              — A.17: global rate limit
|   4. auth:sanctum              — A.9: JWT validation
|   5. RoleMiddleware            — A.9: RBAC
|
*/

// Prometheus metrics — admin only (A.17)
Route::middleware(['auth:sanctum', 'role:admin'])->get('/metrics', MetricsController::class);

// Health checks — public, no auth required
Route::get('/health', [HealthController::class, 'liveness'])->withoutMiddleware(['auth:sanctum']);
Route::get('/health/ready', [HealthController::class, 'readiness'])->withoutMiddleware(['auth:sanctum']);
Route::middleware('auth:sanctum')->get('/health/detailed', [HealthController::class, 'detailed'])
    ->middleware('role:admin'); // A.17: detailed diagnostics — admin only

// Auth — public, strict rate limit (A.9: brute-force protection)
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->withoutMiddleware(['auth:sanctum'])
        ->middleware('throttle:login');

    // A.9: Refresh — requires a valid (non-expired) token; issues a new token pair
    Route::post('/refresh', [AuthController::class, 'refresh'])
        ->middleware(['auth:sanctum', 'throttle:login']);

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');
});

// User resource — authenticated + RBAC
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Read operations — viewer and above
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('role:viewer');
    Route::get('/users/{id}', [UserController::class, 'show'])
        ->middleware('role:viewer');

    // Write operations — admin only + stricter rate limit
    Route::post('/users', [UserController::class, 'store'])
        ->middleware(['role:admin', 'throttle:write']);
    Route::put('/users/{id}', [UserController::class, 'update'])
        ->middleware(['role:admin', 'throttle:write']);
    Route::delete('/users/{id}', [UserController::class, 'destroy'])
        ->middleware(['role:admin', 'throttle:write']);
});
