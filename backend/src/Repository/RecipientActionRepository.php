<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RecipientAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RecipientAction> */
final class RecipientActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecipientAction::class);
    }
}
