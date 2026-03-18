<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\MapView\Projection\MapProjectableEventInterface;
use App\Enum\ExceptionCode;

final readonly class StopExceptionReported implements MapProjectableEventInterface
{
    public function __construct(
        public string $stopPublicId,
        public string $shipmentPublicId,
        public string $routePublicId,
        public int $driverUserId,
        public ExceptionCode $reason,
        public ?string $notes,
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
