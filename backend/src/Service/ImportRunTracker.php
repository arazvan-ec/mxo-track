<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CsvImportRun;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;

final class ImportRunTracker
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function track(Customer $customer, int $created, int $skipped): void
    {
        $this->entityManager->persist(new CsvImportRun($customer, $created, $skipped));
    }
}
