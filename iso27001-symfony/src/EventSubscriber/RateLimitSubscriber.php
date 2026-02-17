<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use App\RateLimiter\RateLimiterFactoryInterface;

/**
 * A.17: Rate limiting subscriber — three tiers.
 * - Auth endpoints:  10 req/min per IP  (login — A.9: brute-force protection)
 * - Write endpoints: 30 req/min per IP  (POST/PUT/PATCH/DELETE — A.17)
 * - All other API:  100 req/min per IP  (DoS protection — A.17)
 */
final class RateLimitSubscriber implements EventSubscriberInterface
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly RateLimiterFactoryInterface $anonymousApiLimiter,
        private readonly RateLimiterFactoryInterface $loginIpLimiter,
        private readonly RateLimiterFactoryInterface $writeApiLimiter,
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
        $path    = $request->getPathInfo();
        $method  = strtoupper($request->getMethod());

        // Only apply to API routes
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $ip = (string) $request->getClientIp();

        if (str_starts_with($path, '/api/v1/auth/login')) {
            $limiter = $this->loginIpLimiter->create($ip);
        } elseif (in_array($method, self::WRITE_METHODS, true)) {
            $limiter = $this->writeApiLimiter->create($ip);
        } else {
            $limiter = $this->anonymousApiLimiter->create($ip);
        }

        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }

        $request->attributes->set('rate_limit_remaining', $limit->getRemainingTokens());
    }
}
