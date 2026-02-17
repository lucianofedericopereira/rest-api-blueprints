<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * A.14: Global exception handler.
 * - Returns consistent JSON error format for all API routes.
 * - A.14: Never exposes stack traces, SQL, or internal paths to the client.
 * - A.12: Full exception details logged server-side with correlation ID.
 */
final class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = ['current_password', 'password', 'password_confirmation'];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Structured logging handled by the default log channel
        });
    }

    public function render($request, Throwable $e): mixed
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    private function renderApiException(Request $request, Throwable $e): JsonResponse
    {
        $requestId = $request->header('X-Request-ID', 'unknown');

        if ($e instanceof ValidationException) {
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
                    'request_id' => $requestId,
                    'details'    => $details,
                ],
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Unauthenticated.', 'request_id' => $requestId],
            ], 401);
        }

        if ($e instanceof HttpException) {
            return response()->json([
                'error' => [
                    'code'       => $this->codeFromStatus($e->getStatusCode()),
                    'message'    => $e->getMessage() ?: 'HTTP Error',
                    'request_id' => $requestId,
                ],
            ], $e->getStatusCode());
        }

        // A.14: Internal errors — never expose internals
        return response()->json([
            'error' => [
                'code'       => 'INTERNAL_ERROR',
                'message'    => 'An unexpected error occurred.',
                'request_id' => $requestId,
            ],
        ], 500);
    }

    private function codeFromStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            429 => 'RATE_LIMITED',
            default => 'HTTP_ERROR',
        };
    }
}
