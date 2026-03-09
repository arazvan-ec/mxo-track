<?php

declare(strict_types=1);

namespace App\Application\Delivery;

/**
 * HTTP-originated context for audit evidence (IP, User-Agent).
 * Optional — allows the service to remain HTTP-agnostic.
 */
final readonly class DeliveryContext
{
    public function __construct(
        public string $clientIp = '',
        public string $userAgent = '',
    ) {}
}
