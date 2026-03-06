<?php

declare(strict_types=1);

namespace App\Dto;

use DateTimeImmutable;

final class WindowViolation
{
    public function __construct(
        public readonly int $stopSequence,
        public readonly string $stopAddress,
        public readonly ?DateTimeImmutable $windowStart,
        public readonly ?DateTimeImmutable $windowEnd,
        public readonly DateTimeImmutable $estimatedArrival,
        public readonly string $type,
        public readonly string $message,
    ) {}
}
