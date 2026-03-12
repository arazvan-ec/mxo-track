<?php

declare(strict_types=1);

namespace App\Notification\Gate;

use Psr\Clock\ClockInterface;

final class QuietHoursGuard
{
    private const QUIET_START = 22;
    private const QUIET_END = 8;

    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function canSendNow(): bool
    {
        $hour = (int) $this->clock->now()->format('G');

        return $hour >= self::QUIET_END && $hour < self::QUIET_START;
    }
}
