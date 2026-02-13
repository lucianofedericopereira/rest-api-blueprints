<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\RateLimitSubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use App\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;

class RateLimitSubscriberTest extends TestCase
{
    private RateLimiterFactoryInterface|MockObject $anonymousApiLimiter;
    private RateLimiterFactoryInterface|MockObject $loginIpLimiter;
    private RateLimiterFactoryInterface|MockObject $writeApiLimiter;
    private RateLimitSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->anonymousApiLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $this->loginIpLimiter      = $this->createMock(RateLimiterFactoryInterface::class);
        $this->writeApiLimiter     = $this->createMock(RateLimiterFactoryInterface::class);

        $this->subscriber = new RateLimitSubscriber(
            $this->anonymousApiLimiter,
            $this->loginIpLimiter,
            $this->writeApiLimiter,
        );
    }

    public function testLoginRateLimitAccepted(): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST');
        $event = $this->createRequestEvent($request);

        $limiter = $this->createMock(LimiterInterface::class);
        $limit = $this->createMock(RateLimit::class);

        $this->loginIpLimiter->expects($this->once())
            ->method('create')
            ->with($request->getClientIp())
            ->willReturn($limiter);

        $limiter->expects($this->once())
            ->method('consume')
            ->with(1)
            ->willReturn($limit);

        $limit->expects($this->once())
            ->method('isAccepted')
            ->willReturn(true);

        $limit->expects($this->once())
            ->method('getRemainingTokens')
            ->willReturn(9);

        $this->subscriber->onKernelRequest($event);

        $this->assertEquals(9, $request->attributes->get('rate_limit_remaining'));
    }

    public function testLoginRateLimitExceeded(): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST');
        $event = $this->createRequestEvent($request);

        $limiter = $this->createMock(LimiterInterface::class);
        $limit = $this->createMock(RateLimit::class);

        $this->loginIpLimiter->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

        $limiter->expects($this->once())
            ->method('consume')
            ->willReturn($limit);

        $limit->expects($this->once())
            ->method('isAccepted')
            ->willReturn(false);

        $limit->expects($this->once())
            ->method('getRetryAfter')
            ->willReturn(new \DateTimeImmutable('+60 seconds'));

        $this->expectException(TooManyRequestsHttpException::class);

        $this->subscriber->onKernelRequest($event);
    }

    public function testWriteEndpointUsesWriteLimiter(): void
    {
        $request = Request::create('/api/v1/users', 'POST');
        $event   = $this->createRequestEvent($request);

        $limiter = $this->createMock(LimiterInterface::class);
        $limit   = $this->createMock(RateLimit::class);

        $this->writeApiLimiter->expects($this->once())
            ->method('create')
            ->willReturn($limiter);

        $limiter->method('consume')->willReturn($limit);
        $limit->method('isAccepted')->willReturn(true);
        $limit->method('getRemainingTokens')->willReturn(29);

        $this->subscriber->onKernelRequest($event);

        $this->assertEquals(29, $request->attributes->get('rate_limit_remaining'));
    }

    public function testNonApiRequestIgnored(): void
    {
        $request = Request::create('/health', 'GET');
        $event = $this->createRequestEvent($request);

        $this->anonymousApiLimiter->expects($this->never())->method('create');
        $this->loginIpLimiter->expects($this->never())->method('create');
        $this->writeApiLimiter->expects($this->never())->method('create');

        $this->subscriber->onKernelRequest($event);
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
