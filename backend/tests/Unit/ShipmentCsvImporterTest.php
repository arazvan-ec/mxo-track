<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Service\ImportRunTracker;
use App\Service\ShipmentCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShipmentCsvImporter::class)]
final class ShipmentCsvImporterTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private ImportRunTracker&MockObject $importRunTracker;
    private ShipmentCsvImporter $importer;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->importRunTracker = $this->createMock(ImportRunTracker::class);
        $this->importer = new ShipmentCsvImporter($this->entityManager, $this->importRunTracker);
        $this->tmpDir = sys_get_temp_dir() . '/csv_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob($this->tmpDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    #[Test]
    public function importValidCsvCreatesShipments(): void
    {
        $csvPath = $this->writeCsv([
            ['reference', 'recipient_name', 'address', 'latitude', 'longitude', 'phone', 'notes'],
            ['REF-001', 'John Doe', 'Calle Mayor 1', '40.4168', '-3.7038', '+34600111222', 'Urgent'],
            ['REF-002', 'Jane Smith', 'Gran Via 50', '40.4200', '-3.7100', '+34600333444', ''],
        ]);

        $customer = new Customer('Test Customer');

        $shipmentRepo = $this->createMock(EntityRepository::class);
        $shipmentRepo->method('findOneBy')
            ->willReturn(null); // no duplicates

        $this->entityManager->method('getRepository')
            ->with(Shipment::class)
            ->willReturn($shipmentRepo);

        $persistedEntities = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedEntities): void {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->expects(self::once())->method('flush');

        $this->importRunTracker->expects(self::once())
            ->method('track')
            ->with($customer, 2, 0);

        $result = $this->importer->import($csvPath, $customer);

        self::assertSame(2, $result['created']);
        self::assertSame(0, $result['skipped']);
        self::assertSame(0, $result['errors']);

        // 2 Shipments + 2 ShipmentEvents = 4 persisted entities
        $shipments = array_filter($persistedEntities, static fn (object $e): bool => $e instanceof Shipment);
        $events = array_filter($persistedEntities, static fn (object $e): bool => $e instanceof ShipmentEvent);

        self::assertCount(2, $shipments);
        self::assertCount(2, $events);

        // Verify first shipment data
        $firstShipment = array_values($shipments)[0];
        self::assertSame('REF-001', $firstShipment->getReference());
        self::assertSame('John Doe', $firstShipment->getRecipientName());
        self::assertSame('Calle Mayor 1', $firstShipment->getAddress());
        self::assertSame(40.4168, $firstShipment->getLatitude());
        self::assertSame(-3.7038, $firstShipment->getLongitude());
        self::assertSame('+34600111222', $firstShipment->getRecipientPhone());
        self::assertSame('Urgent', $firstShipment->getNotes());
    }

    #[Test]
    public function importSkipsDuplicateReferences(): void
    {
        $csvPath = $this->writeCsv([
            ['reference', 'recipient_name', 'address', 'latitude', 'longitude', 'phone', 'notes'],
            ['REF-EXISTING', 'John Doe', 'Calle Mayor 1', '40.4168', '-3.7038', '', ''],
            ['REF-NEW', 'Jane Smith', 'Gran Via 50', '40.4200', '-3.7100', '', ''],
        ]);

        $customer = new Customer('Test Customer');
        $existingShipment = new Shipment('REF-EXISTING', $customer);

        $shipmentRepo = $this->createMock(EntityRepository::class);
        $shipmentRepo->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($existingShipment): ?Shipment {
                if ($criteria['reference'] === 'REF-EXISTING') {
                    return $existingShipment;
                }
                return null;
            });

        $this->entityManager->method('getRepository')
            ->with(Shipment::class)
            ->willReturn($shipmentRepo);

        $this->entityManager->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $this->importRunTracker->expects(self::once())
            ->method('track')
            ->with($customer, 1, 1);

        $result = $this->importer->import($csvPath, $customer);

        self::assertSame(1, $result['created']);
        self::assertSame(1, $result['skipped']);
        self::assertSame(0, $result['errors']);
    }

    #[Test]
    public function importCountsErrorsForRowsWithEmptyReference(): void
    {
        $csvPath = $this->writeCsv([
            ['reference', 'recipient_name', 'address', 'latitude', 'longitude', 'phone', 'notes'],
            ['', 'No Reference', 'Calle Sin Ref', '', '', '', ''],
            ['  ', 'Whitespace Ref', 'Calle Espacio', '', '', '', ''],
            ['REF-VALID', 'Valid One', 'Calle Buena', '40.4168', '-3.7038', '', ''],
        ]);

        $customer = new Customer('Test Customer');

        $shipmentRepo = $this->createMock(EntityRepository::class);
        $shipmentRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(Shipment::class)
            ->willReturn($shipmentRepo);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $this->importRunTracker->expects(self::once())
            ->method('track')
            ->with($customer, 1, 0);

        $result = $this->importer->import($csvPath, $customer);

        self::assertSame(1, $result['created']);
        self::assertSame(0, $result['skipped']);
        self::assertSame(2, $result['errors']);
    }

    #[Test]
    public function importReturnsZeroesForNonExistentFile(): void
    {
        $customer = new Customer('Test Customer');

        $result = $this->importer->import('/non/existent/file.csv', $customer);

        self::assertSame(0, $result['created']);
        self::assertSame(0, $result['skipped']);
        self::assertSame(0, $result['errors']);
    }

    #[Test]
    public function importHandlesMinimalCsvWithOnlyRequiredColumns(): void
    {
        $csvPath = $this->writeCsv([
            ['reference', 'recipient_name', 'address', 'latitude', 'longitude', 'phone', 'notes'],
            ['REF-MINIMAL', '', '', '', '', '', ''],
        ]);

        $customer = new Customer('Test Customer');

        $shipmentRepo = $this->createMock(EntityRepository::class);
        $shipmentRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(Shipment::class)
            ->willReturn($shipmentRepo);

        $persistedEntities = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedEntities): void {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->method('flush');
        $this->importRunTracker->method('track');

        $result = $this->importer->import($csvPath, $customer);

        self::assertSame(1, $result['created']);

        $shipments = array_filter($persistedEntities, static fn (object $e): bool => $e instanceof Shipment);
        $firstShipment = array_values($shipments)[0];

        self::assertSame('REF-MINIMAL', $firstShipment->getReference());
        self::assertNull($firstShipment->getRecipientName());
        self::assertNull($firstShipment->getAddress());
        self::assertNull($firstShipment->getLatitude());
        self::assertNull($firstShipment->getLongitude());
    }

    #[Test]
    public function importIgnoresInvalidCoordinates(): void
    {
        $csvPath = $this->writeCsv([
            ['reference', 'recipient_name', 'address', 'latitude', 'longitude', 'phone', 'notes'],
            ['REF-BADCOORDS', 'Bad Coords', 'Address', '999.9', '-999.9', '', ''],
        ]);

        $customer = new Customer('Test Customer');

        $shipmentRepo = $this->createMock(EntityRepository::class);
        $shipmentRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(Shipment::class)
            ->willReturn($shipmentRepo);

        $persistedEntities = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedEntities): void {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->method('flush');
        $this->importRunTracker->method('track');

        $result = $this->importer->import($csvPath, $customer);

        self::assertSame(1, $result['created']);

        $shipments = array_filter($persistedEntities, static fn (object $e): bool => $e instanceof Shipment);
        $firstShipment = array_values($shipments)[0];

        // Out-of-range latitude (>90) should be ignored
        self::assertNull($firstShipment->getLatitude());
        // Out-of-range longitude (>180) should be ignored
        self::assertNull($firstShipment->getLongitude());
    }

    #[Test]
    public function importHandlesShortRowsByPaddingMissingColumns(): void
    {
        // CSV with fewer columns than expected
        $csvPath = $this->writeCsv([
            ['reference', 'recipient_name'],
            ['REF-SHORT', 'Short Row'],
        ]);

        $customer = new Customer('Test Customer');

        $shipmentRepo = $this->createMock(EntityRepository::class);
        $shipmentRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(Shipment::class)
            ->willReturn($shipmentRepo);

        $persistedEntities = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedEntities): void {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->method('flush');
        $this->importRunTracker->method('track');

        $result = $this->importer->import($csvPath, $customer);

        self::assertSame(1, $result['created']);

        $shipments = array_filter($persistedEntities, static fn (object $e): bool => $e instanceof Shipment);
        $firstShipment = array_values($shipments)[0];
        self::assertSame('REF-SHORT', $firstShipment->getReference());
        self::assertSame('Short Row', $firstShipment->getRecipientName());
    }

    /**
     * @param list<list<string>> $rows
     */
    private function writeCsv(array $rows): string
    {
        $path = $this->tmpDir . '/test_' . uniqid() . '.csv';
        $fh = fopen($path, 'wb');
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);

        return $path;
    }
}
