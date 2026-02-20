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
    private const EXPECTED_COLUMNS = [
        'reference',
        'recipient_name',
        'address',
        'latitude',
        'longitude',
        'phone',
        'notes',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportRunTracker $importRunTracker,
    ) {
    }

    /** @return array{created: int, skipped: int, errors: int} */
    public function import(string $csvPath, Customer $customer): array
    {
        $created = 0;
        $skipped = 0;
        $errors = 0;

        if (!is_file($csvPath)) {
            return ['created' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $fh = fopen($csvPath, 'rb');
        if ($fh === false) {
            return ['created' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $lineNumber = 0;

        while (($row = fgetcsv($fh, 0, ',')) !== false) {
            $lineNumber++;

            // Skip header row
            if ($lineNumber === 1) {
                continue;
            }

            // Pad row to expected column count so optional columns default to empty
            $row = array_pad($row, count(self::EXPECTED_COLUMNS), '');

            $reference = trim((string) ($row[0] ?? ''));
            if ($reference === '') {
                $errors++;
                continue;
            }

            $exists = $this->entityManager
                ->getRepository(Shipment::class)
                ->findOneBy(['reference' => $reference]);

            if ($exists instanceof Shipment) {
                $skipped++;
                continue;
            }

            $shipment = new Shipment($reference, $customer);

            // recipient_name (column 1)
            $recipientName = trim((string) ($row[1] ?? ''));
            if ($recipientName !== '') {
                $shipment->setRecipientName($recipientName);
            }

            // address (column 2)
            $address = trim((string) ($row[2] ?? ''));
            if ($address !== '') {
                $shipment->setAddress($address);
            }

            // latitude (column 3)
            $latRaw = trim((string) ($row[3] ?? ''));
            if ($latRaw !== '') {
                $lat = filter_var($latRaw, FILTER_VALIDATE_FLOAT);
                if ($lat !== false && $lat >= -90.0 && $lat <= 90.0) {
                    $shipment->setLatitude($lat);
                }
            }

            // longitude (column 4)
            $lonRaw = trim((string) ($row[4] ?? ''));
            if ($lonRaw !== '') {
                $lon = filter_var($lonRaw, FILTER_VALIDATE_FLOAT);
                if ($lon !== false && $lon >= -180.0 && $lon <= 180.0) {
                    $shipment->setLongitude($lon);
                }
            }

            // phone (column 5)
            $phone = trim((string) ($row[5] ?? ''));
            if ($phone !== '') {
                $shipment->setRecipientPhone($phone);
            }

            // notes (column 6)
            $notes = trim((string) ($row[6] ?? ''));
            if ($notes !== '') {
                $shipment->setNotes($notes);
            }

            $this->entityManager->persist($shipment);
            $this->entityManager->persist(
                new ShipmentEvent($shipment, ShipmentEventType::CREATED, ['source' => 'csv_import']),
            );
            $created++;
        }

        fclose($fh);
        $this->importRunTracker->track($customer, $created, $skipped);
        $this->entityManager->flush();

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }
}
