<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class RoutesBuilt
{
    /**
     * @param string[] $routePublicIds
     */
    public function __construct(
        public array $routePublicIds,
        public int $shipmentCount,
        public int $vehicleCount,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
