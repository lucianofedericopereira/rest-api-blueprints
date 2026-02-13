<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global API middleware pipeline
        $middleware->api(prepend: [
            \App\Http\Middleware\CorrelationIdMiddleware::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\TelemetryMiddleware::class,  // A.17: error budget + CloudWatch
        ]);

        // Register named middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A.14: Consistent JSON error format for all API routes.
        // Never exposes stack traces, SQL, or internal paths to the client.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (!($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $details = collect($e->errors())
                ->flatMap(fn ($messages, $field) => array_map(
                    fn ($msg) => ['field' => $field, 'message' => $msg, 'code' => 'VALIDATION_FAILED'],
                    $messages,
                ))
                ->values()
                ->all();

            return response()->json([
                'error' => [
                    'code'       => 'VALIDATION_ERROR',
                    'message'    => 'Input validation failed.',
                    'request_id' => $request->header('X-Request-ID', 'unknown'),
                    'details'    => $details,
                ],
            ], 422);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if (!($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $code = match ($e->getStatusCode()) {
                400 => 'BAD_REQUEST',
                401 => 'UNAUTHORIZED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                409 => 'CONFLICT',
                429 => 'RATE_LIMITED',
                default => 'HTTP_ERROR',
            };

            return response()->json([
                'error' => [
                    'code'       => $code,
                    'message'    => $e->getMessage() ?: 'HTTP Error',
                    'request_id' => $request->header('X-Request-ID', 'unknown'),
                ],
            ], $e->getStatusCode());
        });
    })
    ->create();
