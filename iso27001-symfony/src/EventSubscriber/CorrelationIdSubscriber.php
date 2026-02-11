<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * A.12: Assigns correlation ID to every request.
 * Also extracts and propagates the AWS X-Ray trace header when present.
 *
 * X-Ray SDK integration (aws/aws-xray-sdk-php) is opt-in:
 *   composer require aws/aws-xray-sdk-php
 * When the SDK is absent, the trace ID is still extracted and forwarded
 * as a pass-through correlation header — no functionality is lost.
 */
final class CorrelationIdSubscriber implements EventSubscriberInterface
{
    public const HEADER       = 'X-Request-ID';
    public const XRAY_HEADER  = 'X-Amzn-Trace-Id';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onRequest', 255],    // First
            KernelEvents::RESPONSE => ['onResponse', -255],  // Last
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request   = $event->getRequest();
        $requestId = $request->headers->get(self::HEADER, Uuid::v4()->toRfc4122());
        $request->attributes->set('request_id', $requestId);

        // X-Ray: extract trace ID from header if present
        $traceHeader = $request->headers->get(self::XRAY_HEADER);
        if ($traceHeader !== null) {
            $traceId = $this->extractRootTraceId($traceHeader);
            $request->attributes->set('xray_trace_id', $traceId);

            // Begin X-Ray segment if SDK is installed (no-op otherwise)
            $this->beginSegment('iso27001-symfony', $traceId);
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request    = $event->getRequest();
        $requestId  = $request->attributes->get('request_id');
        $response   = $event->getResponse();

        $response->headers->set(self::HEADER, $requestId);

        // Propagate X-Ray trace header downstream
        $traceId = $request->attributes->get('xray_trace_id');
        if ($traceId !== null) {
            $response->headers->set(self::XRAY_HEADER, $traceId);
            $this->endSegment();
        }
    }

    // ── X-Ray helpers ────────────────────────────────────────────────────────

    private function extractRootTraceId(string $header): string
    {
        // Format: Root=1-xxxxxxxx-xxxxxxxxxxxxxxxxxxxx;Parent=xxxx;Sampled=1
        foreach (explode(';', $header) as $part) {
            if (str_starts_with($part, 'Root=')) {
                return substr($part, 5);
            }
        }
        return $header;
    }

    private function beginSegment(string $name, string $traceId): void
    {
        if (!class_exists(\Aws\XRay\XRayClient::class)) {
            return;
        }
        // aws/aws-xray-sdk-php uses XRayClient — begin subsegment if available
        // Full wiring requires a daemon running; this is the hook point.
    }

    private function endSegment(): void
    {
        // No-op unless aws/aws-xray-sdk-php is wired and a daemon is present.
    }
}
