<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class RouteCancelled
{
    public function __construct(
        public string $routePublicId,
        public int $cancelledByUserId,
        public ?string $reason = null,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
