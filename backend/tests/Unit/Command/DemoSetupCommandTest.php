<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\DemoSetupCommand;
use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Domain\Route\Model\Route;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Service\DemoScenarioBuilder;
use App\Service\DemoScenarioResult;
use App\Application\Route\RoutePlanningService;
use App\Service\ShipmentCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoSetupCommandTest extends TestCase
{
    private DemoScenarioBuilder&MockObject $builder;
    private EntityManagerInterface&MockObject $em;
    private ShipmentCsvImporter&MockObject $csvImporter;
    private RoutePlanningService&MockObject $routePlanning;

    protected function setUp(): void
    {
        $this->builder = $this->createMock(DemoScenarioBuilder::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->csvImporter = $this->createMock(ShipmentCsvImporter::class);
        $this->routePlanning = $this->createMock(RoutePlanningService::class);
    }

    public function testCommandPersistsAllEntities(): void
    {
        $customer = new Customer('Logística Express Madrid');
        $warehouse = new CustomerLocation($customer, 'Almacén', 'Madrid');
        $warehouse->setLatitude(40.3460);
        $warehouse->setLongitude(-3.6970);
        $vehicles = [new Vehicle('V1'), new Vehicle('V2'), new Vehicle('V3')];
        $drivers = [new User('d1@test.local'), new User('d2@test.local')];
        $customerUser = new User('c@test.local');
        $shipments = [new Shipment('SHP-001', $customer), new Shipment('SHP-002', $customer)];

        $result = new DemoScenarioResult($customer, $warehouse, $vehicles, $drivers, $shipments, $customerUser);

        $this->builder->expects(self::once())
            ->method('buildScenario')
            ->with(20)
            ->willReturn($result);

        // customer + warehouse + customerUser + 3 vehicles + 2 drivers + 2 shipments = 10
        $this->em->expects(self::exactly(10))
            ->method('persist');
        $this->em->expects(self::once())
            ->method('flush');

        $command = new DemoSetupCommand($this->builder, $this->em, $this->csvImporter, $this->routePlanning);

        $app = new Application();
        $app->add($command);

        $tester = new CommandTester($command);
        $tester->execute(['--shipments' => '20', '--skip-routes' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Logística Express Madrid', $tester->getDisplay());
    }

    public function testCommandDefaultShipmentCount(): void
    {
        $customer = new Customer('Logística Express Madrid');
        $warehouse = new CustomerLocation($customer, 'Almacén', 'Madrid');
        $warehouse->setLatitude(40.3460);
        $warehouse->setLongitude(-3.6970);

        $result = new DemoScenarioResult($customer, $warehouse, [], [], [], new User('c@test.local'));

        $this->builder->expects(self::once())
            ->method('buildScenario')
            ->with(40)
            ->willReturn($result);

        $this->em->method('persist');
        $this->em->method('flush');

        $command = new DemoSetupCommand($this->builder, $this->em, $this->csvImporter, $this->routePlanning);
        $tester = new CommandTester($command);
        $tester->execute(['--skip-routes' => true]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testOutputShowsSummary(): void
    {
        $customer = new Customer('Logística Express Madrid');
        $warehouse = new CustomerLocation($customer, 'Almacén', 'Madrid');
        $warehouse->setLatitude(40.3460);
        $warehouse->setLongitude(-3.6970);
        $vehicles = [new Vehicle('Furgoneta'), new Vehicle('Camión'), new Vehicle('Moto')];
        $drivers = [new User('d1@test.local'), new User('d2@test.local')];
        $shipments = array_map(
            fn (int $i) => new Shipment(sprintf('SHP-%03d', $i), $customer),
            range(1, 10),
        );

        $result = new DemoScenarioResult($customer, $warehouse, $vehicles, $drivers, $shipments, new User('c@test.local'));

        $this->builder->method('buildScenario')->willReturn($result);
        $this->em->method('persist');
        $this->em->method('flush');

        $command = new DemoSetupCommand($this->builder, $this->em, $this->csvImporter, $this->routePlanning);
        $tester = new CommandTester($command);
        $tester->execute(['--shipments' => '10', '--skip-routes' => true]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('3', $display); // 3 vehicles
        self::assertStringContainsString('2', $display); // 2 drivers
        self::assertStringContainsString('10', $display); // 10 shipments
    }

    public function testImportCsvCallsImporter(): void
    {
        $customer = new Customer('Logística Express Madrid');
        $warehouse = new CustomerLocation($customer, 'Almacén', 'Madrid');
        $warehouse->setLatitude(40.3460);
        $warehouse->setLongitude(-3.6970);

        $result = new DemoScenarioResult($customer, $warehouse, [], [], [], new User('c@test.local'));

        $this->builder->method('buildScenario')->willReturn($result);
        $this->em->method('persist');
        $this->em->method('flush');

        $this->csvImporter->expects(self::once())
            ->method('import')
            ->willReturnCallback(function (string $path, Customer $c) use ($customer): array {
                self::assertStringContainsString('envios-madrid.csv', $path);
                self::assertSame($customer, $c);

                return ['created' => 55, 'skipped' => 0, 'errors' => 0, 'quality_report' => null];
            });

        $command = new DemoSetupCommand($this->builder, $this->em, $this->csvImporter, $this->routePlanning);
        $tester = new CommandTester($command);
        $tester->execute(['--import-csv' => true, '--skip-routes' => true]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('55', $display);
    }
}
