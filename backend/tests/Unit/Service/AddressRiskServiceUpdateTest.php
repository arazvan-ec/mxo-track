<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\AddressRisk;
use App\Enum\ExceptionCode;
use App\Repository\AddressRiskRepository;
use App\Service\AddressRiskService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(AddressRiskService::class)]
final class AddressRiskServiceUpdateTest extends TestCase
{
    private AddressRiskService $service;
    private EntityManagerInterface $em;
    private AddressRiskRepository $repo;
    private LoggerInterface $logger;
    private Route $route;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(AddressRiskRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new AddressRiskService($this->em, $this->logger, $this->repo);

        $this->route = new Route('Test Route');
    }

    #[Test]
    public function allDeliveredCreatesRiskWithZeroExceptions(): void
    {
        $address = '123 Main St, Mexico City';
        $hash = md5(mb_strtolower($address));

        $stops = [];
        for ($i = 0; $i < 3; $i++) {
            $stop = new RouteStop($this->route, $i + 1, $address);
            $stop->markDelivered();
            $stops[] = $stop;
        }

        $this->repo->method('findByAddressHash')->willReturn(null);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $this->em->expects(self::once())->method('flush');

        $this->service->updateFromRouteStops($stops);

        self::assertCount(1, $persisted);
        $risk = $persisted[0];
        self::assertInstanceOf(AddressRisk::class, $risk);
        self::assertSame($hash, $risk->getAddressHash());
        self::assertSame(3, $risk->getTotalDeliveries());
        self::assertSame(0, $risk->getExceptionCount());
        self::assertSame(0.0, $risk->getExceptionRate());
    }

    #[Test]
    public function mixedDeliveredAndExceptionCalculatesRate(): void
    {
        $address = '456 Oak Ave, Guadalajara';
        $hash = md5(mb_strtolower($address));

        $stop1 = new RouteStop($this->route, 1, $address);
        $stop1->markDelivered();

        $stop2 = new RouteStop($this->route, 2, $address);
        $stop2->markDelivered();

        $stop3 = new RouteStop($this->route, 3, $address);
        $stop3->markException(ExceptionCode::ABSENT, 'Nobody home');

        $stops = [$stop1, $stop2, $stop3];

        $this->repo->method('findByAddressHash')->willReturn(null);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted) {
            $persisted[] = $entity;
        });
        $this->em->expects(self::once())->method('flush');

        $this->service->updateFromRouteStops($stops);

        self::assertCount(1, $persisted);
        $risk = $persisted[0];
        self::assertInstanceOf(AddressRisk::class, $risk);
        self::assertSame(3, $risk->getTotalDeliveries());
        self::assertSame(1, $risk->getExceptionCount());
        self::assertEqualsWithDelta(1 / 3, $risk->getExceptionRate(), 0.01);
        self::assertSame(['ABSENT'], $risk->getLastExceptionCodes());
    }

    #[Test]
    public function existingRiskGetsIncrementalUpdate(): void
    {
        $address = '789 Pine Rd, Monterrey';
        $hash = md5(mb_strtolower($address));

        $existingRisk = new AddressRisk($hash, $address);
        $existingRisk->setTotalDeliveries(5);
        $existingRisk->setExceptionCount(1);
        $existingRisk->setExceptionRate(0.2);

        $stop1 = new RouteStop($this->route, 1, $address);
        $stop1->markDelivered();

        $stop2 = new RouteStop($this->route, 2, $address);
        $stop2->markException(ExceptionCode::WRONG_ADDRESS, 'Bad address');

        $stops = [$stop1, $stop2];

        $this->repo->method('findByAddressHash')->with($hash)->willReturn($existingRisk);

        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $this->service->updateFromRouteStops($stops);

        self::assertSame(7, $existingRisk->getTotalDeliveries());
        self::assertSame(2, $existingRisk->getExceptionCount());
        self::assertEqualsWithDelta(2 / 7, $existingRisk->getExceptionRate(), 0.01);
        self::assertSame(['WRONG_ADDRESS'], $existingRisk->getLastExceptionCodes());
    }

    #[Test]
    public function skipsPendingAndOriginStops(): void
    {
        $address = '100 Center St, Puebla';

        // PENDING stop — not yet completed
        $pendingStop = new RouteStop($this->route, 1, $address);
        // status defaults to PENDING, no method call needed

        // Origin stop — warehouse, not a delivery
        $originStop = new RouteStop($this->route, 0, '500 Warehouse Blvd');
        $originStop->setOrigin(true);
        $originStop->markDelivered();

        // SKIPPED stop
        $skippedStop = new RouteStop($this->route, 2, $address);
        $skippedStop->markSkipped('Customer cancelled');

        $stops = [$pendingStop, $originStop, $skippedStop];

        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');

        $this->service->updateFromRouteStops($stops);
    }
}
