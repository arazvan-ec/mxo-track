<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Route;

use App\Application\Route\BuildRoutesInput;
use App\Application\Route\RoutePlanningService;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteMapOptions;
use App\Domain\Route\Model\RouteMapView;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Route\Service\RouteMapProjection;
use App\Entity\Customer;
use App\Entity\Vehicle;
use App\Domain\Shipment\Model\Shipment;
use App\Provider\ProviderFactoryInterface;
use App\Provider\ProviderFactoryRegistry;
use App\Provider\ServiceType;
use App\RouteOptimization\OptimizableJob;
use App\RouteOptimization\OptimizableVehicle;
use App\RouteOptimization\OptimizationResult;
use App\RouteOptimization\OptimizedRoute;
use App\RouteOptimization\OptimizedStep;
use App\RouteOptimization\RouteOptimizerInterface;
use App\Service\OptimizationLogger;
use App\Service\RouteBuilder;
use App\Service\RouteCapacityValidator;
use App\Service\RouteSnapshotManager;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(BuildRoutesInput::class)]
final class RoutePlanningServiceOptimizerSelectionTest extends TestCase
{
    #[Test]
    public function build_routes_input_accepts_optimizer_name(): void
    {
        $input = new BuildRoutesInput(
            shipmentPublicIds: ['abc'],
            vehiclePublicIds: ['xyz'],
            optimizerName: 'greedy',
        );

        self::assertSame('greedy', $input->optimizerName);
    }

    #[Test]
    public function build_routes_input_defaults_optimizer_name_to_null(): void
    {
        $input = new BuildRoutesInput(
            shipmentPublicIds: ['abc'],
            vehiclePublicIds: ['xyz'],
        );

        self::assertNull($input->optimizerName);
    }

    #[Test]
    public function route_builder_uses_override_optimizer_when_provided(): void
    {
        $defaultOptimizer = $this->createMock(RouteOptimizerInterface::class);
        $overrideOptimizer = $this->createMock(RouteOptimizerInterface::class);

        // Default should NOT be called
        $defaultOptimizer->expects(self::never())->method('optimize');

        // Override SHOULD be called
        $overrideOptimizer->expects(self::once())
            ->method('optimize')
            ->willReturn(new OptimizationResult(routes: [], unassignedJobIds: []));

        $routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $logger = $this->createMock(OptimizationLogger::class);
        $snapshotManager = $this->createMock(RouteSnapshotManager::class);

        $builder = new RouteBuilder(
            $routeRepo,
            $stopRepo,
            $defaultOptimizer,
            $capacityValidator,
            $logger,
            $snapshotManager,
        );

        $customer = new Customer('Test');
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getLatitude')->willReturn(40.0);
        $shipment->method('getLongitude')->willReturn(-3.0);
        $shipment->method('getServiceTimeSeconds')->willReturn(300);
        $shipment->method('getTotalWeightKg')->willReturn(1.0);
        $shipment->method('getTotalVolumeM3')->willReturn(0.01);
        $shipment->method('getTotalParcels')->willReturn(1);
        $shipment->method('getPriority')->willReturn(\App\Enum\ShipmentPriority::NORMAL);
        $shipment->method('getRequiredSkills')->willReturn([]);
        $shipment->method('getPreferredWindowStart')->willReturn(null);
        $shipment->method('getPreferredWindowEnd')->willReturn(null);

        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(100.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(10.0);
        $vehicle->method('getMaxParcels')->willReturn(50);
        $vehicle->method('getSkills')->willReturn([]);

        $result = $builder->buildRoutes([$shipment], [$vehicle], $customer, null, 30, $overrideOptimizer);

        self::assertSame([], $result);
    }

    #[Test]
    public function route_builder_uses_default_optimizer_when_no_override(): void
    {
        $defaultOptimizer = $this->createMock(RouteOptimizerInterface::class);

        // Default SHOULD be called
        $defaultOptimizer->expects(self::once())
            ->method('optimize')
            ->willReturn(new OptimizationResult(routes: [], unassignedJobIds: []));

        $routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $logger = $this->createMock(OptimizationLogger::class);
        $snapshotManager = $this->createMock(RouteSnapshotManager::class);

        $builder = new RouteBuilder(
            $routeRepo,
            $stopRepo,
            $defaultOptimizer,
            $capacityValidator,
            $logger,
            $snapshotManager,
        );

        $customer = new Customer('Test');
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getLatitude')->willReturn(40.0);
        $shipment->method('getLongitude')->willReturn(-3.0);
        $shipment->method('getServiceTimeSeconds')->willReturn(300);
        $shipment->method('getTotalWeightKg')->willReturn(1.0);
        $shipment->method('getTotalVolumeM3')->willReturn(0.01);
        $shipment->method('getTotalParcels')->willReturn(1);
        $shipment->method('getPriority')->willReturn(\App\Enum\ShipmentPriority::NORMAL);
        $shipment->method('getRequiredSkills')->willReturn([]);
        $shipment->method('getPreferredWindowStart')->willReturn(null);
        $shipment->method('getPreferredWindowEnd')->willReturn(null);

        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(100.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(10.0);
        $vehicle->method('getMaxParcels')->willReturn(50);
        $vehicle->method('getSkills')->willReturn([]);

        $result = $builder->buildRoutes([$shipment], [$vehicle], $customer);

        self::assertSame([], $result);
    }
}
