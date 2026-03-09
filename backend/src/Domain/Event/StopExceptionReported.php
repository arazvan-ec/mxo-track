<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Enum\ExceptionCode;

final readonly class StopExceptionReported
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
}
