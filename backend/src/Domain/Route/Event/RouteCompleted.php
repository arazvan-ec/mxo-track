<?php

declare(strict_types=1);

namespace App\Domain\Route\Event;

final readonly class RouteCompleted
{
    public function __construct(
        public string $routePublicId,
        public int $driverUserId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
