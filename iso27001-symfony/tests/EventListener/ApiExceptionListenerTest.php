<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ApiExceptionListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ApiExceptionListenerTest extends TestCase
{
    private ApiExceptionListener $listener;

    protected function setUp(): void
    {
        $this->listener = new ApiExceptionListener(new NullLogger());
    }

    public function testHandlesNotFoundOnApiRoute(): void
    {
        $request = Request::create('/api/v1/users/unknown-id', 'GET');
        $request->attributes->set('request_id', 'test-req-id');
        $event = $this->createExceptionEvent($request, new NotFoundHttpException('User not found'));

        $this->listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertSame('NOT_FOUND', $body['error']['code']);
        $this->assertSame('test-req-id', $body['error']['request_id']);
    }

    public function testHandlesRateLimitOnApiRoute(): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST');
        $event = $this->createExceptionEvent($request, new TooManyRequestsHttpException(60));

        $this->listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(429, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('RATE_LIMITED', $body['error']['code']);
    }

    public function testNonApiRequestNotHandled(): void
    {
        $request = Request::create('/some/other/path', 'GET');
        $event = $this->createExceptionEvent($request, new NotFoundHttpException());

        $this->listener->onKernelException($event);

        // Should NOT set a response — not an API route
        $this->assertNull($event->getResponse());
    }

    private function createExceptionEvent(Request $request, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
