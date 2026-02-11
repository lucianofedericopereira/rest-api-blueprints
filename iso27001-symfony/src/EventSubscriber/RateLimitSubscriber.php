<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * A.17: Rate limiting middleware.
 * - Auth endpoints: 10 req/min per IP (brute-force protection, A.9)
 * - All other API endpoints: 100 req/min per IP (DoS protection, A.17)
 */
final class RateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $anonymousApiLimiter,
        private readonly RateLimiterFactory $loginIpLimiter,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only apply to API routes
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $limiter = str_starts_with($path, '/api/v1/auth/login')
            ? $this->loginIpLimiter->create($request->getClientIp())
            : $this->anonymousApiLimiter->create($request->getClientIp());

        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $request->attributes->set('rate_limit_remaining', $limit->getRemainingTokens());
    }
}
