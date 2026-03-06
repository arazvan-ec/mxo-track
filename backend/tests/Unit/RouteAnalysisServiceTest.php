<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\RouteAnalysisResult;
use App\Entity\Customer;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\ExceptionCode;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Service\RouteAnalysisService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteAnalysisService::class)]
final class RouteAnalysisServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RouteAnalysisService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new RouteAnalysisService($this->em);
    }

    private function createDoneRoute(): Route
    {
        $route = new Route('Test Route');
        $route->initializePublicId();
        $route->start();
        $route->finish();

        return $route;
    }

    private function mockRepoStops(array $stops): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($stops);
        $this->em->method('getRepository')->willReturn($repo);
    }

    private function createDeliveredStop(Route $route, int $seq, string $address, \DateTimeImmutable $deliveredAt): RouteStop
    {
        $stop = new RouteStop($route, $seq, $address);
        $stop->initializePublicId();
        // Use reflection to set deliveredAt and status since markDelivered() uses current time
        $ref = new \ReflectionClass($stop);
        $statusProp = $ref->getProperty('status');
        $statusProp->setValue($stop, RouteStopStatus::DELIVERED);
        $deliveredProp = $ref->getProperty('deliveredAt');
        $deliveredProp->setValue($stop, $deliveredAt);

        return $stop;
    }

    private function createExceptionStop(Route $route, int $seq, string $address): RouteStop
    {
        $stop = new RouteStop($route, $seq, $address);
        $stop->initializePublicId();
        $stop->markException(ExceptionCode::ABSENT, 'Not home');

        return $stop;
    }

    #[Test]
    public function throwsWhenRouteNotDone(): void
    {
        $route = new Route('Test Route');
        $route->initializePublicId();
        // Status is PLANNED

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Route must be in DONE status');

        $this->service->analyzeRouteExecution($route);
    }

    #[Test]
    public function perfectSequenceAdherence(): void
    {
        $route = $this->createDoneRoute();
        $baseTime = new \DateTimeImmutable('2026-01-01 08:00:00');

        // Stops delivered in planned order: seq 1 → deliveredAt 08:05, seq 2 → deliveredAt 08:10
        $stop1 = $this->createDeliveredStop($route, 1, 'Addr A', $baseTime->modify('+5 minutes'));
        $stop2 = $this->createDeliveredStop($route, 2, 'Addr B', $baseTime->modify('+10 minutes'));

        $this->mockRepoStops([$stop1, $stop2]);

        $result = $this->service->analyzeRouteExecution($route);

        self::assertInstanceOf(RouteAnalysisResult::class, $result);
        self::assertSame(100.0, $result->sequenceAdherence);
        self::assertCount(2, $result->stops);
    }

    #[Test]
    public function zeroSequenceAdherenceWhenAllOutOfOrder(): void
    {
        $route = $this->createDoneRoute();
        $baseTime = new \DateTimeImmutable('2026-01-01 08:00:00');

        // Stops delivered in reverse: seq 1 → later time, seq 2 → earlier time
        $stop1 = $this->createDeliveredStop($route, 1, 'Addr A', $baseTime->modify('+10 minutes'));
        $stop2 = $this->createDeliveredStop($route, 2, 'Addr B', $baseTime->modify('+5 minutes'));

        $this->mockRepoStops([$stop1, $stop2]);

        $result = $this->service->analyzeRouteExecution($route);

        self::assertSame(0.0, $result->sequenceAdherence);
    }

    #[Test]
    public function averageServiceTimeCalculated(): void
    {
        $route = $this->createDoneRoute();
        $baseTime = new \DateTimeImmutable('2026-01-01 08:00:00');

        $stop1 = $this->createDeliveredStop($route, 1, 'Addr A', $baseTime);
        $stop2 = $this->createDeliveredStop($route, 2, 'Addr B', $baseTime->modify('+300 seconds'));
        $stop3 = $this->createDeliveredStop($route, 3, 'Addr C', $baseTime->modify('+600 seconds'));

        $this->mockRepoStops([$stop1, $stop2, $stop3]);

        $result = $this->service->analyzeRouteExecution($route);

        // Service time between stop1→stop2 = 300s, stop2→stop3 = 300s, avg = 300
        self::assertSame(300.0, $result->avgActualServiceTimeSeconds);
    }

    #[Test]
    public function recommendationWhenHighServiceTime(): void
    {
        $route = $this->createDoneRoute();
        $baseTime = new \DateTimeImmutable('2026-01-01 08:00:00');

        $stop1 = $this->createDeliveredStop($route, 1, 'Addr A', $baseTime);
        $stop2 = $this->createDeliveredStop($route, 2, 'Addr B', $baseTime->modify('+400 seconds'));

        $this->mockRepoStops([$stop1, $stop2]);

        $result = $this->service->analyzeRouteExecution($route);

        // avg service time = 400 > 360 → recommendation
        self::assertNotEmpty($result->recommendations);
        self::assertStringContainsString('service time', $result->recommendations[0]);
    }

    #[Test]
    public function recommendationWhenLowSequenceAdherence(): void
    {
        $route = $this->createDoneRoute();
        $baseTime = new \DateTimeImmutable('2026-01-01 08:00:00');

        // 4 stops, all out of order (delivered in reverse)
        $stop1 = $this->createDeliveredStop($route, 1, 'Addr A', $baseTime->modify('+40 minutes'));
        $stop2 = $this->createDeliveredStop($route, 2, 'Addr B', $baseTime->modify('+30 minutes'));
        $stop3 = $this->createDeliveredStop($route, 3, 'Addr C', $baseTime->modify('+20 minutes'));
        $stop4 = $this->createDeliveredStop($route, 4, 'Addr D', $baseTime->modify('+10 minutes'));

        $this->mockRepoStops([$stop1, $stop2, $stop3, $stop4]);

        $result = $this->service->analyzeRouteExecution($route);

        // 0% adherence < 70% → recommendation
        $hasAdherenceRec = false;
        foreach ($result->recommendations as $rec) {
            if (str_contains($rec, 'adherence')) {
                $hasAdherenceRec = true;
                break;
            }
        }
        self::assertTrue($hasAdherenceRec);
    }

    #[Test]
    public function recommendationWhenProblematicAddress(): void
    {
        $route = $this->createDoneRoute();
        $baseTime = new \DateTimeImmutable('2026-01-01 08:00:00');

        $stop1 = $this->createDeliveredStop($route, 1, 'Addr A', $baseTime);
        $stop2 = $this->createDeliveredStop($route, 2, 'Slow Place', $baseTime->modify('+700 seconds')); // >600s

        $this->mockRepoStops([$stop1, $stop2]);

        $result = $this->service->analyzeRouteExecution($route);

        $hasSlowRec = false;
        foreach ($result->recommendations as $rec) {
            if (str_contains($rec, 'Slow Place')) {
                $hasSlowRec = true;
                break;
            }
        }
        self::assertTrue($hasSlowRec);
    }

    #[Test]
    public function recommendationWhenHighExceptionRate(): void
    {
        $route = $this->createDoneRoute();
        $baseTime = new \DateTimeImmutable('2026-01-01 08:00:00');

        // 3 exception stops out of 4 → 75% > 20%
        $stop1 = $this->createDeliveredStop($route, 1, 'Addr A', $baseTime);
        $stop2 = $this->createExceptionStop($route, 2, 'Addr B');
        $stop3 = $this->createExceptionStop($route, 3, 'Addr C');
        $stop4 = $this->createExceptionStop($route, 4, 'Addr D');

        $this->mockRepoStops([$stop1, $stop2, $stop3, $stop4]);

        $result = $this->service->analyzeRouteExecution($route);

        $hasExceptionRec = false;
        foreach ($result->recommendations as $rec) {
            if (str_contains($rec, 'exception rate')) {
                $hasExceptionRec = true;
                break;
            }
        }
        self::assertTrue($hasExceptionRec);
    }

    #[Test]
    public function actualDurationCalculatedFromStartAndEnd(): void
    {
        $route = new Route('Test Route');
        $route->initializePublicId();
        $route->start(); // sets startAt

        // Use reflection to control startAt and endAt times
        $ref = new \ReflectionClass($route);

        $startProp = $ref->getProperty('startAt');
        $startProp->setValue($route, new \DateTimeImmutable('2026-01-01 08:00:00'));

        $endProp = $ref->getProperty('endAt');
        $endProp->setValue($route, new \DateTimeImmutable('2026-01-01 10:30:00'));

        $statusProp = $ref->getProperty('status');
        $statusProp->setValue($route, RouteStatus::DONE);

        $this->mockRepoStops([]);

        $result = $this->service->analyzeRouteExecution($route);

        self::assertSame(150, $result->actualDurationMinutes); // 2.5 hours = 150 min
    }

    #[Test]
    public function excludesOriginStopsFromAnalysis(): void
    {
        $route = $this->createDoneRoute();
        $baseTime = new \DateTimeImmutable('2026-01-01 08:00:00');

        $origin = new RouteStop($route, 0, 'Warehouse');
        $origin->initializePublicId();
        $origin->setOrigin(true);

        $delivery = $this->createDeliveredStop($route, 1, 'Addr A', $baseTime);

        $this->mockRepoStops([$origin, $delivery]);

        $result = $this->service->analyzeRouteExecution($route);

        self::assertCount(1, $result->stops); // Only the delivery stop
    }

    #[Test]
    public function routeNameAndDriverReturned(): void
    {
        $route = $this->createDoneRoute();
        $vehicle = new Vehicle('Furgoneta A');
        $route->setVehicle($vehicle);

        $this->mockRepoStops([]);

        $result = $this->service->analyzeRouteExecution($route);

        self::assertSame('Test Route', $result->routeName);
        self::assertSame('Furgoneta A', $result->vehicleName);
        self::assertNull($result->driverName);
    }
}
