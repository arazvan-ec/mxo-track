<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\DriverAction;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\User;
use App\Service\DriverActionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(DriverActionService::class)]
final class DriverActionServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private DriverActionService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new DriverActionService($this->entityManager);
    }

    #[Test]
    public function registerReturnsTrueForNewAction(): void
    {
        $driver = new User('driver@test.com');
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $clientActionId = Uuid::v4()->toRfc4122();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')
            ->with([
                'driver' => $driver,
                'clientActionId' => Uuid::fromString($clientActionId),
            ])
            ->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(DriverAction::class)
            ->willReturn($repo);

        $this->entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(DriverAction::class));

        $this->entityManager->expects(self::once())
            ->method('flush');

        $result = $this->service->register($driver, $clientActionId, 'DELIVER', $stop);

        self::assertTrue($result);
    }

    #[Test]
    public function registerReturnsFalseForDuplicateClientActionId(): void
    {
        $driver = new User('driver@test.com');
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $clientActionId = Uuid::v4()->toRfc4122();

        $existingAction = new DriverAction(
            $driver,
            Uuid::fromString($clientActionId),
            'DELIVER',
            $stop,
        );

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')
            ->with([
                'driver' => $driver,
                'clientActionId' => Uuid::fromString($clientActionId),
            ])
            ->willReturn($existingAction);

        $this->entityManager->method('getRepository')
            ->with(DriverAction::class)
            ->willReturn($repo);

        $this->entityManager->expects(self::never())
            ->method('persist');

        $this->entityManager->expects(self::never())
            ->method('flush');

        $result = $this->service->register($driver, $clientActionId, 'DELIVER', $stop);

        self::assertFalse($result);
    }

    #[Test]
    public function registerWithExceptionTypeCreatesAction(): void
    {
        $driver = new User('driver@test.com');
        $route = new Route('Exception Route');
        $stop = new RouteStop($route, 2, 'Gran Via 50');
        $clientActionId = Uuid::v4()->toRfc4122();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(DriverAction::class)
            ->willReturn($repo);

        $persistedAction = null;
        $this->entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedAction): void {
                $persistedAction = $entity;
            });

        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->register($driver, $clientActionId, 'EXCEPTION', $stop);

        self::assertTrue($result);
        self::assertInstanceOf(DriverAction::class, $persistedAction);
    }

    #[Test]
    public function registerDifferentClientActionIdsForSameDriverBothSucceed(): void
    {
        $driver = new User('driver@test.com');
        $route = new Route('Multi Action Route');
        $stop = new RouteStop($route, 1, 'Address');
        $firstActionId = Uuid::v4()->toRfc4122();
        $secondActionId = Uuid::v4()->toRfc4122();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null); // both are new

        $this->entityManager->method('getRepository')
            ->with(DriverAction::class)
            ->willReturn($repo);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $result1 = $this->service->register($driver, $firstActionId, 'DELIVER', $stop);
        $result2 = $this->service->register($driver, $secondActionId, 'DELIVER', $stop);

        self::assertTrue($result1);
        self::assertTrue($result2);
    }
}
