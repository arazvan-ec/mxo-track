<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Api\Admin\RouteListApiController;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\User;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Service\Admin\ListFilterApplier;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for the admin routes list endpoint's enriched DTO projection.
 *
 * The controller uses EntityManagerInterface::createQueryBuilder() four times:
 *   1. Data list (Route + joins)
 *   2. Count
 *   3. stopCounts aggregation
 *   4. nextStops (min PENDING sequence per route)  [NEW]
 *   5. nextStops hydration (full RouteStop by (route, sequence))  [NEW]
 *   6. deliveredAt histogram source (today's deliveries per route) [NEW]
 *
 * Call order is deterministic inside list(); the test queues return values
 * in that order.
 */
#[CoversClass(RouteListApiController::class)]
final class RouteListApiControllerTest extends TestCase
{
    /**
     * Default fixture: a single active route with driver and vehicle references
     * but no stops. Tests augment this as needed.
     *
     * @param array<string, mixed> $overrides
     */
    private function makeRoute(array $overrides = []): Route
    {
        $route = new Route($overrides['name'] ?? 'Ruta Test');
        $route->initializePublicId();

        $this->setId($route, $overrides['id'] ?? '1');

        if (($overrides['withDriver'] ?? true) === true) {
            $driver = new User('driver@test.com');
            $driver->initializePublicId();
            $this->setId($driver, '10');
            $route->setDriver($driver);
        }

        if (isset($overrides['totalDistanceKm'])) {
            $route->setTotalDistanceKm($overrides['totalDistanceKm']);
        }
        if (isset($overrides['estimatedDurationMinutes'])) {
            $route->setEstimatedDurationMinutes($overrides['estimatedDurationMinutes']);
        }
        if (isset($overrides['totalWeightKg'])) {
            $route->setTotalWeightKg($overrides['totalWeightKg']);
        }
        if (isset($overrides['totalParcels'])) {
            $route->setTotalParcels($overrides['totalParcels']);
        }

        $route->setStatus(RouteStatus::ACTIVE);

        return $route;
    }

    private function setId(object $entity, string $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }

    private function makeStop(Route $route, int $seq, string $address, RouteStopStatus $status = RouteStopStatus::PENDING): RouteStop
    {
        $stop = new RouteStop($route, $seq, $address);
        $stop->initializePublicId();
        $this->setId($stop, (string) (1000 + $seq));
        if ($status === RouteStopStatus::DELIVERED) {
            $stop->markDelivered();
        }
        return $stop;
    }

    /**
     * Builds a chainable QueryBuilder double that returns self for fluent calls
     * and a Query double whose getResult()/getSingleScalarResult() yield the
     * provided values.
     *
     * @param list<mixed>|scalar|null $result
     */
    private function qb(mixed $result): QueryBuilder
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult', 'getSingleScalarResult', 'getOneOrNullResult', 'getArrayResult', 'getScalarResult'])
            ->getMock();

        if (is_array($result)) {
            $query->method('getResult')->willReturn($result);
            $query->method('getArrayResult')->willReturn($result);
        } else {
            $query->method('getSingleScalarResult')->willReturn($result);
        }

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'select', 'from', 'leftJoin', 'innerJoin', 'where', 'andWhere',
                'orWhere', 'orderBy', 'addOrderBy', 'groupBy', 'setFirstResult',
                'setMaxResults', 'setParameter', 'setParameters', 'getQuery',
                'expr',
            ])
            ->getMock();

        foreach (['select', 'from', 'leftJoin', 'innerJoin', 'where', 'andWhere',
                  'orWhere', 'orderBy', 'addOrderBy', 'groupBy', 'setFirstResult',
                  'setMaxResults', 'setParameter', 'setParameters'] as $method) {
            $qb->method($method)->willReturnSelf();
        }
        $qb->method('expr')->willReturn(new Expr());
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    /**
     * Script an EntityManager whose createQueryBuilder() returns a queued
     * series of QueryBuilder doubles in order.
     *
     * @param list<QueryBuilder> $builders
     */
    private function emWithQueue(array $builders): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')
            ->willReturnCallback(function () use (&$builders): QueryBuilder {
                if (count($builders) === 0) {
                    throw new \RuntimeException('No more QueryBuilder doubles queued');
                }
                return array_shift($builders);
            });
        return $em;
    }

    /**
     * Anonymous subclass that skips AbstractController->json()'s container needs.
     */
    private function controller(EntityManagerInterface $em): RouteListApiController
    {
        $applier = $this->createMock(ListFilterApplier::class);

        return new class ($em, $applier) extends RouteListApiController {
            protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
            {
                return new JsonResponse($data, $status, $headers);
            }
        };
    }

    private function decodeFirstItem(JsonResponse $response): array
    {
        $data = json_decode((string) $response->getContent(), true);
        self::assertIsArray($data);
        self::assertArrayHasKey('items', $data);
        self::assertCount(1, $data['items']);
        return $data['items'][0];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Tests
    // ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function testListIncludesOptimizationMetrics(): void
    {
        $route = $this->makeRoute([
            'id' => '1',
            'totalDistanceKm' => 87.4,
            'estimatedDurationMinutes' => 260,
            'totalWeightKg' => 1243.5,
            'totalParcels' => 23,
        ]);

        $em = $this->emWithQueue([
            $this->qb([$route]),                                  // data list
            $this->qb('1'),                                       // count
            $this->qb([['routeId' => '1', 'total' => '0', 'delivered' => '0']]), // stopCounts
            $this->qb([]),                                        // nextStops min
            $this->qb([]),                                        // histogram source
        ]);

        $response = $this->controller($em)->list(new Request());

        $item = $this->decodeFirstItem($response);
        self::assertSame(87.4, $item['totalDistanceKm']);
        self::assertSame(260, $item['estimatedDurationMinutes']);
        self::assertSame(1243.5, $item['totalWeightKg']);
        self::assertSame(23, $item['totalParcels']);
    }

    #[Test]
    public function testNextStopReflectsFirstPendingBySequence(): void
    {
        $route = $this->makeRoute(['id' => '1']);

        $stopSeq2 = $this->makeStop($route, 2, 'Av. Libertador 1234', RouteStopStatus::PENDING);
        $stopSeq2->setRecipientName('Juan Pérez');
        $stopSeq2->setDeliveryWindowStart(new DateTimeImmutable('2026-04-21T11:00:00-03:00'));
        $stopSeq2->setDeliveryWindowEnd(new DateTimeImmutable('2026-04-21T13:00:00-03:00'));

        $em = $this->emWithQueue([
            $this->qb([$route]),
            $this->qb('1'),
            $this->qb([['routeId' => '1', 'total' => '3', 'delivered' => '1']]),
            // min-pending-sequence per route: route 1 -> sequence 2
            $this->qb([['routeId' => '1', 'minSeq' => '2']]),
            // hydration of those (route, sequence) pairs: return the full RouteStop
            $this->qb([$stopSeq2]),
            // histogram source
            $this->qb([]),
        ]);

        $response = $this->controller($em)->list(new Request());
        $item = $this->decodeFirstItem($response);

        self::assertIsArray($item['nextStop']);
        self::assertSame(2, $item['nextStop']['sequence']);
        self::assertSame('Av. Libertador 1234', $item['nextStop']['address']);
        self::assertSame('Juan Pérez', $item['nextStop']['recipientName']);
        self::assertSame('2026-04-21T11:00:00-03:00', $item['nextStop']['windowStart']);
        self::assertSame('2026-04-21T13:00:00-03:00', $item['nextStop']['windowEnd']);
    }

    #[Test]
    public function testDeliveryHistogramBinsOnlyTodayAndByHour(): void
    {
        $route = $this->makeRoute(['id' => '1']);

        $today = new DateTimeImmutable('today');
        $deliveredAt1 = $today->setTime(10, 15);
        $deliveredAt2 = $today->setTime(10, 45);
        $deliveredAt3 = $today->setTime(14, 0);

        $em = $this->emWithQueue([
            $this->qb([$route]),
            $this->qb('1'),
            $this->qb([['routeId' => '1', 'total' => '3', 'delivered' => '3']]),
            $this->qb([]), // no pending stops
            // Histogram source: rows with routeId + deliveredAt; NB: controller
            // applies a `DATE(s.deliveredAt) = CURRENT_DATE` filter in DQL, so
            // by the time the result reaches the binning code "yesterday" rows
            // never appear here. The binning logic itself only has to cope
            // with today-only timestamps.
            $this->qb([
                ['routeId' => '1', 'deliveredAt' => $deliveredAt1],
                ['routeId' => '1', 'deliveredAt' => $deliveredAt2],
                ['routeId' => '1', 'deliveredAt' => $deliveredAt3],
            ]),
        ]);

        $response = $this->controller($em)->list(new Request());
        $item = $this->decodeFirstItem($response);

        self::assertIsArray($item['deliveryHistogram']);
        self::assertCount(24, $item['deliveryHistogram']);
        self::assertSame(2, $item['deliveryHistogram'][10], 'two deliveries at 10:xx');
        self::assertSame(1, $item['deliveryHistogram'][14], 'one delivery at 14:xx');
        self::assertSame(0, $item['deliveryHistogram'][11]);
        self::assertSame(0, $item['deliveryHistogram'][0]);
        self::assertSame(0, $item['deliveryHistogram'][23]);
    }

    #[Test]
    public function testNullFieldsPropagateAsNull(): void
    {
        // Route with none of the 4 scalars set
        $route = $this->makeRoute(['id' => '1']);

        $em = $this->emWithQueue([
            $this->qb([$route]),
            $this->qb('1'),
            $this->qb([['routeId' => '1', 'total' => '0', 'delivered' => '0']]),
            $this->qb([]),
            $this->qb([]),
        ]);

        $response = $this->controller($em)->list(new Request());
        $item = $this->decodeFirstItem($response);

        self::assertArrayHasKey('totalDistanceKm', $item);
        self::assertArrayHasKey('estimatedDurationMinutes', $item);
        self::assertArrayHasKey('totalWeightKg', $item);
        self::assertArrayHasKey('totalParcels', $item);
        self::assertNull($item['totalDistanceKm']);
        self::assertNull($item['estimatedDurationMinutes']);
        self::assertNull($item['totalWeightKg']);
        self::assertNull($item['totalParcels']);
    }

    #[Test]
    public function testNextStopNullWhenNoPendingStops(): void
    {
        $route = $this->makeRoute(['id' => '1']);

        $em = $this->emWithQueue([
            $this->qb([$route]),
            $this->qb('1'),
            $this->qb([['routeId' => '1', 'total' => '3', 'delivered' => '3']]),
            $this->qb([]), // no min-pending rows
            $this->qb([]), // histogram
        ]);

        $response = $this->controller($em)->list(new Request());
        $item = $this->decodeFirstItem($response);

        self::assertArrayHasKey('nextStop', $item);
        self::assertNull($item['nextStop']);
    }
}
