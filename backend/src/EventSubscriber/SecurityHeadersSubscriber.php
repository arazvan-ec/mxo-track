<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    private string $mercureOrigin;

    public function __construct(
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')]
        string $mercurePublicUrl,
    ) {
        $parsed = parse_url($mercurePublicUrl);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? 'localhost';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $this->mercureOrigin = $scheme . '://' . $host . $port;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.tailwindcss.com",
            "font-src 'self' data:",
            "img-src 'self' https://*.tile.openstreetmap.org https://*.basemaps.cartocdn.com https://unpkg.com data:",
            "connect-src 'self' https://unpkg.com https://nominatim.openstreetmap.org " . $this->mercureOrigin,
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
        ]));
    }
}
