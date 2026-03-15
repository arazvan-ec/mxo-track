<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class DeviationEnded
{
    public function __construct(
        public string $routePublicId,
        public string $vehiclePublicId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
