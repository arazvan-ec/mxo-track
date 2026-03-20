<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DriverFeedback;
use App\Domain\Route\Model\RouteStop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DriverFeedback> */
final class DriverFeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DriverFeedback::class);
    }

    /** @return DriverFeedback[] */
    public function findByStop(RouteStop $stop): array
    {
        return $this->findBy(['stop' => $stop], ['createdAt' => 'DESC']);
    }

    /** @return DriverFeedback[] */
    public function findByAddress(string $address): array
    {
        return $this->createQueryBuilder('df')
            ->join('df.stop', 's')
            ->where('s.address = :address')
            ->setParameter('address', $address)
            ->orderBy('df.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
