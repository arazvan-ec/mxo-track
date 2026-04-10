<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\OptimizationAnalyticsController;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteEvent;
use App\Entity\AddressRisk;
use App\Enum\RouteEventType;
use App\Repository\AddressRiskRepository;
use App\Repository\RoutePerformanceMetricRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptimizationAnalyticsController::class)]
final class OptimizationAnalyticsControllerTest extends TestCase
{
    #[Test]
    public function metricsReturnsOptimizerPerformanceStats(): void
    {
        $metricsRepo = $this->createMock(RoutePerformanceMetricRepository::class);
        $metricsRepo->method('getMetricsByOptimizer')->willReturn([
            [
                'optimizer_used' => 'vroom',
                'avg_distance_km' => '42.50',
                'avg_duration_min' => '110.00',
                'route_count' => 25,
                'avg_success_rate' => '93.20',
            ],
        ]);

        $addressRiskRepo = $this->createMock(AddressRiskRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $controller = new OptimizationAnalyticsController($metricsRepo, $addressRiskRepo, $em);
        $response = $controller->metrics();

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        self::assertCount(1, $data);
        self::assertSame('vroom', $data[0]['optimizer_name']);
        self::assertSame('42.50', $data[0]['avg_distance_km']);
        self::assertSame('110.00', $data[0]['avg_duration_min']);
        self::assertSame(25, $data[0]['route_count']);
        self::assertSame('93.20', $data[0]['avg_success_rate']);
    }

    #[Test]
    public function metricsReturnsEmptyArrayWhenNoData(): void
    {
        $metricsRepo = $this->createMock(RoutePerformanceMetricRepository::class);
        $metricsRepo->method('getMetricsByOptimizer')->willReturn([]);

        $addressRiskRepo = $this->createMock(AddressRiskRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $controller = new OptimizationAnalyticsController($metricsRepo, $addressRiskRepo, $em);
        $response = $controller->metrics();

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        self::assertSame([], $data);
    }

    #[Test]
    public function addressRisksReturnsTopRiskyAddresses(): void
    {
        $risk1 = new AddressRisk('hash1', 'Av. Reforma 123, CDMX');
        $risk1->setTotalDeliveries(50);
        $risk1->setExceptionCount(20);
        $risk1->setExceptionRate(0.4);

        $risk2 = new AddressRisk('hash2', 'Calle 5 de Mayo 456, Puebla');
        $risk2->setTotalDeliveries(10);
        $risk2->setExceptionCount(2);
        $risk2->setExceptionRate(0.2);

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$risk1, $risk2]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $addressRiskRepo = $this->createMock(AddressRiskRepository::class);
        $addressRiskRepo->method('createQueryBuilder')->willReturn($qb);

        $metricsRepo = $this->createMock(RoutePerformanceMetricRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $controller = new OptimizationAnalyticsController($metricsRepo, $addressRiskRepo, $em);
        $response = $controller->addressRisks();

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        self::assertCount(2, $data);

        self::assertSame('Av. Reforma 123, CDMX', $data[0]['address']);
        self::assertSame(50, $data[0]['total_deliveries']);
        self::assertSame(20, $data[0]['exception_count']);
        self::assertSame(0.4, $data[0]['exception_rate']);
        self::assertTrue($data[0]['is_high_risk']);

        self::assertSame('Calle 5 de Mayo 456, Puebla', $data[1]['address']);
        self::assertSame(10, $data[1]['total_deliveries']);
        self::assertSame(2, $data[1]['exception_count']);
        self::assertSame(0.2, $data[1]['exception_rate']);
        self::assertFalse($data[1]['is_high_risk']);
    }

    #[Test]
    public function reoptHistoryReturnsRecentReoptimizationEvents(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('getPublicIdString')->willReturn('01JTEST000000000000000001');

        $event = new RouteEvent(
            route: $route,
            eventType: RouteEventType::REOPTIMIZED,
            actorType: 'system',
            payload: ['improvement_percent' => 12.5],
            occurredAt: new \DateTimeImmutable('2026-04-09T14:30:00+00:00'),
        );

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$event]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $metricsRepo = $this->createMock(RoutePerformanceMetricRepository::class);
        $addressRiskRepo = $this->createMock(AddressRiskRepository::class);

        $controller = new OptimizationAnalyticsController($metricsRepo, $addressRiskRepo, $em);
        $response = $controller->reoptHistory();

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        self::assertCount(1, $data);
        self::assertSame('01JTEST000000000000000001', $data[0]['route_public_id']);
        self::assertSame('system', $data[0]['trigger']);
        self::assertSame('2026-04-09T14:30:00+00:00', $data[0]['occurred_at']);
    }

    #[Test]
    public function reoptHistoryUsesTriggerFromPayloadWhenPresent(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('getPublicIdString')->willReturn('01JTEST000000000000000002');

        $event = new RouteEvent(
            route: $route,
            eventType: RouteEventType::REOPTIMIZED,
            actorType: 'system',
            payload: ['trigger' => 'delay_detected', 'improvement_percent' => 8.0],
            occurredAt: new \DateTimeImmutable('2026-04-08T10:00:00+00:00'),
        );

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$event]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $metricsRepo = $this->createMock(RoutePerformanceMetricRepository::class);
        $addressRiskRepo = $this->createMock(AddressRiskRepository::class);

        $controller = new OptimizationAnalyticsController($metricsRepo, $addressRiskRepo, $em);
        $response = $controller->reoptHistory();

        $data = json_decode($response->getContent(), true);

        self::assertSame('delay_detected', $data[0]['trigger']);
    }
}
