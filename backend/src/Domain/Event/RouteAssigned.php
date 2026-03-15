<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class RouteAssigned
{
    public function __construct(
        public string $routePublicId,
        public ?string $vehiclePublicId,
        public ?int $driverUserId,
        public int $assignedByUserId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
