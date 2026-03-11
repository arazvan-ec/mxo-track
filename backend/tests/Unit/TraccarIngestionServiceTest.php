<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Route;
use App\Entity\Vehicle;
use App\Entity\VehicleCheckpoint;
use App\Entity\VehicleLastPosition;
use App\Entity\VehiclePosition;
use App\Enum\RouteStatus;
use App\Service\TraccarIngestionService;
use App\Tracking\DevicePosition;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(TraccarIngestionService::class)]
final class TraccarIngestionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private HubInterface&MockObject $hub;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private TraccarIngestionService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->hub = $this->createMock(HubInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->service = new TraccarIngestionService($this->entityManager, $this->hub, $this->eventDispatcher);
    }

    #[Test]
    public function ingestCreatesPositionForNewData(): void
    {
        $vehicle = new Vehicle('Test Truck');
        $vehicle->initializePublicId();

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')
            ->with(['vehicle' => $vehicle, 'status' => RouteStatus::ACTIVE])
            ->willReturn(null);

        $lastPosRepo = $this->createMock(EntityRepository::class);
        $lastPosRepo->method('findOneBy')
            ->with(['vehicle' => $vehicle])
            ->willReturn(null);

        $checkpointRepo = $this->createMock(EntityRepository::class);
        $checkpointRepo->method('findOneBy')
            ->with(['vehicle' => $vehicle])
            ->willReturn(null);

        $positionRepo = $this->createMock(EntityRepository::class);
        $positionRepo->method('findOneBy')
            ->willReturn(null); // no duplicate

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($routeRepo, $lastPosRepo, $checkpointRepo, $positionRepo) {
                return match ($class) {
                    Route::class => $routeRepo,
                    VehicleLastPosition::class => $lastPosRepo,
                    VehicleCheckpoint::class => $checkpointRepo,
                    VehiclePosition::class => $positionRepo,
                    default => $this->createMock(EntityRepository::class),
                };
            });

        // Expect persist calls: VehiclePosition + VehicleLastPosition + VehicleCheckpoint
        $this->entityManager->expects(self::exactly(3))
            ->method('persist');

        $this->entityManager->expects(self::once())
            ->method('flush');

        $this->hub->expects(self::once())
            ->method('publish')
            ->with(self::isInstanceOf(Update::class));

        $positions = [
            new DevicePosition(
                latitude: 40.4168,
                longitude: -3.7038,
                speed: 45.5,
                course: 180.0,
                accuracy: 5.0,
                deviceTime: new \DateTimeImmutable('2025-06-01T12:00:00+00:00'),
                serverTime: new \DateTimeImmutable('2025-06-01T12:00:01+00:00'),
                rawId: 101,
            ),
        ];

        $created = $this->service->ingestForVehicle($vehicle, $positions);

        self::assertSame(1, $created);
    }

    #[Test]
    public function ingestSkipsDuplicatePositions(): void
    {
        $vehicle = new Vehicle('Test Van');
        $vehicle->initializePublicId();

        $existingPosition = $this->createMock(VehiclePosition::class);

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')->willReturn(null);

        $lastPosRepo = $this->createMock(EntityRepository::class);
        $lastPosRepo->method('findOneBy')->willReturn(null);

        $checkpointRepo = $this->createMock(EntityRepository::class);
        $checkpointRepo->method('findOneBy')->willReturn(null);

        $positionRepo = $this->createMock(EntityRepository::class);
        $positionRepo->method('findOneBy')
            ->willReturn($existingPosition); // duplicate exists

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($routeRepo, $lastPosRepo, $checkpointRepo, $positionRepo) {
                return match ($class) {
                    Route::class => $routeRepo,
                    VehicleLastPosition::class => $lastPosRepo,
                    VehicleCheckpoint::class => $checkpointRepo,
                    VehiclePosition::class => $positionRepo,
                    default => $this->createMock(EntityRepository::class),
                };
            });

        $this->entityManager->expects(self::never())
            ->method('persist');

        $this->entityManager->expects(self::never())
            ->method('flush');

        $positions = [
            new DevicePosition(
                latitude: 40.4168,
                longitude: -3.7038,
                speed: 0.0,
                course: 0.0,
                accuracy: 0.0,
                deviceTime: new \DateTimeImmutable('2025-06-01T12:00:00+00:00'),
                serverTime: new \DateTimeImmutable('2025-06-01T12:00:01+00:00'),
            ),
        ];

        $created = $this->service->ingestForVehicle($vehicle, $positions);

        self::assertSame(0, $created);
    }

    #[Test]
    public function ingestCreatesMultiplePositions(): void
    {
        $vehicle = new Vehicle('Multi Truck');
        $vehicle->initializePublicId();

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')->willReturn(null);

        $lastPosRepo = $this->createMock(EntityRepository::class);
        $lastPosRepo->method('findOneBy')->willReturn(null);

        $checkpointRepo = $this->createMock(EntityRepository::class);
        $checkpointRepo->method('findOneBy')->willReturn(null);

        $positionRepo = $this->createMock(EntityRepository::class);
        $positionRepo->method('findOneBy')
            ->willReturn(null); // no duplicates

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($routeRepo, $lastPosRepo, $checkpointRepo, $positionRepo) {
                return match ($class) {
                    Route::class => $routeRepo,
                    VehicleLastPosition::class => $lastPosRepo,
                    VehicleCheckpoint::class => $checkpointRepo,
                    VehiclePosition::class => $positionRepo,
                    default => $this->createMock(EntityRepository::class),
                };
            });

        // First position: VehiclePosition + VehicleLastPosition + VehicleCheckpoint = 3 persist
        // Second position: VehiclePosition = 1 persist (last pos and checkpoint are reused via reference)
        $this->entityManager->expects(self::exactly(4))
            ->method('persist');

        $this->entityManager->expects(self::exactly(2))
            ->method('flush');

        $positions = [
            new DevicePosition(
                latitude: 40.4168,
                longitude: -3.7038,
                speed: 0.0,
                course: 0.0,
                accuracy: 0.0,
                deviceTime: new \DateTimeImmutable('2025-06-01T12:00:00+00:00'),
                serverTime: new \DateTimeImmutable('2025-06-01T12:00:01+00:00'),
            ),
            new DevicePosition(
                latitude: 40.4200,
                longitude: -3.7100,
                speed: 0.0,
                course: 0.0,
                accuracy: 0.0,
                deviceTime: new \DateTimeImmutable('2025-06-01T12:01:00+00:00'),
                serverTime: new \DateTimeImmutable('2025-06-01T12:01:01+00:00'),
            ),
        ];

        $created = $this->service->ingestForVehicle($vehicle, $positions);

        self::assertSame(2, $created);
    }

    #[Test]
    public function ingestAssociatesPositionWithActiveRoute(): void
    {
        $vehicle = new Vehicle('Route Truck');
        $vehicle->initializePublicId();
        $activeRoute = new Route('Test Route');
        $activeRoute->setStatus(RouteStatus::ACTIVE);

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')
            ->with(['vehicle' => $vehicle, 'status' => RouteStatus::ACTIVE])
            ->willReturn($activeRoute);

        $lastPosRepo = $this->createMock(EntityRepository::class);
        $lastPosRepo->method('findOneBy')->willReturn(null);

        $checkpointRepo = $this->createMock(EntityRepository::class);
        $checkpointRepo->method('findOneBy')->willReturn(null);

        $positionRepo = $this->createMock(EntityRepository::class);
        $positionRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($routeRepo, $lastPosRepo, $checkpointRepo, $positionRepo) {
                return match ($class) {
                    Route::class => $routeRepo,
                    VehicleLastPosition::class => $lastPosRepo,
                    VehicleCheckpoint::class => $checkpointRepo,
                    VehiclePosition::class => $positionRepo,
                    default => $this->createMock(EntityRepository::class),
                };
            });

        $persistedEntities = [];
        $this->entityManager->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedEntities): void {
                $persistedEntities[] = $entity;
            });

        $this->entityManager->method('flush');

        $positions = [
            new DevicePosition(
                latitude: 40.4168,
                longitude: -3.7038,
                speed: 0.0,
                course: 0.0,
                accuracy: 0.0,
                deviceTime: new \DateTimeImmutable('2025-06-01T12:00:00+00:00'),
                serverTime: new \DateTimeImmutable('2025-06-01T12:00:01+00:00'),
            ),
        ];

        $this->service->ingestForVehicle($vehicle, $positions);

        // Find the VehiclePosition entity among persisted entities
        $vehiclePosition = null;
        foreach ($persistedEntities as $entity) {
            if ($entity instanceof VehiclePosition) {
                $vehiclePosition = $entity;
                break;
            }
        }

        self::assertNotNull($vehiclePosition, 'VehiclePosition should have been persisted');
        self::assertSame($activeRoute, $vehiclePosition->getRoute());
    }

    #[Test]
    public function ingestContinuesOnMercureFailure(): void
    {
        $vehicle = new Vehicle('Mercure Fail Truck');
        $vehicle->initializePublicId();

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')->willReturn(null);

        $lastPosRepo = $this->createMock(EntityRepository::class);
        $lastPosRepo->method('findOneBy')->willReturn(null);

        $checkpointRepo = $this->createMock(EntityRepository::class);
        $checkpointRepo->method('findOneBy')->willReturn(null);

        $positionRepo = $this->createMock(EntityRepository::class);
        $positionRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($routeRepo, $lastPosRepo, $checkpointRepo, $positionRepo) {
                return match ($class) {
                    Route::class => $routeRepo,
                    VehicleLastPosition::class => $lastPosRepo,
                    VehicleCheckpoint::class => $checkpointRepo,
                    VehiclePosition::class => $positionRepo,
                    default => $this->createMock(EntityRepository::class),
                };
            });

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        // Mercure hub throws an exception
        $this->hub->method('publish')
            ->willThrowException(new \RuntimeException('Mercure hub unavailable'));

        $positions = [
            new DevicePosition(
                latitude: 40.4168,
                longitude: -3.7038,
                speed: 0.0,
                course: 0.0,
                accuracy: 0.0,
                deviceTime: new \DateTimeImmutable('2025-06-01T12:00:00+00:00'),
                serverTime: new \DateTimeImmutable('2025-06-01T12:00:01+00:00'),
            ),
        ];

        $created = $this->service->ingestForVehicle($vehicle, $positions);

        // Ingestion should succeed even though Mercure failed
        self::assertSame(1, $created);
    }

    #[Test]
    public function ingestReturnsZeroForEmptyPositions(): void
    {
        $vehicle = new Vehicle('Empty Truck');

        $routeRepo = $this->createMock(EntityRepository::class);
        $routeRepo->method('findOneBy')->willReturn(null);

        $lastPosRepo = $this->createMock(EntityRepository::class);
        $lastPosRepo->method('findOneBy')->willReturn(null);

        $checkpointRepo = $this->createMock(EntityRepository::class);
        $checkpointRepo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->willReturnCallback(function (string $class) use ($routeRepo, $lastPosRepo, $checkpointRepo) {
                return match ($class) {
                    Route::class => $routeRepo,
                    VehicleLastPosition::class => $lastPosRepo,
                    VehicleCheckpoint::class => $checkpointRepo,
                    default => $this->createMock(EntityRepository::class),
                };
            });

        $this->entityManager->expects(self::never())->method('persist');

        $created = $this->service->ingestForVehicle($vehicle, []);

        self::assertSame(0, $created);
    }
}
