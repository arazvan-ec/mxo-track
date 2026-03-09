<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class ShipmentsImported
{
    public function __construct(
        public int $importRunId,
        public int $customerId,
        public int $createdCount,
        public int $skippedCount,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
