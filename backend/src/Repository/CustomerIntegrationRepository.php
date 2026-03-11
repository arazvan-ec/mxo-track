<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\CustomerIntegration;
use App\Provider\ServiceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerIntegration>
 */
class CustomerIntegrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerIntegration::class);
    }

    /**
     * @return list<CustomerIntegration>
     */
    public function findActiveByCustomerAndService(Customer $customer, ServiceType $service): array
    {
        return $this->createQueryBuilder('ci')
            ->andWhere('ci.customer = :customer')
            ->andWhere('ci.serviceType = :serviceType')
            ->andWhere('ci.enabled = true')
            ->setParameter('customer', $customer)
            ->setParameter('serviceType', $service)
            ->orderBy('ci.priority', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
