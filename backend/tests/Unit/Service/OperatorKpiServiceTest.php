<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\OperatorKpiService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OperatorKpiServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->em->method('getConnection')->willReturn($this->connection);
    }

    public function testCollectKpisReturnsExpectedKeys(): void
    {
        $this->setupQueryBuilderMock(
            scalarResults: ['0', '0', '0', '0', '0', '0'],
            arrayResults: [[]],
        );
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $service = new OperatorKpiService($this->em);
        $kpis = $service->collectKpis();

        self::assertArrayHasKey('activeRoutes', $kpis);
        self::assertArrayHasKey('deliveriesToday', $kpis);
        self::assertArrayHasKey('exceptionsToday', $kpis);
        self::assertArrayHasKey('completionRate', $kpis);
        self::assertArrayHasKey('successRate7d', $kpis);
        self::assertArrayHasKey('vehiclesWithPosition', $kpis);
        self::assertArrayHasKey('topDrivers', $kpis);
    }

    public function testSuccessRate7dCalculation(): void
    {
        // QB calls: activeRoutes(3), deliveriesToday(15), exceptionsToday(2),
        //           completionRate(array), delivered7d(80), exceptions7d(20), vehiclesWithPosition(5)
        $this->setupQueryBuilderMock(
            scalarResults: ['3', '15', '2', '80', '20', '5'],
            arrayResults: [[['routeId' => 1, 'total' => '10', 'delivered' => '8']]],
        );
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $service = new OperatorKpiService($this->em);
        $kpis = $service->collectKpis();

        // 80 delivered / (80 + 20) total = 80%
        self::assertSame(80.0, $kpis['successRate7d']);
    }

    public function testTopDriversLimitedToThree(): void
    {
        $this->setupQueryBuilderMock(
            scalarResults: ['0', '0', '0', '0', '0', '0'],
            arrayResults: [[]],
        );
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['driver_name' => 'Ana', 'deliveries' => '20', 'exceptions' => '1'],
            ['driver_name' => 'Luis', 'deliveries' => '18', 'exceptions' => '0'],
            ['driver_name' => 'Carlos', 'deliveries' => '15', 'exceptions' => '2'],
        ]);

        $service = new OperatorKpiService($this->em);
        $kpis = $service->collectKpis();

        self::assertCount(3, $kpis['topDrivers']);
        self::assertSame('Ana', $kpis['topDrivers'][0]['driver_name']);
    }

    public function testVehiclesWithPositionCount(): void
    {
        $this->setupQueryBuilderMock(
            scalarResults: ['5', '10', '1', '50', '5', '8'],
            arrayResults: [[]],
        );
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $service = new OperatorKpiService($this->em);
        $kpis = $service->collectKpis();

        self::assertSame(8, $kpis['vehiclesWithPosition']);
    }

    public function testCompletionRateCalculation(): void
    {
        $this->setupQueryBuilderMock(
            scalarResults: ['0', '0', '0', '0', '0', '0'],
            arrayResults: [[
                ['routeId' => 1, 'total' => '10', 'delivered' => '7'],
                ['routeId' => 2, 'total' => '5', 'delivered' => '5'],
            ]],
        );
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $service = new OperatorKpiService($this->em);
        $kpis = $service->collectKpis();

        // 12 delivered / 15 total = 80%
        self::assertSame(80.0, $kpis['completionRate']);
    }

    /**
     * Sets up em->createQueryBuilder() to return mocked QueryBuilders.
     *
     * Call order in collectKpis():
     *   0: countActiveRoutes       → getSingleScalarResult
     *   1: countDeliveriesToday     → getSingleScalarResult
     *   2: countExceptionsToday     → getSingleScalarResult
     *   3: calculateCompletionRate  → getArrayResult
     *   4: calculateSuccessRate7d   → getSingleScalarResult (delivered)
     *   5: calculateSuccessRate7d   → getSingleScalarResult (exceptions)
     *   6: countVehiclesWithPosition → getSingleScalarResult
     *
     * @param list<string> $scalarResults Values for getSingleScalarResult calls (6 values)
     * @param list<list<array<string, mixed>>> $arrayResults Values for getArrayResult calls (1 value)
     */
    private function setupQueryBuilderMock(array $scalarResults, array $arrayResults): void
    {
        $callIndex = 0;
        $scalarIndex = 0;
        $arrayIndex = 0;

        $this->em->method('createQueryBuilder')->willReturnCallback(
            function () use (&$callIndex, &$scalarIndex, &$arrayIndex, $scalarResults, $arrayResults): QueryBuilder {
                $currentCall = $callIndex++;

                $query = $this->createMock(Query::class);

                // Call index 3 is completionRate (uses getArrayResult)
                if ($currentCall === 3) {
                    $query->method('getArrayResult')
                        ->willReturn($arrayResults[$arrayIndex++] ?? []);
                } else {
                    $query->method('getSingleScalarResult')
                        ->willReturn($scalarResults[$scalarIndex++] ?? '0');
                }

                $qb = $this->createMock(QueryBuilder::class);
                $qb->method('select')->willReturnSelf();
                $qb->method('from')->willReturnSelf();
                $qb->method('join')->willReturnSelf();
                $qb->method('where')->willReturnSelf();
                $qb->method('andWhere')->willReturnSelf();
                $qb->method('setParameter')->willReturnSelf();
                $qb->method('groupBy')->willReturnSelf();
                $qb->method('getQuery')->willReturn($query);

                return $qb;
            },
        );
    }
}
