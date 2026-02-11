<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Infrastructure\Telemetry\CloudWatchEmitter;
use App\Infrastructure\Telemetry\ErrorBudgetTracker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A.17: Wires ErrorBudgetTracker and CloudWatchEmitter into the request lifecycle.
 *
 * Runs at priority -200 (after all other response subscribers) on every main
 * request so the final HTTP status code is always available.
 *
 * Both integrations degrade gracefully:
 *   - ErrorBudgetTracker: Redis-backed when REDIS_URL is set; in-process otherwise.
 *   - CloudWatchEmitter: calls PutMetricData when aws/aws-sdk-php is installed and
 *     credentials are present; silent no-op otherwise.
 */
final class TelemetrySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ErrorBudgetTracker $errorBudget,
        private readonly CloudWatchEmitter $cloudWatch,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -200],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request    = $event->getRequest();
        $response   = $event->getResponse();
        $statusCode = $response->getStatusCode();

        // A.17: Record in error budget (5xx responses consume budget)
        $this->errorBudget->record($statusCode);

        // CloudWatch custom metrics (no-op when SDK / credentials absent)
        $startTime = $request->server->get('REQUEST_TIME_FLOAT', microtime(true));
        $durationMs = round((microtime(true) - (float) $startTime) * 1000, 2);

        $this->cloudWatch->emitRequest(
            method:     $request->getMethod(),
            path:       $request->getPathInfo(),
            statusCode: $statusCode,
            durationMs: $durationMs,
        );
    }
}
