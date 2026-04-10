<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\Customer;
use App\Entity\Vehicle;
use App\Enum\ShipmentPriority;
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
final class RouteBuilderAutoCalibrationTest extends TestCase
{
    #[Test]
    public function buildRoutes_without_serviceTimeOverrides_calls_calibration_service(): void
    {
        $routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $optimizationLogger = $this->createMock(OptimizationLogger::class);
        $snapshotManager = $this->createMock(RouteSnapshotManager::class);

        $optimizer = $this->createMock(RouteOptimizerInterface::class);
        $optimizer->method('optimize')
            ->willReturn(new OptimizationResult(routes: [], unassignedJobIds: [0]));

        $calibrationService = $this->createMock(ServiceTimeCalibrationService::class);
        $addressRiskService = $this->createMock(AddressRiskService::class);
        $coordinateCorrectionService = $this->createMock(CoordinateCorrectionService::class);

        // Calibration service SHOULD be called when no overrides provided
        $calibrationService->expects(self::once())
            ->method('getCalibratedServiceTimesWithFeedback')
            ->with(42, 50, 2)
            ->willReturn([
                ['address' => '123 Main St', 'avgSeconds' => 420, 'sampleCount' => 5, 'minSeconds' => 300, 'maxSeconds' => 600],
            ]);

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

        $shipment = $this->createShipment('123 Main St', null);

        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(1000.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(10.0);
        $vehicle->method('getMaxParcels')->willReturn(50);
        $vehicle->method('getSkills')->willReturn([]);

        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('42');

        // Capture jobs to verify calibrated service time was applied
        $capturedJobs = null;
        $optimizer->expects(self::once())
            ->method('optimize')
            ->willReturnCallback(function ($vehicles, $jobs) use (&$capturedJobs) {
                $capturedJobs = $jobs;
                return new OptimizationResult(routes: [], unassignedJobIds: [0]);
            });

        $builder->buildRoutes(
            [$shipment],
            [$vehicle],
            $customer,
        );

        self::assertNotNull($capturedJobs);
        self::assertCount(1, $capturedJobs);
        // Calibration returned 420s for '123 Main St' — should be used
        self::assertSame(420, $capturedJobs[0]->serviceTimeSeconds);
    }

    #[Test]
    public function buildRoutes_with_serviceTimeOverrides_does_not_call_calibration(): void
    {
        $routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $optimizationLogger = $this->createMock(OptimizationLogger::class);
        $snapshotManager = $this->createMock(RouteSnapshotManager::class);

        $optimizer = $this->createMock(RouteOptimizerInterface::class);
        $optimizer->method('optimize')
            ->willReturn(new OptimizationResult(routes: [], unassignedJobIds: [0]));

        $calibrationService = $this->createMock(ServiceTimeCalibrationService::class);
        $addressRiskService = $this->createMock(AddressRiskService::class);
        $coordinateCorrectionService = $this->createMock(CoordinateCorrectionService::class);

        // Calibration service should NOT be called when explicit overrides provided
        $calibrationService->expects(self::never())
            ->method('getCalibratedServiceTimesWithFeedback');

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

        $shipment = $this->createShipment('123 Main St', null);

        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(1000.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(10.0);
        $vehicle->method('getMaxParcels')->willReturn(50);
        $vehicle->method('getSkills')->willReturn([]);

        $customer = $this->createMock(Customer::class);

        $builder->buildRoutes(
            [$shipment],
            [$vehicle],
            $customer,
            serviceTimeOverrides: ['123 Main St' => 999],
        );
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
