<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\MapView\Projection\MapProjectableEventInterface;

final readonly class StopSkipped implements MapProjectableEventInterface
{
    public function __construct(
        public string $stopPublicId,
        public string $routePublicId,
        public int $driverUserId,
        public ?string $reason = null,
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
