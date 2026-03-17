<?php

declare(strict_types=1);

namespace App\Domain\Route\Event;

final readonly class RouteOptimized
{
    public function __construct(
        public string $routePublicId,
        public float $improvementPercent,
        public ?float $distanceKm,
        public ?int $durationMinutes,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
