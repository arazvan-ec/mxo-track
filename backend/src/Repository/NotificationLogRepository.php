<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\NotificationLog;
use App\Entity\Shipment;
use App\Enum\NotificationChannel;
use App\Enum\NotificationLogStatus;
use App\Enum\NotificationTriggerType;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationLog> */
final class NotificationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationLog::class);
    }

    public function hasBeenSent(
        Shipment $shipment,
        NotificationTriggerType $triggerType,
        NotificationChannel $channel,
    ): bool {
        return (bool) $this->createQueryBuilder('n')
            ->select('1')
            ->where('n.shipment = :shipment')
            ->andWhere('n.triggerType = :triggerType')
            ->andWhere('n.channel = :channel')
            ->andWhere('n.status = :status')
            ->setParameter('shipment', $shipment)
            ->setParameter('triggerType', $triggerType)
            ->setParameter('channel', $channel)
            ->setParameter('status', NotificationLogStatus::Sent)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countSentSince(
        string $phone,
        NotificationChannel $channel,
        DateTimeImmutable $since,
    ): int {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipientPhone = :phone')
            ->andWhere('n.channel = :channel')
            ->andWhere('n.status = :status')
            ->andWhere('n.createdAt >= :since')
            ->setParameter('phone', $phone)
            ->setParameter('channel', $channel)
            ->setParameter('status', NotificationLogStatus::Sent)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function lastSentAt(
        string $phone,
        NotificationChannel $channel,
    ): ?DateTimeImmutable {
        $result = $this->createQueryBuilder('n')
            ->select('n.createdAt')
            ->where('n.recipientPhone = :phone')
            ->andWhere('n.channel = :channel')
            ->andWhere('n.status = :status')
            ->setParameter('phone', $phone)
            ->setParameter('channel', $channel)
            ->setParameter('status', NotificationLogStatus::Sent)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['createdAt'] ?? null;
    }

    public function countSentByCustomerSince(
        Customer $customer,
        NotificationChannel $channel,
        DateTimeImmutable $since,
    ): int {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.customer = :customer')
            ->andWhere('n.channel = :channel')
            ->andWhere('n.status = :status')
            ->andWhere('n.createdAt >= :since')
            ->setParameter('customer', $customer)
            ->setParameter('channel', $channel)
            ->setParameter('status', NotificationLogStatus::Sent)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
