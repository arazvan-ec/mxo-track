<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\RoutePerformanceMetric;
use App\Repository\RoutePerformanceMetricRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RoutePerformanceMetricRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private RoutePerformanceMetricRepository $repository;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        $classMetadata = new ClassMetadata(RoutePerformanceMetric::class);

        $this->em->method('getClassMetadata')
            ->with(RoutePerformanceMetric::class)
            ->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->with(RoutePerformanceMetric::class)
            ->willReturn($this->em);

        $this->repository = new RoutePerformanceMetricRepository($registry);
    }

    public function testGetMetricsByOptimizerReturnsExpectedKeys(): void
    {
        $expectedRow = [
            'optimizer_used' => 'vroom',
            'avg_distance_km' => '42.50',
            'avg_duration_min' => '65',
            'route_count' => 10,
            'avg_success_rate' => '95.5',
        ];

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$expectedRow]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->getMetricsByOptimizer(new \DateTimeImmutable('-30 days'));

        self::assertIsArray($result);
        self::assertCount(1, $result);

        $row = $result[0];
        self::assertArrayHasKey('optimizer_used', $row);
        self::assertArrayHasKey('avg_distance_km', $row);
        self::assertArrayHasKey('avg_duration_min', $row);
        self::assertArrayHasKey('route_count', $row);
        self::assertArrayHasKey('avg_success_rate', $row);
    }

    public function testGetMetricsByOptimizerReturnsEmptyArrayWhenNoData(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->getMetricsByOptimizer(new \DateTimeImmutable('-30 days'));

        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    public function testGetMetricsByOptimizerGroupsMultipleOptimizers(): void
    {
        $rows = [
            [
                'optimizer_used' => 'vroom',
                'avg_distance_km' => '42.50',
                'avg_duration_min' => '65',
                'route_count' => 10,
                'avg_success_rate' => '95.5',
            ],
            [
                'optimizer_used' => 'greedy',
                'avg_distance_km' => '55.30',
                'avg_duration_min' => '80',
                'route_count' => 5,
                'avg_success_rate' => '88.2',
            ],
        ];

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')
            ->willReturn($qb);

        $result = $this->repository->getMetricsByOptimizer(new \DateTimeImmutable('-30 days'));

        self::assertCount(2, $result);
        self::assertSame('vroom', $result[0]['optimizer_used']);
        self::assertSame('greedy', $result[1]['optimizer_used']);
    }
}
