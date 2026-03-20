<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\MapView\Projection\MapProjectableEventInterface;

final readonly class StopsReordered implements MapProjectableEventInterface
{
    /**
     * @param array<int, string> $order Map of sequence index → stop publicId
     */
    public function __construct(
        public string $routePublicId,
        public array $order,
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
