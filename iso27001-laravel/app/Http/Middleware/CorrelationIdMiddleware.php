<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A.12: Assigns a correlation ID to every request.
 * Propagates through logs, domain events, downstream calls, and HTTP responses.
 */
final class CorrelationIdMiddleware
{
    public const HEADER = 'X-Request-ID';

    public function handle(Request $request, Closure $next): Response
    {
        // Preserve client-provided ID or generate a new one
        $requestId = $request->header(self::HEADER) ?? (string) Str::uuid();
        $request->headers->set(self::HEADER, $requestId);

        // Make available to logging context
        config(['request.id' => $requestId]);

        /** @var Response $response */
        $response = $next($request);

        // Inject into response so the client can trace it
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
