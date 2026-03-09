<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class StopDelivered
{
    public function __construct(
        public string $stopPublicId,
        public string $shipmentPublicId,
        public string $routePublicId,
        public int $driverUserId,
        public string $podPublicId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
