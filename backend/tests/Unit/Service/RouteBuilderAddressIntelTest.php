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
use App\RouteOptimization\RouteOptimizerInterface;
use App\Service\AddressRiskService;
use App\Service\CoordinateCorrectionService;
use App\Service\OptimizationLogger;
use App\Service\RouteBuilder;
use App\Service\RouteCapacityValidator;
use App\Service\RouteSnapshotManager;
use App\Service\ServiceTimeCalibrationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteBuilder::class)]
final class RouteBuilderAddressIntelTest extends TestCase
{
    /** @var list<OptimizableJob> */
    private array $capturedJobs = [];

    private function createBuilder(
        ?AddressRiskService $riskService = null,
        ?CoordinateCorrectionService $coordService = null,
    ): RouteBuilder {
        $routeRepo = $this->createMock(RouteRepositoryInterface::class);
        $stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $optimizationLogger = $this->createMock(OptimizationLogger::class);
        $snapshotManager = $this->createMock(RouteSnapshotManager::class);
        $calibrationService = $this->createMock(ServiceTimeCalibrationService::class);
        $calibrationService->method('getCalibratedServiceTimesWithFeedback')->willReturn([]);

        $optimizer = $this->createMock(RouteOptimizerInterface::class);
        $optimizer->method('optimize')
            ->willReturnCallback(function ($vehicles, $jobs) {
                $this->capturedJobs = $jobs;
                return new OptimizationResult(routes: [], unassignedJobIds: array_map(fn($j) => $j->id, $jobs));
            });

        $routeRepo->method('save');
        $routeRepo->method('flush');

        return new RouteBuilder(
            $routeRepo,
            $stopRepo,
            $optimizer,
            $capacityValidator,
            $optimizationLogger,
            $snapshotManager,
            $calibrationService,
            $riskService ?? $this->createMock(AddressRiskService::class),
            $coordService ?? $this->createMock(CoordinateCorrectionService::class),
        );
    }

    private function createShipment(string $address, float $lat = 40.0, float $lng = -3.0): Shipment
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getAddress')->willReturn($address);
        $shipment->method('getLatitude')->willReturn($lat);
        $shipment->method('getLongitude')->willReturn($lng);
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

    private function createVehicle(): Vehicle
    {
        $v = $this->createMock(Vehicle::class);
        $v->method('getMaxWeightKg')->willReturn(1000.0);
        $v->method('getMaxVolumeM3')->willReturn(10.0);
        $v->method('getMaxParcels')->willReturn(50);
        $v->method('getSkills')->willReturn([]);

        return $v;
    }

    #[Test]
    public function high_risk_address_gets_service_time_buffer(): void
    {
        $riskService = $this->createMock(AddressRiskService::class);
        $riskService->method('checkAddress')
            ->with('123 Risky St')
            ->willReturn(['is_risky' => true, 'exception_rate' => 0.5, 'sample_count' => 10]);

        $builder = $this->createBuilder(riskService: $riskService);

        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('1');

        $builder->buildRoutes(
            [$this->createShipment('123 Risky St')],
            [$this->createVehicle()],
            $customer,
        );

        self::assertNotEmpty($this->capturedJobs);
        // 300s base + 120s buffer = 420s
        self::assertSame(420, $this->capturedJobs[0]->serviceTimeSeconds);
    }

    #[Test]
    public function normal_address_gets_no_buffer(): void
    {
        $riskService = $this->createMock(AddressRiskService::class);
        $riskService->method('checkAddress')
            ->willReturn(['is_risky' => false, 'exception_rate' => 0.1, 'sample_count' => 10]);

        $builder = $this->createBuilder(riskService: $riskService);

        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('1');

        $builder->buildRoutes(
            [$this->createShipment('456 Safe Ave')],
            [$this->createVehicle()],
            $customer,
        );

        self::assertNotEmpty($this->capturedJobs);
        self::assertSame(300, $this->capturedJobs[0]->serviceTimeSeconds);
    }

    #[Test]
    public function corrected_coordinates_override_shipment_coords(): void
    {
        $coordService = $this->createMock(CoordinateCorrectionService::class);
        $coordService->method('getCorrectedCoordinates')
            ->with('789 Corrected Blvd')
            ->willReturn([40.123, -3.456]);

        $builder = $this->createBuilder(coordService: $coordService);

        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('1');

        $builder->buildRoutes(
            [$this->createShipment('789 Corrected Blvd', 40.0, -3.0)],
            [$this->createVehicle()],
            $customer,
        );

        self::assertNotEmpty($this->capturedJobs);
        self::assertSame(40.123, $this->capturedJobs[0]->latitude);
        self::assertSame(-3.456, $this->capturedJobs[0]->longitude);
    }

    #[Test]
    public function no_coordinate_correction_uses_shipment_coords(): void
    {
        $coordService = $this->createMock(CoordinateCorrectionService::class);
        $coordService->method('getCorrectedCoordinates')->willReturn(null);

        $builder = $this->createBuilder(coordService: $coordService);

        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('1');

        $builder->buildRoutes(
            [$this->createShipment('Normal Address', 40.5, -3.5)],
            [$this->createVehicle()],
            $customer,
        );

        self::assertNotEmpty($this->capturedJobs);
        self::assertSame(40.5, $this->capturedJobs[0]->latitude);
        self::assertSame(-3.5, $this->capturedJobs[0]->longitude);
    }
}
