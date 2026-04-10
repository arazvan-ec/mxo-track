<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\Customer;
use App\Entity\DriverAvailability;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\ShipmentPriority;
use App\RouteOptimization\OptimizableVehicle;
use App\RouteOptimization\OptimizationResult;
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
final class RouteBuilderShiftTest extends TestCase
{
    #[Test]
    public function vehicle_with_driver_availability_gets_shift_seconds(): void
    {
        $routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $optimizationLogger = $this->createMock(OptimizationLogger::class);
        $snapshotManager = $this->createMock(RouteSnapshotManager::class);
        $calibrationService = $this->createMock(ServiceTimeCalibrationService::class);
        $calibrationService->method('getCalibratedServiceTimesWithFeedback')->willReturn([]);
        $addressRiskService = $this->createMock(AddressRiskService::class);
        $coordinateCorrectionService = $this->createMock(CoordinateCorrectionService::class);

        // Capture vehicles passed to optimizer
        $capturedVehicles = null;
        $optimizer = $this->createMock(RouteOptimizerInterface::class);
        $optimizer->expects(self::once())
            ->method('optimize')
            ->willReturnCallback(function ($vehicles, $jobs) use (&$capturedVehicles) {
                $capturedVehicles = $vehicles;
                return new OptimizationResult(routes: [], unassignedJobIds: [0]);
            });

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

        $shipment = $this->createShipment();

        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(1000.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(10.0);
        $vehicle->method('getMaxParcels')->willReturn(50);
        $vehicle->method('getSkills')->willReturn([]);

        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('1');

        // DriverAvailability "08:00" - "18:00"
        $driver = $this->createMock(User::class);
        $availability = new DriverAvailability($driver, 1, '08:00', '18:00');

        $builder->buildRoutes(
            [$shipment],
            [$vehicle],
            $customer,
            driverAvailabilities: [0 => $availability],
        );

        self::assertNotNull($capturedVehicles);
        self::assertCount(1, $capturedVehicles);

        /** @var OptimizableVehicle $optimizableVehicle */
        $optimizableVehicle = $capturedVehicles[0];
        // 08:00 = 8 * 3600 = 28800
        self::assertSame(28800, $optimizableVehicle->shiftStartSeconds);
        // 18:00 = 18 * 3600 = 64800
        self::assertSame(64800, $optimizableVehicle->shiftEndSeconds);
    }

    #[Test]
    public function vehicle_without_availability_has_null_shift(): void
    {
        $routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $optimizationLogger = $this->createMock(OptimizationLogger::class);
        $snapshotManager = $this->createMock(RouteSnapshotManager::class);
        $calibrationService = $this->createMock(ServiceTimeCalibrationService::class);
        $calibrationService->method('getCalibratedServiceTimesWithFeedback')->willReturn([]);
        $addressRiskService = $this->createMock(AddressRiskService::class);
        $coordinateCorrectionService = $this->createMock(CoordinateCorrectionService::class);

        $capturedVehicles = null;
        $optimizer = $this->createMock(RouteOptimizerInterface::class);
        $optimizer->expects(self::once())
            ->method('optimize')
            ->willReturnCallback(function ($vehicles, $jobs) use (&$capturedVehicles) {
                $capturedVehicles = $vehicles;
                return new OptimizationResult(routes: [], unassignedJobIds: [0]);
            });

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

        $shipment = $this->createShipment();

        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(1000.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(10.0);
        $vehicle->method('getMaxParcels')->willReturn(50);
        $vehicle->method('getSkills')->willReturn([]);

        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('1');

        // No driver availabilities passed
        $builder->buildRoutes(
            [$shipment],
            [$vehicle],
            $customer,
        );

        self::assertNotNull($capturedVehicles);
        self::assertCount(1, $capturedVehicles);

        /** @var OptimizableVehicle $optimizableVehicle */
        $optimizableVehicle = $capturedVehicles[0];
        self::assertNull($optimizableVehicle->shiftStartSeconds);
        self::assertNull($optimizableVehicle->shiftEndSeconds);
    }

    private function createShipment(): Shipment
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAddress')->willReturn('Test Address');
        $shipment->method('getLatitude')->willReturn(40.0);
        $shipment->method('getLongitude')->willReturn(-3.0);
        $shipment->method('getServiceTimeSeconds')->willReturn(300);
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
