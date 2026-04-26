<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Api\Admin\RouteListApiController;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
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
 * The controller now depends on RouteStopRepositoryInterface for all stop-level
 * aggregations (counts, next pending stop, today's delivery histogram). The EM
 * is only used for the two route-level queries (list + count). Tests mock the
 * repository directly rather than scripting DQL result rows.
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

    /**
     * Builds a chainable QueryBuilder double for the route list + count pair.
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
     * EntityManager mock scripted for the 2 route-level queries: list + count.
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
     * Repository mock with scripted returns for the 3 aggregation methods.
     *
     * @param array<string, array{total:int, delivered:int}> $counts
     * @param array<string, array{sequence:int, address:string, recipientName:?string, windowStart:?string, windowEnd:?string}> $nextStops
     * @param array<string, list<int>> $histograms
     */
    private function repo(array $counts = [], array $nextStops = [], array $histograms = []): RouteStopRepositoryInterface
    {
        $repo = $this->createMock(RouteStopRepositoryInterface::class);
        $repo->method('countsByRoutes')->willReturn($counts);
        $repo->method('findNextPendingStopsByRoutes')->willReturn($nextStops);
        $repo->method('findDeliveryHistogramsByRoutes')->willReturn($histograms);
        return $repo;
    }

    /**
     * Anonymous subclass that skips AbstractController->json()'s container needs.
     */
    private function controller(EntityManagerInterface $em, RouteStopRepositoryInterface $repo): RouteListApiController
    {
        $applier = $this->createMock(ListFilterApplier::class);

        return new class ($em, $applier, $repo) extends RouteListApiController {
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
            $this->qb([$route]),   // data list
            $this->qb('1'),        // count
        ]);
        $repo = $this->repo(counts: ['1' => ['total' => 0, 'delivered' => 0]]);

        $response = $this->controller($em, $repo)->list(new Request());

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

        $em = $this->emWithQueue([
            $this->qb([$route]),
            $this->qb('1'),
        ]);
        $repo = $this->repo(
            counts: ['1' => ['total' => 3, 'delivered' => 1]],
            nextStops: ['1' => [
                'sequence' => 2,
                'address' => 'Av. Libertador 1234',
                'recipientName' => 'Juan Pérez',
                'windowStart' => '2026-04-21T11:00:00-03:00',
                'windowEnd' => '2026-04-21T13:00:00-03:00',
            ]],
        );

        $response = $this->controller($em, $repo)->list(new Request());
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

        $em = $this->emWithQueue([
            $this->qb([$route]),
            $this->qb('1'),
        ]);
        // The repository is the binning boundary; its return value is already
        // a 24-element array. This test asserts the controller faithfully
        // forwards it into the response.
        $expected = array_fill(0, 24, 0);
        $expected[10] = 2;
        $expected[14] = 1;
        $repo = $this->repo(
            counts: ['1' => ['total' => 3, 'delivered' => 3]],
            histograms: ['1' => $expected],
        );

        $response = $this->controller($em, $repo)->list(new Request());
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
        ]);
        $repo = $this->repo(counts: ['1' => ['total' => 0, 'delivered' => 0]]);

        $response = $this->controller($em, $repo)->list(new Request());
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
        ]);
        $repo = $this->repo(counts: ['1' => ['total' => 3, 'delivered' => 3]]);

        $response = $this->controller($em, $repo)->list(new Request());
        $item = $this->decodeFirstItem($response);

        self::assertArrayHasKey('nextStop', $item);
        self::assertNull($item['nextStop']);
    }
}
