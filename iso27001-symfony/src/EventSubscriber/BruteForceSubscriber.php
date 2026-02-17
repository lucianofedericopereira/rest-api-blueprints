<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Infrastructure\Security\BruteForceGuard;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A.9: Brute-force protection for the JWT login endpoint.
 *
 * Hooks into LexikJWTAuthenticationBundle events:
 *   - JWT_AUTHENTICATED_FAILURE → increment failure counter; lockout on threshold
 *   - JWT_AUTHENTICATED_SUCCESS → clear failure counter on successful login
 *
 * Also intercepts incoming requests to the login endpoint and short-circuits
 * with HTTP 429 when the account is already locked, before credentials are
 * even checked (A.9: fail-fast).
 */
final class BruteForceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BruteForceGuard $guard,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Check lockout BEFORE credentials are verified
            KernelEvents::REQUEST => ['onRequest', 10],

            // LexikJWT events (registered by bundle name)
            Events::AUTHENTICATION_FAILURE => ['onAuthFailure', 0],
            Events::AUTHENTICATION_SUCCESS => ['onAuthSuccess', 0],
        ];
    }

    /** Short-circuit locked accounts before credential check. */
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/v1/auth/login')) {
            return;
        }

        $email = (string) ($request->toArray()['email'] ?? '');
        if ($email === '') {
            return;
        }

        if ($this->guard->isLocked($email)) {
            $event->setResponse(new JsonResponse(
                ['error' => ['code' => 'ACCOUNT_LOCKED', 'message' => 'Too many failed attempts. Account temporarily locked.']],
                Response::HTTP_TOO_MANY_REQUESTS,
            ));
        }
    }

    public function onAuthFailure(AuthenticationFailureEvent $event): void
    {
        $request = $event->getRequest();
        if ($request === null) {
            return;
        }
        try {
            $email = (string) ($request->toArray()['email'] ?? '');
        } catch (\Throwable) {
            $email = '';
        }
        if ($email !== '') {
            $this->guard->recordFailure($email);
        }
    }

    public function onAuthSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (method_exists($user, 'getEmail')) {
            $this->guard->clear((string) $user->getEmail());
        }
    }
}
