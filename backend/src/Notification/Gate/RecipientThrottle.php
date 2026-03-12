<?php

declare(strict_types=1);

namespace App\Notification\Gate;

use App\Enum\NotificationChannel;
use App\Repository\NotificationLogRepository;

final class RecipientThrottle
{
    private const MAX_PER_DAY = 6;
    private const MIN_INTERVAL_MINUTES = 10;

    public function __construct(
        private readonly NotificationLogRepository $logRepo,
    ) {}

    public function canSend(string $phone, NotificationChannel $channel): bool
    {
        $today = new \DateTimeImmutable('today');
        $countToday = $this->logRepo->countSentSince($phone, $channel, $today);

        if ($countToday >= self::MAX_PER_DAY) {
            return false;
        }

        $lastSent = $this->logRepo->lastSentAt($phone, $channel);
        if ($lastSent !== null && $lastSent > new \DateTimeImmutable(
            sprintf('-%d minutes', self::MIN_INTERVAL_MINUTES),
        )) {
            return false;
        }

        return true;
    }
}
