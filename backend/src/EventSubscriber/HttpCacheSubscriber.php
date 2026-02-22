<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class HttpCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $path = $request->getPathInfo();

        if ($response->headers->has('Cache-Control')) {
            return;
        }

        // Fleet map and real-time endpoints: no cache
        if (str_starts_with($path, '/api/mercure') || str_starts_with($path, '/fleet/map')) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            return;
        }

        // API vehicle list: short cache
        if (str_starts_with($path, '/api/vehicles') && $request->isMethod('GET')) {
            $response->headers->set('Cache-Control', 'private, max-age=30');
            return;
        }

        // Reports: medium cache
        if (str_contains($path, '/reports') && $request->isMethod('GET')) {
            $response->headers->set('Cache-Control', 'private, max-age=300');
            return;
        }

        // API search: short cache
        if (str_starts_with($path, '/api/search')) {
            $response->headers->set('Cache-Control', 'private, max-age=10');
            return;
        }
    }
}
