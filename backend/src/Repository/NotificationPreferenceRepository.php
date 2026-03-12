<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NotificationPreference;
use App\Enum\NotificationTriggerType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationPreference> */
final class NotificationPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationPreference::class);
    }

    /** @return NotificationPreference[] */
    public function findEnabledByCustomerAndTrigger(
        int $customerId,
        NotificationTriggerType $triggerType,
    ): array {
        return $this->createQueryBuilder('p')
            ->where('p.customer = :customerId')
            ->andWhere('p.triggerType = :triggerType')
            ->andWhere('p.enabled = true')
            ->setParameter('customerId', $customerId)
            ->setParameter('triggerType', $triggerType)
            ->getQuery()
            ->getResult();
    }
}
