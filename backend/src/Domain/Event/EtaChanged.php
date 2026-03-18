<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\MapView\Projection\MapProjectableEventInterface;

final readonly class EtaChanged implements MapProjectableEventInterface
{
    /**
     * @param array<string, int> $previousEtas stop_public_id => minutes
     * @param array<string, int> $currentEtas  stop_public_id => minutes
     */
    public function __construct(
        public string $routePublicId,
        public array $previousEtas,
        public array $currentEtas,
        public int $maxDeltaMinutes,
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
