<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\CsvQualityReport;
use App\Entity\Customer;
use App\Entity\Shipment;
use App\Service\CsvQualityAnalyzer;
use App\Service\ImportRunTracker;
use App\Service\ShipmentCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CsvDemoImportTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ShipmentCsvImporter $importer;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $importRunTracker = $this->createMock(ImportRunTracker::class);
        $qualityAnalyzer = $this->createMock(CsvQualityAnalyzer::class);
        $qualityAnalyzer->method('analyze')
            ->willReturn(new CsvQualityReport(100, []));

        // Repository returns null for all findOneBy (no duplicates)
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')
            ->with(Shipment::class)
            ->willReturn($repo);

        $this->importer = new ShipmentCsvImporter($this->em, $importRunTracker, $qualityAnalyzer);
    }

    public function testDemoCsvImportsAllRows(): void
    {
        $csvPath = $this->getDemoCsvPath();
        self::assertFileExists($csvPath, 'Demo CSV file must exist at docs/demo/envios-madrid.csv');

        $customer = new Customer('Test Customer');
        $result = $this->importer->import($csvPath, $customer);

        self::assertSame(55, $result['created'], 'Should import all 55 demo shipments');
        self::assertSame(0, $result['skipped'], 'No shipments should be skipped');
        self::assertSame(0, $result['errors'], 'No rows should have errors');
    }

    public function testDemoCsvHasExpectedColumns(): void
    {
        $csvPath = $this->getDemoCsvPath();
        $fh = fopen($csvPath, 'rb');
        self::assertNotFalse($fh);

        $header = fgetcsv($fh, 0, ',', '"', '');
        fclose($fh);

        $expectedColumns = [
            'reference', 'recipient_name', 'address', 'latitude', 'longitude',
            'phone', 'notes', 'service_type', 'weight_kg', 'volume_m3',
            'num_parcels', 'ean', 'description', 'service_time_seconds', 'priority',
        ];

        self::assertSame($expectedColumns, $header);
    }

    public function testDemoCsvHasMixedPriorities(): void
    {
        $csvPath = $this->getDemoCsvPath();
        $fh = fopen($csvPath, 'rb');
        self::assertNotFalse($fh);

        $priorities = [];
        $lineNum = 0;
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $lineNum++;
            if ($lineNum === 1) {
                continue;
            }
            $priorities[] = strtolower(trim((string) ($row[14] ?? '')));
        }
        fclose($fh);

        $unique = array_unique($priorities);
        self::assertContains('normal', $unique);
        self::assertContains('high', $unique);
        self::assertContains('critical', $unique);
        self::assertContains('low', $unique);
        self::assertContains('urgent', $unique);
    }

    public function testDemoCsvHasMixedServiceTypes(): void
    {
        $csvPath = $this->getDemoCsvPath();
        $fh = fopen($csvPath, 'rb');
        self::assertNotFalse($fh);

        $types = [];
        $lineNum = 0;
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $lineNum++;
            if ($lineNum === 1) {
                continue;
            }
            $types[] = strtoupper(trim((string) ($row[7] ?? '')));
        }
        fclose($fh);

        $unique = array_unique($types);
        self::assertContains('DELIVERY', $unique);
        self::assertContains('DELIVERY_AND_PICKUP', $unique);
        self::assertContains('RETURN', $unique);
    }

    public function testDemoCsvCoordinatesAreInMadrid(): void
    {
        $csvPath = $this->getDemoCsvPath();
        $fh = fopen($csvPath, 'rb');
        self::assertNotFalse($fh);

        $lineNum = 0;
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $lineNum++;
            if ($lineNum === 1) {
                continue;
            }

            $lat = (float) $row[3];
            $lng = (float) $row[4];

            self::assertGreaterThan(40.0, $lat, "Row $lineNum latitude should be in Madrid area");
            self::assertLessThan(41.0, $lat, "Row $lineNum latitude should be in Madrid area");
            self::assertGreaterThan(-4.0, $lng, "Row $lineNum longitude should be in Madrid area");
            self::assertLessThan(-3.0, $lng, "Row $lineNum longitude should be in Madrid area");
        }
        fclose($fh);
    }

    public function testDemoCsvAllReferencesAreUnique(): void
    {
        $csvPath = $this->getDemoCsvPath();
        $fh = fopen($csvPath, 'rb');
        self::assertNotFalse($fh);

        $references = [];
        $lineNum = 0;
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $lineNum++;
            if ($lineNum === 1) {
                continue;
            }
            $references[] = $row[0];
        }
        fclose($fh);

        self::assertCount(count(array_unique($references)), $references, 'All references must be unique');
    }

    private function getDemoCsvPath(): string
    {
        return \dirname(__DIR__, 4) . '/docs/demo/envios-madrid.csv';
    }
}
