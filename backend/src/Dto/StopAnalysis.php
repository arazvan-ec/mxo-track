<?php

declare(strict_types=1);

namespace App\Dto;

final class StopAnalysis
{
    public function __construct(
        public readonly int $plannedSequence,
        public readonly ?int $actualOrder,
        public readonly string $address,
        public readonly string $status,
        public readonly ?string $deliveredAt,
        public readonly ?float $actualServiceTimeSeconds,
        public readonly ?int $sequenceDeviation,
        public readonly ?string $exceptionCode,
        public readonly ?string $exceptionNotes,
    ) {}
}
