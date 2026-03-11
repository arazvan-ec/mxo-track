<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\RealtimeEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RealtimeEvent> */
class RealtimeEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RealtimeEvent::class);
    }

    /** @return list<RealtimeEvent> */
    public function findSince(Customer $customer, ?string $topic, \DateTimeImmutable $since): array
    {
        $qb = $this->createQueryBuilder('re')
            ->andWhere('re.customer = :customer')
            ->andWhere('re.createdAt > :since')
            ->setParameter('customer', $customer)
            ->setParameter('since', $since)
            ->orderBy('re.createdAt', 'ASC');

        if ($topic !== null) {
            $qb->andWhere('re.topic = :topic')->setParameter('topic', $topic);
        }

        return $qb->getQuery()->getResult();
    }

    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('re')
            ->delete()
            ->andWhere('re.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
