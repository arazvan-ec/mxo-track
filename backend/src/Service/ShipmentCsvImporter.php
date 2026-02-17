<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Enum\ShipmentEventType;
use Doctrine\ORM\EntityManagerInterface;

final class ShipmentCsvImporter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportRunTracker $importRunTracker,
    ) {
    }

    /** @return array{created:int,skipped:int} */
    public function import(string $csvPath, Customer $customer): array
    {
        $created = 0;
        $skipped = 0;

        if (!is_file($csvPath)) {
            return ['created' => 0, 'skipped' => 0];
        }

        $fh = fopen($csvPath, 'rb');
        if ($fh === false) {
            return ['created' => 0, 'skipped' => 0];
        }

        while (($row = fgetcsv($fh, 0, ',')) !== false) {
            $reference = trim((string) ($row[0] ?? ''));
            if ($reference === '') {
                $skipped++;
                continue;
            }

            $exists = $this->entityManager->getRepository(Shipment::class)->findOneBy(['reference' => $reference]);
            if ($exists instanceof Shipment) {
                $skipped++;
                continue;
            }

            $shipment = new Shipment($reference, $customer);
            $this->entityManager->persist($shipment);
            $this->entityManager->persist(new ShipmentEvent($shipment, ShipmentEventType::CREATED, ['source' => 'csv_import']));
            $created++;
        }

        fclose($fh);
        $this->importRunTracker->track($customer, $created, $skipped);
        $this->entityManager->flush();

        return ['created' => $created, 'skipped' => $skipped];
    }
}
