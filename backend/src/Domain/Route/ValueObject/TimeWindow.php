<?php

declare(strict_types=1);

namespace App\Domain\Route\ValueObject;

final readonly class TimeWindow
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
        if ($start >= $end) {
            throw new \InvalidArgumentException('TimeWindow start must be before end.');
        }
    }
}
