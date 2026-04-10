<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\Customer;
use App\Entity\Vehicle;
use App\Enum\ShipmentPriority;
use App\RouteOptimization\OptimizableJob;
use App\RouteOptimization\OptimizationResult;
use App\RouteOptimization\OptimizedRoute;
use App\RouteOptimization\RouteOptimizerInterface;
use App\Service\OptimizationLogger;
use App\Service\RouteBuilder;
use App\Service\RouteCapacityValidator;
use App\Service\RouteSnapshotManager;
use App\Service\AddressRiskService;
use App\Service\CoordinateCorrectionService;
use App\Service\ServiceTimeCalibrationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteBuilder::class)]
final class RouteBuilderServiceTimeOverrideTest extends TestCase
{
    #[Test]
    public function service_time_overrides_are_applied_to_optimizer_jobs(): void
    {
        $routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $optimizationLogger = $this->createMock(OptimizationLogger::class);
        $snapshotManager = $this->createMock(RouteSnapshotManager::class);

        $optimizer = $this->createMock(RouteOptimizerInterface::class);

        // Capture the jobs passed to the optimizer to verify service times
        $capturedJobs = null;
        $optimizer->expects(self::once())
            ->method('optimize')
            ->willReturnCallback(function ($vehicles, $jobs) use (&$capturedJobs) {
                $capturedJobs = $jobs;
                return new OptimizationResult(routes: [], unassignedJobIds: array_map(fn($j) => $j->id, $jobs));
            });

        $routeRepo->method('save');
        $routeRepo->method('flush');

        $calibrationService = $this->createMock(ServiceTimeCalibrationService::class);
        $addressRiskService = $this->createMock(AddressRiskService::class);
        $coordinateCorrectionService = $this->createMock(CoordinateCorrectionService::class);

        $builder = new RouteBuilder(
            $routeRepo,
            $stopRepo,
            $optimizer,
            $capacityValidator,
            $optimizationLogger,
            $snapshotManager,
            $calibrationService,
            $addressRiskService,
            $coordinateCorrectionService,
        );

        $shipment1 = $this->createShipment('123 Main St', 300);
        $shipment2 = $this->createShipment('456 Oak Ave', null);

        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(1000.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(10.0);
        $vehicle->method('getMaxParcels')->willReturn(50);
        $vehicle->method('getSkills')->willReturn([]);

        $customer = $this->createMock(Customer::class);

        $builder->buildRoutes(
            [$shipment1, $shipment2],
            [$vehicle],
            $customer,
            maxStopsPerRoute: 30,
            serviceTimeOverrides: ['456 Oak Ave' => 600],
        );

        self::assertNotNull($capturedJobs);
        self::assertCount(2, $capturedJobs);

        // Shipment 1: has its own service time (300), no override for its address
        self::assertSame(300, $capturedJobs[0]->serviceTimeSeconds);

        // Shipment 2: no service time (null → would default to 300), but override sets 600
        self::assertSame(600, $capturedJobs[1]->serviceTimeSeconds);
    }

    private function createShipment(string $address, ?int $serviceTimeSeconds): Shipment
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAddress')->willReturn($address);
        $shipment->method('getLatitude')->willReturn(40.0);
        $shipment->method('getLongitude')->willReturn(-3.0);
        $shipment->method('getServiceTimeSeconds')->willReturn($serviceTimeSeconds);
        $shipment->method('getTotalWeightKg')->willReturn(1.0);
        $shipment->method('getTotalVolumeM3')->willReturn(0.1);
        $shipment->method('getTotalParcels')->willReturn(1);
        $shipment->method('getPriority')->willReturn(ShipmentPriority::NORMAL);
        $shipment->method('getPreferredWindowStart')->willReturn(null);
        $shipment->method('getPreferredWindowEnd')->willReturn(null);
        $shipment->method('getRequiredSkills')->willReturn([]);

        return $shipment;
    }
}
