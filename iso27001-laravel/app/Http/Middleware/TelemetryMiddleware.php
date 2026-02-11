<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Telemetry\CloudWatchEmitter;
use App\Infrastructure\Telemetry\ErrorBudgetTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A.17: Wires ErrorBudgetTracker and CloudWatchEmitter into the request lifecycle.
 *
 * Executes AFTER the response is built so the final HTTP status code is
 * always available. Registered as the last middleware in the API pipeline
 * (lowest priority — outermost wrap in bootstrap/app.php).
 *
 * Both integrations degrade gracefully:
 *   - ErrorBudgetTracker: Redis-backed when REDIS_HOST is configured; in-process otherwise.
 *   - CloudWatchEmitter: calls PutMetricData when aws/aws-sdk-php + credentials present;
 *     silent no-op otherwise.
 */
final class TelemetryMiddleware
{
    public function __construct(
        private readonly ErrorBudgetTracker $errorBudget,
        private readonly CloudWatchEmitter $cloudWatch,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $statusCode = $response->getStatusCode();
        $durationMs = round((microtime(true) - $start) * 1000, 2);

        // A.17: Record in error budget (5xx responses consume budget)
        $this->errorBudget->record($statusCode);

        // CloudWatch custom metrics (no-op when SDK / credentials absent)
        $this->cloudWatch->emitRequest(
            method:     $request->getMethod(),
            path:       $request->getPathInfo(),
            statusCode: $statusCode,
            durationMs: $durationMs,
        );

        return $response;
    }
}
