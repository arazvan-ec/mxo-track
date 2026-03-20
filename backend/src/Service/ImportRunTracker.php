<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CsvImportRun;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;

class ImportRunTracker
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function track(Customer $customer, int $created, int $skipped, ?int $qualityScore = null): CsvImportRun
    {
        $run = new CsvImportRun($customer, $created, $skipped);

        if ($qualityScore !== null) {
            $run->setQualityScore($qualityScore);
        }

        $this->entityManager->persist($run);

        return $run;
    }
}
