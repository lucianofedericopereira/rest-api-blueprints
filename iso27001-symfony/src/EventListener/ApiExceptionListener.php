<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * A.14: Global exception handler.
 * - Returns consistent JSON error format to clients.
 * - A.14: Never exposes stack traces, SQL, or internal paths in responses.
 * - A.12: Full details are logged server-side with correlation ID.
 */
final readonly class ApiExceptionListener
{
    public function __construct(private LoggerInterface $logger) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle JSON API requests
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $requestId = $request->attributes->get('request_id', 'unknown');

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage() ?: Response::$statusTexts[$statusCode] ?? 'HTTP Error';
            $code = $this->codeFromStatus($statusCode);
        } else {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = 'An unexpected error occurred.';
            $code = 'INTERNAL_ERROR';

            // A.12: Log full details server-side (never sent to client)
            $this->logger->error('unhandled_exception', [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'request_id' => $requestId,
            ]);
        }

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $requestId,
            ],
        ], $statusCode));
    }

    private function codeFromStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMITED',
            default => 'HTTP_ERROR',
        };
    }
}
