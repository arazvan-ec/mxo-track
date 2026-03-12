<?php

declare(strict_types=1);

namespace App\Notification\Gate;

use App\Entity\Customer;
use App\Enum\NotificationChannel;
use App\Repository\NotificationLogRepository;

final class CustomerNotificationQuota
{
    private const DEFAULT_MONTHLY_QUOTA = 1000;

    public function __construct(
        private readonly NotificationLogRepository $logRepo,
    ) {}

    public function canSend(Customer $customer, NotificationChannel $channel): bool
    {
        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $count = $this->logRepo->countSentByCustomerSince($customer, $channel, $monthStart);
        $quota = $customer->getNotificationQuota() ?? self::DEFAULT_MONTHLY_QUOTA;

        return $count < $quota;
    }

    public function remaining(Customer $customer, NotificationChannel $channel): int
    {
        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $count = $this->logRepo->countSentByCustomerSince($customer, $channel, $monthStart);
        $quota = $customer->getNotificationQuota() ?? self::DEFAULT_MONTHLY_QUOTA;

        return max(0, $quota - $count);
    }
}
