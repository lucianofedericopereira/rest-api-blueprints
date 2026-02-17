<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A.10: Injects security headers on every HTTP response.
 * Enforces HSTS, prevents clickjacking, restricts content sources.
 */
final class SecurityHeaderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $headers = $response->headers;

        // A.10: Force HTTPS for 1 year including subdomains
        $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Prevent embedding in iframes (clickjacking)
        $headers->set('X-Frame-Options', 'DENY');

        // Stop MIME type sniffing
        $headers->set('X-Content-Type-Options', 'nosniff');

        // Restrict referrer information leakage
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // A.14: Basic Content Security Policy — tighten per application needs
        $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

        // Restrict browser feature access (microphone, camera, geolocation)
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Prevent cross-origin window handle leakage
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Require explicit cross-origin resource embedding opt-in
        $headers->set('Cross-Origin-Embedder-Policy', 'require-corp');

        // Remove server fingerprinting headers
        $headers->remove('X-Powered-By');
        $headers->remove('Server');
    }
}
