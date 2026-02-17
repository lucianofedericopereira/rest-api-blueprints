<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Telemetry\ErrorBudgetTracker;
use App\Infrastructure\Telemetry\QualityScoreCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Health check endpoints.
 *
 * A.17: Three tiers — liveness, readiness, detailed diagnostics.
 *       Detailed endpoint is admin-protected.
 */
final class HealthController extends Controller
{
    public function __construct(
        private readonly ErrorBudgetTracker $errorBudget,
        private readonly QualityScoreCalculator $qualityScore,
    ) {}

    /** GET /health — liveness: is the process running? */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /** GET /health/ready — readiness: can it serve traffic? */
    public function readiness(): JsonResponse
    {
        $checks  = [];
        $overall = true;

        // Database check
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $checks['database'] = [
                'status'     => 'ok',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error'];
            $overall = false;
        }

        // Cache / Redis check
        try {
            $start = microtime(true);
            Cache::put('health_check', 'ok', 5);
            Cache::get('health_check');
            $checks['cache'] = [
                'status'     => 'ok',
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'error'];
            $overall = false;
        }

        return response()->json(
            [
                'status'    => $overall ? 'ok' : 'degraded',
                'checks'    => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
            $overall ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    /** GET /health/detailed — detailed diagnostics (admin only) */
    public function detailed(): JsonResponse
    {
        $snapshot     = $this->errorBudget->snapshot();
        $qualityScore = $this->qualityScore->calculate($snapshot);

        return response()->json([
            'status'          => 'ok',
            'version'         => config('app.version', 'unknown'),
            'environment'     => config('app.env'),
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_peak_mb'  => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'db_connections'  => DB::getConnections(),
            'timestamp'       => now()->toIso8601String(),
            'error_budget'    => $snapshot,
            'quality_score'   => $qualityScore,
        ]);
    }
}
