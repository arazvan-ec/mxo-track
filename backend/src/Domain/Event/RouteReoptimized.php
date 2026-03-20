<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\MapView\Projection\MapProjectableEventInterface;

final readonly class RouteReoptimized implements MapProjectableEventInterface
{
    public function __construct(
        public string $routePublicId,
        public float $improvementPercent,
        public ?float $distanceKm,
        public ?int $durationMinutes,
        public int $pendingStopsCount,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}

    public function getRoutePublicId(): string
    {
        return $this->routePublicId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
