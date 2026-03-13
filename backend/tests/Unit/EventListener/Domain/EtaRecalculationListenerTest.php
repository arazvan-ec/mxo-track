<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\Domain;

use App\Domain\Event\EtaChanged;
use App\Domain\Event\VehiclePositionReceived;
use App\Entity\Route;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use App\EventListener\Domain\EtaRecalculationListener;
use App\Repository\VehicleRepository;
use App\Service\EtaService;
use App\Service\RouteSnapshotManager;
use App\View\RouteViewService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mercure\HubInterface;

#[CoversClass(EtaRecalculationListener::class)]
final class EtaRecalculationListenerTest extends TestCase
{
    private EtaRecalculationListener $listener;
    private VehicleRepository $vehicleRepo;
    private EntityManagerInterface $em;
    private EtaService $etaService;
    private RouteSnapshotManager $snapshotManager;
    private RouteViewService $viewService;
    private HubInterface $hub;
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->vehicleRepo = $this->createMock(VehicleRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->etaService = $this->createMock(EtaService::class);
        $this->snapshotManager = $this->createMock(RouteSnapshotManager::class);
        $this->viewService = $this->createMock(RouteViewService::class);
        $this->hub = $this->createMock(HubInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->listener = new EtaRecalculationListener(
            $this->vehicleRepo,
            $this->em,
            $this->etaService,
            $this->snapshotManager,
            $this->viewService,
            $this->hub,
            $this->dispatcher,
        );
    }

    private function createVehicle(): Vehicle
    {
        $vehicle = new Vehicle('Test Van');
        $vehicle->initializePublicId();

        return $vehicle;
    }

    private function createActiveRoute(Vehicle $vehicle): Route
    {
        $route = new Route('Test Route');
        $route->initializePublicId();
        $route->setVehicle($vehicle);
        $route->setStatus(RouteStatus::ACTIVE);

        return $route;
    }

    #[Test]
    public function recalculatesEtasOnPositionReceived(): void
    {
        $vehicle = $this->createVehicle();
        $route = $this->createActiveRoute($vehicle);

        $this->vehicleRepo->method('findOneByPublicId')->willReturn($vehicle);

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')->with([
            'vehicle' => $vehicle,
            'status' => RouteStatus::ACTIVE,
        ])->willReturn($route);
        $this->em->method('getRepository')->with(Route::class)->willReturn($routeRepo);

        $etas = [
            'stop1' => ['eta' => new DateTimeImmutable('+15 min'), 'remainingMinutes' => 15, 'distanceKm' => 3.2],
        ];
        $this->etaService->expects(self::once())->method('calculateEtas')->with($route)->willReturn($etas);
        $this->snapshotManager->expects(self::once())->method('updateEtas')->with($route, $etas)->willReturn(null);

        $event = new VehiclePositionReceived(
            vehiclePublicId: $vehicle->getPublicIdString(),
            latitude: 40.416,
            longitude: -3.703,
            speed: 30.0,
            course: 180.0,
            deviceTime: new DateTimeImmutable(),
        );

        $this->listener->onVehiclePositionReceived($event);
    }

    #[Test]
    public function noVehicleIsNoOp(): void
    {
        $this->vehicleRepo->method('findOneByPublicId')->willReturn(null);

        $this->etaService->expects(self::never())->method('calculateEtas');

        $event = new VehiclePositionReceived(
            vehiclePublicId: 'nonexistent',
            latitude: 40.416,
            longitude: -3.703,
            speed: null,
            course: null,
            deviceTime: new DateTimeImmutable(),
        );

        $this->listener->onVehiclePositionReceived($event);
    }

    #[Test]
    public function noActiveRouteIsNoOp(): void
    {
        $vehicle = $this->createVehicle();
        $this->vehicleRepo->method('findOneByPublicId')->willReturn($vehicle);

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->with(Route::class)->willReturn($routeRepo);

        $this->etaService->expects(self::never())->method('calculateEtas');

        $event = new VehiclePositionReceived(
            vehiclePublicId: $vehicle->getPublicIdString(),
            latitude: 40.416,
            longitude: -3.703,
            speed: null,
            course: null,
            deviceTime: new DateTimeImmutable(),
        );

        $this->listener->onVehiclePositionReceived($event);
    }

    #[Test]
    public function dispatchesEtaChangedWhenDeltaExceedsThreshold(): void
    {
        $vehicle = $this->createVehicle();
        $route = $this->createActiveRoute($vehicle);

        $this->vehicleRepo->method('findOneByPublicId')->willReturn($vehicle);

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')->willReturn($route);
        $this->em->method('getRepository')->with(Route::class)->willReturn($routeRepo);

        $etas = [
            'stop1' => ['eta' => new DateTimeImmutable('+25 min'), 'remainingMinutes' => 25, 'distanceKm' => 5.0],
        ];
        $this->etaService->method('calculateEtas')->willReturn($etas);

        // Previous ETA was 15 min, now 25 min = delta 10 min (>= 5 threshold)
        $this->snapshotManager->method('updateEtas')->willReturn(['stop1' => 15]);

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (EtaChanged $e): bool {
                return $e->maxDeltaMinutes === 10
                    && $e->previousEtas === ['stop1' => 15]
                    && $e->currentEtas === ['stop1' => 25];
            }));

        $event = new VehiclePositionReceived(
            vehiclePublicId: $vehicle->getPublicIdString(),
            latitude: 40.416,
            longitude: -3.703,
            speed: 30.0,
            course: 180.0,
            deviceTime: new DateTimeImmutable(),
        );

        $this->listener->onVehiclePositionReceived($event);
    }

    #[Test]
    public function doesNotDispatchEtaChangedWhenDeltaBelowThreshold(): void
    {
        $vehicle = $this->createVehicle();
        $route = $this->createActiveRoute($vehicle);

        $this->vehicleRepo->method('findOneByPublicId')->willReturn($vehicle);

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')->willReturn($route);
        $this->em->method('getRepository')->with(Route::class)->willReturn($routeRepo);

        $etas = [
            'stop1' => ['eta' => new DateTimeImmutable('+17 min'), 'remainingMinutes' => 17, 'distanceKm' => 3.5],
        ];
        $this->etaService->method('calculateEtas')->willReturn($etas);

        // Previous ETA was 15 min, now 17 min = delta 2 min (< 5 threshold)
        $this->snapshotManager->method('updateEtas')->willReturn(['stop1' => 15]);

        $this->dispatcher->expects(self::never())->method('dispatch');

        $event = new VehiclePositionReceived(
            vehiclePublicId: $vehicle->getPublicIdString(),
            latitude: 40.416,
            longitude: -3.703,
            speed: 30.0,
            course: 180.0,
            deviceTime: new DateTimeImmutable(),
        );

        $this->listener->onVehiclePositionReceived($event);
    }
}
