<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * A.12: Assigns correlation ID to every request.
 * Available via RequestStack throughout the entire request lifecycle.
 */
final class CorrelationIdSubscriber implements EventSubscriberInterface
{
    public const HEADER = 'X-Request-ID';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onRequest', 255],   // First
            KernelEvents::RESPONSE => ['onResponse', -255],  // Last
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $requestId = $request->headers->get(self::HEADER, Uuid::v4()->toRfc4122());
        $request->attributes->set('request_id', $requestId);
    }

    public function onResponse(ResponseEvent $event): void
    {
        $requestId = $event->getRequest()->attributes->get('request_id');
        $event->getResponse()->headers->set(self::HEADER, $requestId);
    }
}
