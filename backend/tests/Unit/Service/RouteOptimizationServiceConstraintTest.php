<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\Customer;
use App\Entity\Vehicle;
use App\Enum\RouteStopStatus;
use App\Enum\ShipmentPriority;
use App\Enum\VehicleSkill;
use App\RouteOptimization\OptimizableJob;
use App\RouteOptimization\OptimizableVehicle;
use App\RouteOptimization\OptimizationResult;
use App\RouteOptimization\OptimizedRoute;
use App\RouteOptimization\OptimizedStep;
use App\RouteOptimization\RouteOptimizerInterface;
use App\Routing\MultiWaypointRouteResult;
use App\Routing\RouteResult;
use App\Routing\RoutingEngineInterface;
use App\Service\OptimizationLogger;
use App\Service\RouteOptimizationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteOptimizationService::class)]
final class RouteOptimizationServiceConstraintTest extends TestCase
{
    private RouteStopRepositoryInterface $stopRepo;
    private RouteOptimizerInterface $optimizer;
    private RoutingEngineInterface $routingEngine;
    private OptimizationLogger $logger;
    /** @var list<OptimizableJob> */
    private array $capturedJobs = [];
    /** @var list<OptimizableVehicle> */
    private array $capturedVehicles = [];

    protected function setUp(): void
    {
        $this->stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $this->routingEngine = $this->createMock(RoutingEngineInterface::class);
        $this->logger = $this->createMock(OptimizationLogger::class);

        $this->optimizer = $this->createMock(RouteOptimizerInterface::class);
        $this->optimizer->method('optimize')
            ->willReturnCallback(function (array $vehicles, array $jobs) {
                $this->capturedVehicles = $vehicles;
                $this->capturedJobs = $jobs;

                $steps = [new OptimizedStep(jobId: -1, type: 'start', arrivalSeconds: 0, serviceSeconds: 0)];
                foreach ($jobs as $j) {
                    $steps[] = new OptimizedStep(jobId: $j->id, type: 'job', arrivalSeconds: 100, serviceSeconds: $j->serviceTimeSeconds);
                }
                $steps[] = new OptimizedStep(jobId: -1, type: 'end', arrivalSeconds: 500, serviceSeconds: 0);

                return new OptimizationResult(
                    routes: [new OptimizedRoute(vehicleId: 0, steps: $steps, distanceMeters: 5000, durationSeconds: 600)],
                    unassignedJobIds: [],
                );
            });

        // Mock routing engine for distance calculations
        $this->routingEngine->method('routeWithWaypoints')
            ->willReturn(new MultiWaypointRouteResult(totalDistanceKm: 5.0, totalDurationSeconds: 600, legs: []));
        $this->routingEngine->method('route')
            ->willReturn(new RouteResult(distanceKm: 2.0, durationSeconds: 300));
    }

    private function createService(): RouteOptimizationService
    {
        return new RouteOptimizationService(
            $this->stopRepo,
            $this->optimizer,
            $this->routingEngine,
            $this->logger,
        );
    }

    private function createRoute(?Vehicle $vehicle = null): Route
    {
        $customer = $this->createMock(Customer::class);
        $route = new Route('Test Route', $customer);
        if ($vehicle !== null) {
            $route->setVehicle($vehicle);
        }

        return $route;
    }

    private function createOriginStop(?Route $route = null): RouteStop
    {
        $r = $route ?? $this->createRoute();
        $stop = new RouteStop($r, 0, 'Origin');
        $stop->setLatitude(40.4168);
        $stop->setLongitude(-3.7038);
        $stop->setOrigin(true);

        return $stop;
    }

    private function createDeliveryStop(
        ?Shipment $shipment = null,
        float $lat = 40.42,
        float $lng = -3.71,
        string $address = 'Test Address',
        RouteStopStatus $status = RouteStopStatus::PENDING,
        ?Route $route = null,
    ): RouteStop {
        $r = $route ?? $this->createRoute();
        $stop = new RouteStop($r, 1, $address);
        $stop->setLatitude($lat);
        $stop->setLongitude($lng);
        $stop->setOrigin(false);
        if ($status === RouteStopStatus::DELIVERED) {
            $stop->markDelivered();
        } elseif ($status === RouteStopStatus::SKIPPED) {
            $stop->skip('test');
        }
        // PENDING is the default, no need to set
        if ($shipment !== null) {
            $stop->setShipment($shipment);
        }

        return $stop;
    }

    private function createShipment(
        ?int $serviceTimeSeconds = null,
        ShipmentPriority $priority = ShipmentPriority::NORMAL,
        array $skills = [],
        ?\DateTimeImmutable $windowStart = null,
        ?\DateTimeImmutable $windowEnd = null,
        ?float $weightKg = null,
        ?float $volumeM3 = null,
        int $parcels = 1,
    ): Shipment {
        $customer = $this->createMock(Customer::class);
        $shipment = new Shipment('REF-' . bin2hex(random_bytes(4)), $customer);
        $shipment->setLatitude(40.42);
        $shipment->setLongitude(-3.71);
        $shipment->setAddress('Test Address');

        if ($serviceTimeSeconds !== null) {
            $shipment->setServiceTimeSeconds($serviceTimeSeconds);
        }
        $shipment->setPriority($priority);
        if ($skills !== []) {
            $shipment->setRequiredSkills($skills);
        }
        if ($windowStart !== null) {
            $shipment->setPreferredWindowStart($windowStart);
        }
        if ($windowEnd !== null) {
            $shipment->setPreferredWindowEnd($windowEnd);
        }
        if ($weightKg !== null) {
            $shipment->setTotalWeightKg($weightKg);
        }
        if ($volumeM3 !== null) {
            $shipment->setTotalVolumeM3($volumeM3);
        }
        $shipment->setTotalParcels($parcels);

        return $shipment;
    }

    // ── Tarea 2a: buildJobFromStop tests ──

    #[Test]
    public function optimizeStopOrder_passes_time_windows_from_shipment(): void
    {
        $shipment = $this->createShipment(
            windowStart: new \DateTimeImmutable('2026-04-10 09:00:00'),
            windowEnd: new \DateTimeImmutable('2026-04-10 12:00:00'),
        );

        $origin = $this->createOriginStop();
        $stop1 = $this->createDeliveryStop($shipment, 40.42, -3.71);
        $stop2 = $this->createDeliveryStop(null, 40.43, -3.72);

        $route = $this->createRoute();
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $stop1, $stop2]);

        $service = $this->createService();
        $service->optimizeStopOrder($route);

        // The first job (from stop1 with shipment) should have time windows
        self::assertNotEmpty($this->capturedJobs);
        $firstJob = $this->capturedJobs[0];
        self::assertNotEmpty($firstJob->timeWindows, 'Job from shipment with time windows should have timeWindows set');
        self::assertSame(32400, $firstJob->timeWindows[0]['start']); // 9:00 = 9*3600
        self::assertSame(43200, $firstJob->timeWindows[0]['end']);   // 12:00 = 12*3600
    }

    #[Test]
    public function optimizeStopOrder_passes_skills_from_shipment(): void
    {
        $shipment = $this->createShipment(skills: [VehicleSkill::REFRIGERATED]);

        $origin = $this->createOriginStop();
        $stop1 = $this->createDeliveryStop($shipment, 40.42, -3.71);
        $stop2 = $this->createDeliveryStop(null, 40.43, -3.72);

        $route = $this->createRoute();
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $stop1, $stop2]);

        $service = $this->createService();
        $service->optimizeStopOrder($route);

        $firstJob = $this->capturedJobs[0];
        self::assertContains((string) VehicleSkill::REFRIGERATED->value, $firstJob->requiredSkills);
    }

    #[Test]
    public function optimizeStopOrder_passes_priority_from_shipment(): void
    {
        $shipment = $this->createShipment(priority: ShipmentPriority::URGENT);

        $origin = $this->createOriginStop();
        $stop1 = $this->createDeliveryStop($shipment, 40.42, -3.71);
        $stop2 = $this->createDeliveryStop(null, 40.43, -3.72);

        $route = $this->createRoute();
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $stop1, $stop2]);

        $service = $this->createService();
        $service->optimizeStopOrder($route);

        $firstJob = $this->capturedJobs[0];
        self::assertSame(75, $firstJob->priority); // URGENT = 75
    }

    #[Test]
    public function optimizeStopOrder_passes_service_time_from_shipment(): void
    {
        $shipment = $this->createShipment(serviceTimeSeconds: 600);

        $origin = $this->createOriginStop();
        $stop1 = $this->createDeliveryStop($shipment, 40.42, -3.71);
        $stop2 = $this->createDeliveryStop(null, 40.43, -3.72);

        $route = $this->createRoute();
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $stop1, $stop2]);

        $service = $this->createService();
        $service->optimizeStopOrder($route);

        $firstJob = $this->capturedJobs[0];
        self::assertSame(600, $firstJob->serviceTimeSeconds);
    }

    #[Test]
    public function optimizeStopOrder_uses_default_300s_when_no_shipment(): void
    {
        $origin = $this->createOriginStop();
        $stop1 = $this->createDeliveryStop(null, 40.42, -3.71);
        $stop2 = $this->createDeliveryStop(null, 40.43, -3.72);

        $route = $this->createRoute();
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $stop1, $stop2]);

        $service = $this->createService();
        $service->optimizeStopOrder($route);

        foreach ($this->capturedJobs as $job) {
            self::assertSame(300, $job->serviceTimeSeconds);
        }
    }

    // ── Tarea 2b: buildVehicleFromRoute tests ──

    #[Test]
    public function optimizeStopOrder_passes_vehicle_capacity_from_route(): void
    {
        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(1000.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(5.0);
        $vehicle->method('getMaxParcels')->willReturn(50);
        $vehicle->method('getSkills')->willReturn([VehicleSkill::HEAVY_LOAD]);

        $origin = $this->createOriginStop();
        $stop1 = $this->createDeliveryStop(null, 40.42, -3.71);
        $stop2 = $this->createDeliveryStop(null, 40.43, -3.72);

        $route = $this->createRoute($vehicle);
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $stop1, $stop2]);

        $service = $this->createService();
        $service->optimizeStopOrder($route);

        self::assertNotEmpty($this->capturedVehicles);
        $v = $this->capturedVehicles[0];
        self::assertSame(1000.0, $v->maxWeightKg);
        self::assertSame(5.0, $v->maxVolumeM3);
        self::assertSame(50, $v->maxParcels);
        self::assertContains((string) VehicleSkill::HEAVY_LOAD->value, $v->skills);
    }

    #[Test]
    public function optimizeStopOrder_defaults_when_no_vehicle_on_route(): void
    {
        $origin = $this->createOriginStop();
        $stop1 = $this->createDeliveryStop(null, 40.42, -3.71);
        $stop2 = $this->createDeliveryStop(null, 40.43, -3.72);

        $route = $this->createRoute(null);
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $stop1, $stop2]);

        $service = $this->createService();
        $service->optimizeStopOrder($route);

        $v = $this->capturedVehicles[0];
        self::assertNull($v->maxWeightKg);
        self::assertNull($v->maxVolumeM3);
        self::assertNull($v->maxParcels);
        self::assertSame([], $v->skills);
    }

    // ── Tarea 2d: reoptimizePendingStops tests ──

    #[Test]
    public function reoptimizePendingStops_passes_time_windows_from_shipment(): void
    {
        $shipment = $this->createShipment(
            windowStart: new \DateTimeImmutable('2026-04-10 14:00:00'),
            windowEnd: new \DateTimeImmutable('2026-04-10 17:00:00'),
        );

        $origin = $this->createOriginStop();
        $deliveredStop = $this->createDeliveryStop(null, 40.41, -3.70, 'Delivered', RouteStopStatus::DELIVERED);
        $deliveredStop->setSequence(1);
        $pendingStop1 = $this->createDeliveryStop($shipment, 40.42, -3.71, 'Pending 1');
        $pendingStop1->setSequence(2);
        $pendingStop2 = $this->createDeliveryStop(null, 40.43, -3.72, 'Pending 2');
        $pendingStop2->setSequence(3);

        $route = $this->createRoute();
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $deliveredStop, $pendingStop1, $pendingStop2]);

        $service = $this->createService();
        $service->reoptimizePendingStops($route, 40.415, -3.705);

        $firstJob = $this->capturedJobs[0];
        self::assertNotEmpty($firstJob->timeWindows);
        self::assertSame(50400, $firstJob->timeWindows[0]['start']); // 14:00 = 14*3600
        self::assertSame(61200, $firstJob->timeWindows[0]['end']);   // 17:00 = 17*3600
    }

    #[Test]
    public function reoptimizePendingStops_passes_service_time_from_shipment(): void
    {
        $shipment = $this->createShipment(serviceTimeSeconds: 900);

        $origin = $this->createOriginStop();
        $pendingStop1 = $this->createDeliveryStop($shipment, 40.42, -3.71);
        $pendingStop1->setSequence(1);
        $pendingStop2 = $this->createDeliveryStop(null, 40.43, -3.72);
        $pendingStop2->setSequence(2);

        $route = $this->createRoute();
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $pendingStop1, $pendingStop2]);

        $service = $this->createService();
        $service->reoptimizePendingStops($route);

        $firstJob = $this->capturedJobs[0];
        self::assertSame(900, $firstJob->serviceTimeSeconds);
    }

    #[Test]
    public function reoptimizePendingStops_passes_vehicle_capacity(): void
    {
        $vehicle = $this->createMock(Vehicle::class);
        $vehicle->method('getMaxWeightKg')->willReturn(500.0);
        $vehicle->method('getMaxVolumeM3')->willReturn(3.0);
        $vehicle->method('getMaxParcels')->willReturn(30);
        $vehicle->method('getSkills')->willReturn([]);

        $origin = $this->createOriginStop();
        $pendingStop1 = $this->createDeliveryStop(null, 40.42, -3.71);
        $pendingStop1->setSequence(1);
        $pendingStop2 = $this->createDeliveryStop(null, 40.43, -3.72);
        $pendingStop2->setSequence(2);

        $route = $this->createRoute($vehicle);
        $this->stopRepo->method('findByRoute')->willReturn([$origin, $pendingStop1, $pendingStop2]);

        $service = $this->createService();
        $service->reoptimizePendingStops($route, 40.415, -3.705);

        $v = $this->capturedVehicles[0];
        self::assertSame(500.0, $v->maxWeightKg);
        self::assertSame(3.0, $v->maxVolumeM3);
        self::assertSame(30, $v->maxParcels);
    }
}
