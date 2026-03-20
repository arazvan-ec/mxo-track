<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CsvQualityReport;
use App\Entity\Customer;
use App\Domain\Shipment\Model\Parcel;
use App\Domain\Shipment\Model\Shipment;
use App\Domain\Shipment\Model\ShipmentEvent;
use App\Enum\ServiceType;
use App\Enum\ShipmentEventType;
use App\Enum\ShipmentPriority;
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
        'service_type',
        'weight_kg',
        'volume_m3',
        'num_parcels',
        'ean',
        'description',
        'service_time_seconds',
        'priority',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportRunTracker $importRunTracker,
        private readonly CsvQualityAnalyzer $qualityAnalyzer,
    ) {
    }

    /** @return array{created: int, skipped: int, errors: int, quality_report: CsvQualityReport|null} */
    public function import(string $csvPath, Customer $customer): array
    {
        $created = 0;
        $skipped = 0;
        $errors = 0;
        $qualityReport = null;

        if (!is_file($csvPath)) {
            return ['created' => 0, 'skipped' => 0, 'errors' => 0, 'quality_report' => null];
        }

        $fh = fopen($csvPath, 'rb');
        if ($fh === false) {
            return ['created' => 0, 'skipped' => 0, 'errors' => 0, 'quality_report' => null];
        }

        // Read all data rows (skip header) for quality analysis
        $dataRows = [];
        $lineNumber = 0;

        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $lineNumber++;

            if ($lineNumber === 1) {
                continue; // Skip header
            }

            $dataRows[] = $row;
        }

        fclose($fh);

        // Run quality analysis before import
        $qualityReport = $this->qualityAnalyzer->analyze($dataRows);

        // Process each data row
        foreach ($dataRows as $row) {
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

            // service_type (column 7)
            $serviceTypeRaw = strtoupper(trim((string) ($row[7] ?? '')));
            $serviceType = ServiceType::tryFrom($serviceTypeRaw);
            if ($serviceType !== null) {
                $shipment->setServiceType($serviceType);
            }

            // weight_kg (column 8)
            $weightRaw = trim((string) ($row[8] ?? ''));
            $weight = $weightRaw !== '' ? filter_var($weightRaw, FILTER_VALIDATE_FLOAT) : false;

            // volume_m3 (column 9)
            $volumeRaw = trim((string) ($row[9] ?? ''));
            $volume = $volumeRaw !== '' ? filter_var($volumeRaw, FILTER_VALIDATE_FLOAT) : false;

            // num_parcels (column 10)
            $numParcelsRaw = trim((string) ($row[10] ?? ''));
            $numParcels = $numParcelsRaw !== '' ? filter_var($numParcelsRaw, FILTER_VALIDATE_INT) : false;
            $numParcels = ($numParcels !== false && $numParcels > 0) ? (int) $numParcels : 1;

            // ean (column 11)
            $ean = trim((string) ($row[11] ?? ''));

            // description (column 12)
            $parcelDescription = trim((string) ($row[12] ?? ''));

            // service_time_seconds (column 13, optional)
            $serviceTimeRaw = trim((string) ($row[13] ?? ''));
            if ($serviceTimeRaw !== '') {
                $serviceTime = filter_var($serviceTimeRaw, FILTER_VALIDATE_INT);
                if ($serviceTime !== false && $serviceTime > 0) {
                    $shipment->setServiceTimeSeconds($serviceTime);
                }
            }

            if ($weight !== false && $weight > 0) {
                $shipment->setTotalWeightKg((float) $weight);
            }
            if ($volume !== false && $volume > 0) {
                $shipment->setTotalVolumeM3((float) $volume);
            }
            $shipment->setTotalParcels($numParcels);

            // Create parcel entities
            $parcelWeight = ($weight !== false && $weight > 0) ? (float) $weight / $numParcels : 0.1;
            $parcelVolume = ($volume !== false && $volume > 0) ? (float) $volume / $numParcels : 0.001;
            for ($p = 1; $p <= $numParcels; $p++) {
                $parcel = new Parcel($shipment, $p, $parcelWeight, $parcelVolume);
                if ($ean !== '') {
                    $parcel->setEan($ean);
                }
                if ($parcelDescription !== '') {
                    $parcel->setDescription($parcelDescription);
                }
                $this->entityManager->persist($parcel);
            }

            // priority (column 14, optional)
            $priorityRaw = strtolower(trim((string) ($row[14] ?? '')));
            if ($priorityRaw !== '') {
                $priority = self::parsePriority($priorityRaw);
                if ($priority !== null) {
                    $shipment->setPriority($priority);
                }
            }

            $this->entityManager->persist($shipment);
            $this->entityManager->persist(
                new ShipmentEvent($shipment, ShipmentEventType::CREATED, ['source' => 'csv_import']),
            );
            $created++;
        }

        $this->importRunTracker->track($customer, $created, $skipped, $qualityReport->score);
        $this->entityManager->flush();

        return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors, 'quality_report' => $qualityReport];
    }

    private static function parsePriority(string $name): ?ShipmentPriority
    {
        foreach (ShipmentPriority::cases() as $case) {
            if (strtolower($case->name) === $name) {
                return $case;
            }
        }

        return null;
    }
}
