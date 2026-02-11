<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A.12: Assigns a correlation ID to every request.
 * Also extracts and propagates the AWS X-Ray trace header when present.
 *
 * X-Ray SDK integration (aws/aws-sdk-php) is opt-in:
 *   composer require aws/aws-sdk-php
 * When the SDK is absent, the trace ID is still extracted and forwarded
 * as a pass-through correlation header — no functionality is lost.
 */
final class CorrelationIdMiddleware
{
    public const HEADER      = 'X-Request-ID';
    public const XRAY_HEADER = 'X-Amzn-Trace-Id';

    public function handle(Request $request, Closure $next): Response
    {
        // Preserve client-provided ID or generate a new one
        $requestId = $request->header(self::HEADER) ?? (string) Str::uuid();
        $request->headers->set(self::HEADER, $requestId);

        // Make available to logging context
        config(['request.id' => $requestId]);

        // X-Ray: extract trace ID from header if present (no-op when absent)
        $traceId = null;
        $traceHeader = $request->header(self::XRAY_HEADER);
        if ($traceHeader !== null) {
            $traceId = $this->extractRootTraceId($traceHeader);
            config(['request.xray_trace_id' => $traceId]);
        }

        /** @var Response $response */
        $response = $next($request);

        // Inject correlation headers into response for client-side tracing
        $response->headers->set(self::HEADER, $requestId);
        if ($traceId !== null) {
            $response->headers->set(self::XRAY_HEADER, $traceId);
        }

        return $response;
    }

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
}
