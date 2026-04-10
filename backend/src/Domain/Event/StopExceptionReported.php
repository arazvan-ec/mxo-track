<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\MapView\Projection\MapProjectableEventInterface;
use App\Enum\ExceptionCode;

final readonly class StopExceptionReported implements MapProjectableEventInterface
{
    public function __construct(
        public string $stopPublicId = '',
        public string $shipmentPublicId = '',
        public string $routePublicId = '',
        public int $driverUserId = 0,
        public ?ExceptionCode $reason = null,
        public ?string $notes = null,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
        public ?string $exceptionCode = null,
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
