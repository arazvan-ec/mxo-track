<?php

declare(strict_types=1);

namespace App\Domain\Route\Event;

final readonly class EtaChanged
{
    /**
     * @param array<string, int> $previousEtas stop_public_id => minutes
     * @param array<string, int> $currentEtas  stop_public_id => minutes
     */
    public function __construct(
        public string $routePublicId,
        public array $previousEtas,
        public array $currentEtas,
        public int $maxDeltaMinutes,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
