# Plan: Unified Route View Layer (Spec v3)

**Goal:** Persist optimization results in RouteSnapshot, expose via RouteViewService, render with shared Twig components + JS class. Immutable plan + additive progress pattern.

**Architecture:**
```
Route + RouteStop (immutable plan)
    ↓ on build/optimize
RouteSnapshot (persisted optimization + progress)
    ↓ on view request
RouteViewService → MapViewData DTOs
    ↓ on render
Twig Components + MxoRouteMap JS
    ↓ on progress event
Mercure → re-render
```

**Tech:** Symfony 7.4, Doctrine ORM 3.x, PHP 8.4, PHPUnit 10/11, Leaflet 1.9.4, Alpine.js 3.14.8

---

## Phase 1: Domain — RouteSnapshot Entity + Manager

### Task 1: Create RouteSnapshot entity

**Files:**
- NEW: `backend/src/Entity/RouteSnapshot.php`
- NEW: `backend/src/Repository/RouteSnapshotRepository.php`
- NEW: `backend/tests/Unit/Entity/RouteSnapshotTest.php`

**Test first** (`tests/Unit/Entity/RouteSnapshotTest.php`):
```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Route;
use App\Entity\RouteSnapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteSnapshot::class)]
final class RouteSnapshotTest extends TestCase
{
    #[Test]
    public function snapshotLinksToRoute(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        self::assertSame($route, $snapshot->getRoute());
        self::assertNotNull($snapshot->getCreatedAt());
        self::assertNotNull($snapshot->getUpdatedAt());
    }

    #[Test]
    public function polylineFieldsWorkCorrectly(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        self::assertNull($snapshot->getPolyline());

        $snapshot->setPolyline('encoded_polyline_here');
        self::assertSame('encoded_polyline_here', $snapshot->getPolyline());

        $snapshot->setOriginalPolyline('original_here');
        self::assertSame('original_here', $snapshot->getOriginalPolyline());

        $snapshot->setActualPolyline('actual_here');
        self::assertSame('actual_here', $snapshot->getActualPolyline());
    }

    #[Test]
    public function optimizationMetricsWorkCorrectly(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $snapshot->setDistanceBeforeKm(50.0);
        $snapshot->setDistanceAfterKm(35.0);
        $snapshot->setSavingsPercent(30.0);

        self::assertSame(50.0, $snapshot->getDistanceBeforeKm());
        self::assertSame(35.0, $snapshot->getDistanceAfterKm());
        self::assertSame(30.0, $snapshot->getSavingsPercent());
    }

    #[Test]
    public function timingFieldsWorkCorrectly(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $snapshot->setDrivingTimeMinutes(60);
        $snapshot->setDeliveryTimeMinutes(30);
        $snapshot->setTotalTimeMinutes(90);

        self::assertSame(60, $snapshot->getDrivingTimeMinutes());
        self::assertSame(30, $snapshot->getDeliveryTimeMinutes());
        self::assertSame(90, $snapshot->getTotalTimeMinutes());
    }

    #[Test]
    public function stopStatesAreNullByDefault(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        self::assertNull($snapshot->getStopStates());
        self::assertNull($snapshot->getOriginalStopOrder());
    }

    #[Test]
    public function stopStatesCanBeSetAndRetrieved(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $states = [
            ['publicId' => 'abc', 'sequence' => 1, 'status' => 'PENDING'],
            ['publicId' => 'def', 'sequence' => 2, 'status' => 'DELIVERED', 'deliveredAt' => '2026-03-13T10:00:00+00:00'],
        ];
        $snapshot->setStopStates($states);

        self::assertSame($states, $snapshot->getStopStates());
    }

    #[Test]
    public function capacityValidationCanBeSetAndRetrieved(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $validation = [
            'valid' => true,
            'totalWeightKg' => 120.5,
            'weightUtilization' => 60.25,
        ];
        $snapshot->setCapacityValidation($validation);

        self::assertSame($validation, $snapshot->getCapacityValidation());
    }

    #[Test]
    public function touchUpdatesTimestamp(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $originalUpdatedAt = $snapshot->getUpdatedAt();

        // Force a microsecond delay
        usleep(1000);
        $snapshot->touch();

        self::assertGreaterThan($originalUpdatedAt, $snapshot->getUpdatedAt());
    }
}
```

**Verify test fails** (entity class doesn't exist yet):
```bash
cd backend && php vendor/bin/phpunit tests/Unit/Entity/RouteSnapshotTest.php
# Expected: Error — class RouteSnapshot not found
```

**Implement** (`src/Entity/RouteSnapshot.php`):
```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RouteSnapshotRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouteSnapshotRepository::class)]
#[ORM\Table(name: 'route_snapshot')]
#[ORM\Index(name: 'idx_route_snapshot_route', columns: ['route_id'])]
class RouteSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    // ── Polylines (encoded Google format from OSRM) ──

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $polyline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $originalPolyline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $actualPolyline = null;

    // ── Optimization metrics ──

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $distanceBeforeKm = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $distanceAfterKm = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 1, nullable: true)]
    private ?string $savingsPercent = null;

    // ── Timing ──

    #[ORM\Column(nullable: true)]
    private ?int $drivingTimeMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $deliveryTimeMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalTimeMinutes = null;

    // ── Stop snapshots ──

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $originalStopOrder = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $stopStates = null;

    // ── Capacity ──

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $capacityValidation = null;

    // ── Timestamps ──

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(Route $route)
    {
        $this->route = $route;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getRoute(): Route { return $this->route; }

    public function getPolyline(): ?string { return $this->polyline; }
    public function setPolyline(?string $polyline): void { $this->polyline = $polyline; }

    public function getOriginalPolyline(): ?string { return $this->originalPolyline; }
    public function setOriginalPolyline(?string $v): void { $this->originalPolyline = $v; }

    public function getActualPolyline(): ?string { return $this->actualPolyline; }
    public function setActualPolyline(?string $v): void { $this->actualPolyline = $v; }

    public function getDistanceBeforeKm(): ?float { return $this->distanceBeforeKm !== null ? (float) $this->distanceBeforeKm : null; }
    public function setDistanceBeforeKm(?float $v): void { $this->distanceBeforeKm = $v !== null ? (string) $v : null; }

    public function getDistanceAfterKm(): ?float { return $this->distanceAfterKm !== null ? (float) $this->distanceAfterKm : null; }
    public function setDistanceAfterKm(?float $v): void { $this->distanceAfterKm = $v !== null ? (string) $v : null; }

    public function getSavingsPercent(): ?float { return $this->savingsPercent !== null ? (float) $this->savingsPercent : null; }
    public function setSavingsPercent(?float $v): void { $this->savingsPercent = $v !== null ? (string) $v : null; }

    public function getDrivingTimeMinutes(): ?int { return $this->drivingTimeMinutes; }
    public function setDrivingTimeMinutes(?int $v): void { $this->drivingTimeMinutes = $v; }

    public function getDeliveryTimeMinutes(): ?int { return $this->deliveryTimeMinutes; }
    public function setDeliveryTimeMinutes(?int $v): void { $this->deliveryTimeMinutes = $v; }

    public function getTotalTimeMinutes(): ?int { return $this->totalTimeMinutes; }
    public function setTotalTimeMinutes(?int $v): void { $this->totalTimeMinutes = $v; }

    public function getOriginalStopOrder(): ?array { return $this->originalStopOrder; }
    public function setOriginalStopOrder(?array $v): void { $this->originalStopOrder = $v; }

    public function getStopStates(): ?array { return $this->stopStates; }
    public function setStopStates(?array $v): void { $this->stopStates = $v; $this->updatedAt = new DateTimeImmutable(); }

    public function getCapacityValidation(): ?array { return $this->capacityValidation; }
    public function setCapacityValidation(?array $v): void { $this->capacityValidation = $v; }

    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }

    public function touch(): void { $this->updatedAt = new DateTimeImmutable(); }
}
```

**Implement** (`src/Repository/RouteSnapshotRepository.php`):
```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use App\Entity\RouteSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteSnapshot>
 */
class RouteSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteSnapshot::class);
    }

    public function findByRoute(Route $route): ?RouteSnapshot
    {
        return $this->findOneBy(['route' => $route]);
    }
}
```

**Verify test passes:**
```bash
cd backend && php vendor/bin/phpunit tests/Unit/Entity/RouteSnapshotTest.php
# Expected: OK (8 tests)
```

**Generate migration:**
```bash
cd backend && php bin/console doctrine:migrations:diff --no-interaction
# Expected: new migration file Version2026XXXX
```

**Commit:**
```bash
git add backend/src/Entity/RouteSnapshot.php backend/src/Repository/RouteSnapshotRepository.php backend/tests/Unit/Entity/RouteSnapshotTest.php backend/migrations/
git commit -m "feat: add RouteSnapshot entity with migration"
```

---

### Task 2: Create RouteSnapshotManager service

**Files:**
- NEW: `backend/src/Service/RouteSnapshotManager.php`
- NEW: `backend/tests/Unit/Service/RouteSnapshotManagerTest.php`

**Test first** (`tests/Unit/Service/RouteSnapshotManagerTest.php`):
```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Route;
use App\Entity\RouteSnapshot;
use App\Entity\RouteStop;
use App\Enum\RouteStopStatus;
use App\Repository\RouteSnapshotRepository;
use App\Routing\Coordinate;
use App\Routing\MultiWaypointRouteResult;
use App\Routing\RoutingEngineInterface;
use App\Service\RouteCapacityValidator;
use App\Service\RouteSnapshotManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteSnapshotManager::class)]
final class RouteSnapshotManagerTest extends TestCase
{
    private RouteSnapshotManager $manager;
    private EntityManagerInterface $em;
    private RoutingEngineInterface $routingEngine;
    private RouteCapacityValidator $capacityValidator;
    private RouteSnapshotRepository $snapshotRepo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->routingEngine = $this->createMock(RoutingEngineInterface::class);
        $this->capacityValidator = $this->createMock(RouteCapacityValidator::class);
        $this->snapshotRepo = $this->createMock(RouteSnapshotRepository::class);

        $this->manager = new RouteSnapshotManager(
            $this->em,
            $this->routingEngine,
            $this->capacityValidator,
            $this->snapshotRepo,
        );
    }

    #[Test]
    public function createSnapshotPersistsNewSnapshot(): void
    {
        $route = new Route('Test Route');

        // Mock: no existing snapshot
        $this->snapshotRepo->method('findByRoute')->willReturn(null);

        // Mock: no stops (empty route for simplicity)
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn([]);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        // Expect persist
        $this->em->expects(self::once())->method('persist')
            ->with(self::isInstanceOf(RouteSnapshot::class));

        $snapshot = $this->manager->createSnapshot($route);

        self::assertInstanceOf(RouteSnapshot::class, $snapshot);
        self::assertSame($route, $snapshot->getRoute());
    }

    #[Test]
    public function createSnapshotWithMetricsSetsValues(): void
    {
        $route = new Route('Test Route');

        $this->snapshotRepo->method('findByRoute')->willReturn(null);

        // Mock stops query
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn([]);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $this->em->method('persist');

        $snapshot = $this->manager->createSnapshot(
            $route,
            distanceBeforeKm: 50.0,
            distanceAfterKm: 35.0,
        );

        self::assertSame(50.0, $snapshot->getDistanceBeforeKm());
        self::assertSame(35.0, $snapshot->getDistanceAfterKm());
        self::assertSame(30.0, $snapshot->getSavingsPercent());
    }

    #[Test]
    public function createSnapshotReusesExistingSnapshot(): void
    {
        $route = new Route('Test Route');
        $existing = new RouteSnapshot($route);
        $existing->setPolyline('old_polyline');

        $this->snapshotRepo->method('findByRoute')->willReturn($existing);

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn([]);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        // Should NOT persist (reuse existing)
        $this->em->expects(self::never())->method('persist');

        $snapshot = $this->manager->createSnapshot($route);

        self::assertSame($existing, $snapshot);
    }

    #[Test]
    public function updateStopStatesBuildStatesFromStops(): void
    {
        $route = new Route('Test Route');
        $snapshot = new RouteSnapshot($route);

        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        // Create mock stops
        $stop1 = new RouteStop($route, 0, 'Origin');
        $stop1->setOrigin(true);
        $stop1->setLatitude(40.0);
        $stop1->setLongitude(-3.0);

        $stop2 = new RouteStop($route, 1, 'Stop A');
        $stop2->setLatitude(40.1);
        $stop2->setLongitude(-3.1);

        $stop3 = new RouteStop($route, 2, 'Stop B');
        $stop3->setLatitude(40.2);
        $stop3->setLongitude(-3.2);
        $stop3->markDelivered();

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn([$stop1, $stop2, $stop3]);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $this->em->method('createQueryBuilder')->willReturn($qb);

        $result = $this->manager->updateStopStates($route);

        self::assertSame($snapshot, $result);
        $states = $result->getStopStates();
        self::assertNotNull($states);
        self::assertCount(3, $states);
        self::assertSame('PENDING', $states[1]['status']);
        self::assertSame('DELIVERED', $states[2]['status']);
        self::assertNotNull($states[2]['deliveredAt']);
    }
}
```

**Verify test fails:**
```bash
cd backend && php vendor/bin/phpunit tests/Unit/Service/RouteSnapshotManagerTest.php
# Expected: Error — class RouteSnapshotManager not found
```

**Implement** (`src/Service/RouteSnapshotManager.php`):
```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteSnapshot;
use App\Entity\RouteStop;
use App\Repository\RouteSnapshotRepository;
use App\Routing\Coordinate;
use App\Routing\RoutingEngineInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Manages RouteSnapshot lifecycle: creates after build/optimize,
 * updates stop states on progress events, refreshes polylines.
 */
final class RouteSnapshotManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RoutingEngineInterface $routingEngine,
        private readonly RouteCapacityValidator $capacityValidator,
        private readonly RouteSnapshotRepository $snapshotRepo,
    ) {}

    /**
     * Creates or updates the full snapshot after build/optimize.
     * Calls OSRM once for the polyline.
     */
    public function createSnapshot(
        Route $route,
        ?float $distanceBeforeKm = null,
        ?float $distanceAfterKm = null,
        ?array $originalStopOrder = null,
    ): RouteSnapshot {
        $snapshot = $this->snapshotRepo->findByRoute($route);
        $isNew = false;

        if ($snapshot === null) {
            $snapshot = new RouteSnapshot($route);
            $isNew = true;
        }

        $stops = $this->getStopsForRoute($route);

        // Polyline from OSRM
        $waypoints = [];
        foreach ($stops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $waypoints[] = new Coordinate($stop->getLatitude(), $stop->getLongitude());
            }
        }

        if (\count($waypoints) >= 2) {
            $routeResult = $this->routingEngine->routeWithWaypoints($waypoints);
            $snapshot->setPolyline($routeResult->geometry);

            if ($distanceAfterKm === null) {
                $distanceAfterKm = $routeResult->totalDistanceKm;
            }
        }

        // Metrics
        if ($distanceBeforeKm !== null) {
            $snapshot->setDistanceBeforeKm($distanceBeforeKm);
        }
        if ($distanceAfterKm !== null) {
            $snapshot->setDistanceAfterKm($distanceAfterKm);
        }
        if ($distanceBeforeKm !== null && $distanceBeforeKm > 0 && $distanceAfterKm !== null) {
            $savings = round((1 - $distanceAfterKm / $distanceBeforeKm) * 100, 1);
            $snapshot->setSavingsPercent($savings);
        }

        // Timing from OSRM
        if (\count($waypoints) >= 2) {
            $deliveryCount = 0;
            foreach ($stops as $stop) {
                if (!$stop->isOrigin()) {
                    $deliveryCount++;
                }
            }

            $routeResult = $this->routingEngine->routeWithWaypoints($waypoints);
            $drivingMinutes = (int) round($routeResult->totalDurationSeconds / 60.0);
            $deliveryMinutes = $deliveryCount * 5; // 5 min per stop default
            $snapshot->setDrivingTimeMinutes($drivingMinutes);
            $snapshot->setDeliveryTimeMinutes($deliveryMinutes);
            $snapshot->setTotalTimeMinutes($drivingMinutes + $deliveryMinutes);
        }

        // Original stop order
        if ($originalStopOrder !== null) {
            $snapshot->setOriginalStopOrder($originalStopOrder);
        }

        // Stop states (initial)
        $this->buildStopStates($snapshot, $stops);

        // Capacity validation
        $validation = $this->capacityValidator->validate($route);
        $snapshot->setCapacityValidation($validation);

        $snapshot->touch();

        if ($isNew) {
            $this->em->persist($snapshot);
        }

        return $snapshot;
    }

    /**
     * Updates only the stopStates from current RouteStop entities.
     * Fast path — no OSRM calls.
     */
    public function updateStopStates(Route $route): RouteSnapshot
    {
        $snapshot = $this->snapshotRepo->findByRoute($route);

        if ($snapshot === null) {
            $snapshot = new RouteSnapshot($route);
            $this->em->persist($snapshot);
        }

        $stops = $this->getStopsForRoute($route);
        $this->buildStopStates($snapshot, $stops);

        return $snapshot;
    }

    /**
     * Refreshes the polyline after stop reordering.
     */
    public function refreshPolyline(Route $route): RouteSnapshot
    {
        $snapshot = $this->snapshotRepo->findByRoute($route);

        if ($snapshot === null) {
            return $this->createSnapshot($route);
        }

        $stops = $this->getStopsForRoute($route);
        $waypoints = [];
        foreach ($stops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $waypoints[] = new Coordinate($stop->getLatitude(), $stop->getLongitude());
            }
        }

        if (\count($waypoints) >= 2) {
            $routeResult = $this->routingEngine->routeWithWaypoints($waypoints);
            $snapshot->setPolyline($routeResult->geometry);
            $snapshot->setDistanceAfterKm($routeResult->totalDistanceKm);

            $drivingMinutes = (int) round($routeResult->totalDurationSeconds / 60.0);
            $snapshot->setDrivingTimeMinutes($drivingMinutes);

            $deliveryCount = 0;
            foreach ($stops as $stop) {
                if (!$stop->isOrigin()) {
                    $deliveryCount++;
                }
            }
            $deliveryMinutes = $deliveryCount * 5;
            $snapshot->setDeliveryTimeMinutes($deliveryMinutes);
            $snapshot->setTotalTimeMinutes($drivingMinutes + $deliveryMinutes);
        }

        $snapshot->touch();

        return $snapshot;
    }

    /**
     * @param list<RouteStop> $stops
     */
    private function buildStopStates(RouteSnapshot $snapshot, array $stops): void
    {
        $states = [];
        foreach ($stops as $stop) {
            $state = [
                'publicId' => $stop->getPublicId()?->toRfc4122(),
                'sequence' => $stop->getSequence(),
                'address' => $stop->getAddress(),
                'recipientName' => $stop->getRecipientName(),
                'lat' => $stop->getLatitude(),
                'lng' => $stop->getLongitude(),
                'isOrigin' => $stop->isOrigin(),
                'status' => $stop->getStatus()->value,
            ];

            if ($stop->getDeliveredAt() !== null) {
                $state['deliveredAt'] = $stop->getDeliveredAt()->format(\DateTimeInterface::ATOM);
            }

            if ($stop->getExceptionCode() !== null) {
                $state['exceptionCode'] = $stop->getExceptionCode()->value;
                $state['exceptionNotes'] = $stop->getExceptionNotes();
            }

            $states[] = $state;
        }

        $snapshot->setStopStates($states);
    }

    /**
     * @return list<RouteStop>
     */
    private function getStopsForRoute(Route $route): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
```

**Verify test passes:**
```bash
cd backend && php vendor/bin/phpunit tests/Unit/Service/RouteSnapshotManagerTest.php
# Expected: OK (4 tests)
```

**Commit:**
```bash
git add backend/src/Service/RouteSnapshotManager.php backend/tests/Unit/Service/RouteSnapshotManagerTest.php
git commit -m "feat: add RouteSnapshotManager service"
```

---

### Task 3: Create DTOs (MapViewOptions, MapViewData, RouteViewData, StopViewData)

**Files:**
- NEW: `backend/src/View/MapViewOptions.php`
- NEW: `backend/src/View/MapViewData.php`
- NEW: `backend/src/View/RouteViewData.php`
- NEW: `backend/src/View/StopViewData.php`
- NEW: `backend/tests/Unit/View/MapViewDataTest.php`

**Test first** (`tests/Unit/View/MapViewDataTest.php`):
```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\View;

use App\View\MapViewData;
use App\View\MapViewOptions;
use App\View\RouteViewData;
use App\View\StopViewData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MapViewData::class)]
#[CoversClass(RouteViewData::class)]
#[CoversClass(StopViewData::class)]
#[CoversClass(MapViewOptions::class)]
final class MapViewDataTest extends TestCase
{
    #[Test]
    public function mapViewDataSerializesToArray(): void
    {
        $stop = new StopViewData(
            sequence: 1,
            address: 'Calle Mayor 1',
            recipientName: 'Juan',
            recipientPhone: '600000000',
            lat: 40.416775,
            lng: -3.703790,
            status: 'PENDING',
            isOrigin: false,
        );

        $routeView = new RouteViewData(
            publicId: 'abc123',
            name: 'Ruta 1',
            color: '#3b82f6',
            vehicleName: 'Furgoneta A',
            driverName: null,
            status: 'PLANNED',
            stops: [$stop],
        );

        $options = new MapViewOptions(showOptimizationMetrics: true);

        $mapView = new MapViewData(
            routes: [$routeView],
            options: $options,
        );

        $array = $mapView->toArray();

        self::assertArrayHasKey('routes', $array);
        self::assertCount(1, $array['routes']);
        self::assertSame('abc123', $array['routes'][0]['publicId']);
        self::assertSame('Calle Mayor 1', $array['routes'][0]['stops'][0]['address']);
    }

    #[Test]
    public function mapViewDataSerializesToJson(): void
    {
        $options = new MapViewOptions();
        $mapView = new MapViewData(routes: [], options: $options);

        $json = $mapView->toJson();
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('routes', $decoded);
        self::assertSame([], $decoded['routes']);
    }

    #[Test]
    public function routeViewDataIncludesAllOptionalFields(): void
    {
        $routeView = new RouteViewData(
            publicId: 'route1',
            name: 'Test Route',
            color: '#10b981',
            vehicleName: 'Van 1',
            driverName: 'Carlos',
            status: 'ACTIVE',
            stops: [],
            polyline: 'encoded_polyline',
            metrics: ['distanceAfterKm' => 35.0],
            timing: ['totalTimeMinutes' => 90],
            validation: ['valid' => true],
            originalStops: [['seq' => 0, 'address' => 'Origin']],
            comparisonPolyline: 'actual_polyline',
        );

        $array = $routeView->toArray();

        self::assertSame('encoded_polyline', $array['polyline']);
        self::assertSame(35.0, $array['metrics']['distanceAfterKm']);
        self::assertSame('actual_polyline', $array['comparisonPolyline']);
    }

    #[Test]
    public function mapViewOptionsDefaultValues(): void
    {
        $options = new MapViewOptions();

        self::assertFalse($options->showOptimizationMetrics);
        self::assertTrue($options->showStopStatus);
        self::assertTrue($options->showPolylines);
        self::assertFalse($options->showVehicleTracking);
    }
}
```

**Verify test fails**, then implement the 4 DTO classes:

**`src/View/MapViewOptions.php`:**
```php
<?php

declare(strict_types=1);

namespace App\View;

final class MapViewOptions
{
    public function __construct(
        public readonly bool $showOptimizationMetrics = false,
        public readonly bool $showTimingBreakdown = false,
        public readonly bool $showVehicleTracking = false,
        public readonly bool $showStopStatus = true,
        public readonly bool $showCapacityValidation = false,
        public readonly bool $showOriginalOrder = false,
        public readonly bool $showPolylines = true,
        public readonly bool $showOptimizationLog = false,
        public readonly ?string $comparisonMode = null,
        public readonly ?string $vehiclePublicId = null,
        public readonly ?array $vehiclePosition = null,
        public readonly ?array $optimizationLog = null,
    ) {}
}
```

**`src/View/StopViewData.php`:**
```php
<?php

declare(strict_types=1);

namespace App\View;

final class StopViewData
{
    public function __construct(
        public readonly int $sequence,
        public readonly string $address,
        public readonly ?string $recipientName,
        public readonly ?string $recipientPhone,
        public readonly ?float $lat,
        public readonly ?float $lng,
        public readonly string $status,
        public readonly bool $isOrigin,
        public readonly ?string $deliveredAt = null,
        public readonly ?string $exceptionCode = null,
        public readonly ?string $exceptionNotes = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'sequence' => $this->sequence,
            'address' => $this->address,
            'recipientName' => $this->recipientName,
            'recipientPhone' => $this->recipientPhone,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => $this->status,
            'isOrigin' => $this->isOrigin,
            'deliveredAt' => $this->deliveredAt,
            'exceptionCode' => $this->exceptionCode,
            'exceptionNotes' => $this->exceptionNotes,
        ], static fn($v) => $v !== null);
    }
}
```

**`src/View/RouteViewData.php`:**
```php
<?php

declare(strict_types=1);

namespace App\View;

final class RouteViewData
{
    /**
     * @param list<StopViewData> $stops
     */
    public function __construct(
        public readonly string $publicId,
        public readonly string $name,
        public readonly string $color,
        public readonly ?string $vehicleName,
        public readonly ?string $driverName,
        public readonly ?string $status,
        public readonly array $stops,
        public readonly ?string $polyline = null,
        public readonly ?array $metrics = null,
        public readonly ?array $timing = null,
        public readonly ?array $validation = null,
        public readonly ?array $originalStops = null,
        public readonly ?string $comparisonPolyline = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'publicId' => $this->publicId,
            'name' => $this->name,
            'color' => $this->color,
            'vehicleName' => $this->vehicleName,
            'driverName' => $this->driverName,
            'status' => $this->status,
            'stops' => array_map(static fn(StopViewData $s) => $s->toArray(), $this->stops),
            'polyline' => $this->polyline,
            'metrics' => $this->metrics,
            'timing' => $this->timing,
            'validation' => $this->validation,
            'originalStops' => $this->originalStops,
            'comparisonPolyline' => $this->comparisonPolyline,
        ], static fn($v) => $v !== null);
    }
}
```

**`src/View/MapViewData.php`:**
```php
<?php

declare(strict_types=1);

namespace App\View;

final class MapViewData
{
    /**
     * @param list<RouteViewData> $routes
     */
    public function __construct(
        public readonly array $routes,
        public readonly MapViewOptions $options,
        public readonly ?array $origin = null,
        public readonly ?array $globalMetrics = null,
        public readonly ?string $mercureTopic = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'routes' => array_map(static fn(RouteViewData $r) => $r->toArray(), $this->routes),
            'origin' => $this->origin,
            'globalMetrics' => $this->globalMetrics,
            'mercureTopic' => $this->mercureTopic,
        ], static fn($v) => $v !== null);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
    }
}
```

**Verify tests pass:**
```bash
cd backend && php vendor/bin/phpunit tests/Unit/View/MapViewDataTest.php
```

**Commit:**
```bash
git add backend/src/View/ backend/tests/Unit/View/
git commit -m "feat: add MapViewData DTOs for route view layer"
```

---

### Task 4: Create RouteViewService

**Files:**
- NEW: `backend/src/View/RouteViewService.php`
- NEW: `backend/tests/Unit/View/RouteViewServiceTest.php`

**Test first** (`tests/Unit/View/RouteViewServiceTest.php`):

Test that:
1. `buildSingleRouteView()` reads from RouteSnapshot and produces MapViewData
2. Role filtering works (ROLE_ADMIN gets metrics, ROLE_CUSTOMER doesn't)
3. `buildMultiRouteView()` produces MapViewData with multiple routes
4. Missing snapshot gracefully returns empty/basic view

**Implementation** (`src/View/RouteViewService.php`):

Key methods:
- `buildSingleRouteView(Route $route, string $role, ?MapViewOptions $options = null): MapViewData`
  - Reads RouteSnapshot via repository
  - Builds RouteViewData from snapshot fields
  - Builds StopViewData from snapshot.stopStates (NOT from RouteStop entities)
  - Applies role filter to strip metrics/timing/validation for non-admin
  - Returns MapViewData with Mercure topic
- `buildMultiRouteView(array $routes, string $role, ?MapViewOptions $options = null): MapViewData`
  - Calls buildSingleRouteView for each route
  - Aggregates global metrics (total distance before/after, total savings)
  - Returns MapViewData

**Role filter logic:**
```php
private function filterForRole(string $role, RouteViewData $routeView, MapViewOptions $options): RouteViewData
{
    if ($role === 'ROLE_ADMIN') {
        return $routeView; // Admin sees everything
    }

    // Customer and Driver: strip metrics, timing, validation, original stops, comparison
    return new RouteViewData(
        publicId: $routeView->publicId,
        name: $routeView->name,
        color: $routeView->color,
        vehicleName: $routeView->vehicleName,
        driverName: $routeView->driverName,
        status: $routeView->status,
        stops: $routeView->stops,
        polyline: $options->showPolylines ? $routeView->polyline : null,
    );
}
```

**Route colors** (assigned by index):
```php
private const ROUTE_COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'];
```

**Verify tests pass, commit:**
```bash
git add backend/src/View/RouteViewService.php backend/tests/Unit/View/RouteViewServiceTest.php
git commit -m "feat: add RouteViewService with role-based filtering"
```

---

### Task 5: Integrate RouteSnapshotManager into RouteBuilder and RoutePlanningService

**Files modified:**
- `backend/src/Service/RouteBuilder.php` — add RouteSnapshotManager dependency, create snapshot after materializeRoutes
- `backend/src/Application/Route/RoutePlanningService.php` — create snapshot after optimizeRoute with apply=true

**Test first:** Write test that verifies RouteBuilder calls RouteSnapshotManager.createSnapshot after building routes. Mock the manager.

**Changes to RouteBuilder constructor:**
```php
public function __construct(
    private readonly EntityManagerInterface $em,
    private readonly RouteOptimizerInterface $optimizer,
    private readonly RouteCapacityValidator $capacityValidator,
    private readonly OptimizationLogger $optimizationLogger,
    private readonly RouteSnapshotManager $snapshotManager,  // NEW
) {}
```

**CRITICAL:** After changing constructor, check all call sites:
```bash
grep -r "new RouteBuilder(" backend/src/ backend/tests/
```
RouteBuilder is auto-wired by Symfony, so no Factory to update. But check tests.

**Changes to `buildRoutes()` — after `materializeRoutes()` and before return:**
```php
// Create snapshot for each route
foreach ($materializedRoutes as $mr) {
    $route = $mr['route'];
    $originalStopOrder = array_map(static fn(RouteStop $s) => [
        'sequence' => $s->getSequence(),
        'address' => $s->getAddress(),
        'recipientName' => $s->getRecipientName(),
        'lat' => $s->getLatitude(),
        'lng' => $s->getLongitude(),
        'isOrigin' => $s->isOrigin(),
    ], $mr['stops']);

    $this->snapshotManager->createSnapshot(
        $route,
        originalStopOrder: $originalStopOrder,
    );
}
```

**Changes to RoutePlanningService.optimizeRoute() — in the `if ($apply)` block, after flush:**
```php
// Store original stop order before optimization for snapshot
$originalStopOrder = [];
foreach ($result['optimized'] as $item) {
    $stop = $item['stop'];
    $originalStopOrder[] = [
        'sequence' => $stop->getSequence(), // current (pre-optimize) sequence
        'address' => $stop->getAddress(),
        'recipientName' => $stop->getRecipientName(),
        'lat' => $stop->getLatitude(),
        'lng' => $stop->getLongitude(),
        'isOrigin' => $stop->isOrigin(),
    ];
}
```

Then after `applyOptimizedOrder` + flush:
```php
$this->snapshotManager->createSnapshot(
    $route,
    distanceBeforeKm: $distanceBefore,
    distanceAfterKm: $distanceAfter,
    originalStopOrder: $originalStopOrder,
);
$this->em->flush();
```

**RoutePlanningService constructor change:**
```php
public function __construct(
    private EntityManagerInterface $em,
    private RouteBuilder $routeBuilder,
    private RouteOptimizationService $optimizationService,
    private RouteCapacityValidator $capacityValidator,
    private RouteRepository $routeRepo,
    private RouteStopRepository $stopRepo,
    private EventDispatcherInterface $eventDispatcher,
    private OptimizationLogger $optimizationLogger,
    private RouteSnapshotManager $snapshotManager,  // NEW
) {}
```

**CRITICAL:** Check call sites for RoutePlanningService:
```bash
grep -r "new RoutePlanningService(" backend/src/ backend/tests/
```
Also auto-wired, but verify.

**Verify all existing tests still pass:**
```bash
cd backend && php vendor/bin/phpunit
```

**Commit:**
```bash
git add backend/src/Service/RouteBuilder.php backend/src/Application/Route/RoutePlanningService.php backend/tests/
git commit -m "feat: integrate RouteSnapshotManager into RouteBuilder and RoutePlanningService"
```

---

### Task 6: Create RouteSnapshotListener for progress events

**Files:**
- NEW: `backend/src/EventListener/Domain/RouteSnapshotListener.php`
- NEW: `backend/tests/Unit/EventListener/Domain/RouteSnapshotListenerTest.php`

**Test first:**
- Test `onStopDelivered` calls `snapshotManager->updateStopStates()` and publishes Mercure update
- Test `onStopException` does the same
- Test `onRouteCompleted` does the same
- Test missing route is handled gracefully (no-op)

**Implementation** (`src/EventListener/Domain/RouteSnapshotListener.php`):
```php
<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteStarted;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Entity\Route;
use App\Repository\RouteRepository;
use App\Service\RouteSnapshotManager;
use App\View\RouteViewService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

final readonly class RouteSnapshotListener
{
    public function __construct(
        private RouteSnapshotManager $snapshotManager,
        private RouteViewService $viewService,
        private HubInterface $hub,
        private RouteRepository $routeRepo,
    ) {}

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $this->handleProgressEvent($event->routePublicId);
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $this->handleProgressEvent($event->routePublicId);
    }

    #[AsEventListener]
    public function onRouteStarted(RouteStarted $event): void
    {
        $this->handleProgressEvent($event->routePublicId);
    }

    #[AsEventListener]
    public function onRouteCompleted(RouteCompleted $event): void
    {
        $this->handleProgressEvent($event->routePublicId);
    }

    private function handleProgressEvent(string $routePublicId): void
    {
        $route = $this->routeRepo->findOneByPublicId($routePublicId);
        if (!$route instanceof Route) {
            return;
        }

        $this->snapshotManager->updateStopStates($route);
        $this->publishRouteViewUpdate($route);
    }

    private function publishRouteViewUpdate(Route $route): void
    {
        $roles = ['ROLE_ADMIN', 'ROLE_CUSTOMER', 'ROLE_DRIVER'];

        foreach ($roles as $role) {
            try {
                $mapData = $this->viewService->buildSingleRouteView($route, $role);
                $this->hub->publish(new Update(
                    sprintf('/routes/%s/view/%s', $route->getPublicIdString(), strtolower(str_replace('ROLE_', '', $role))),
                    $mapData->toJson(),
                ));
            } catch (Throwable) {
                // Don't break the flow on Mercure failure
            }
        }
    }
}
```

**Verify tests pass, commit:**
```bash
git add backend/src/EventListener/Domain/RouteSnapshotListener.php backend/tests/Unit/EventListener/Domain/RouteSnapshotListenerTest.php
git commit -m "feat: add RouteSnapshotListener for progress events + Mercure publishing"
```

---

### Task 7: Create Doctrine migration

**Run:**
```bash
cd backend && php bin/console doctrine:migrations:diff --no-interaction
```

Review the generated migration, ensure it creates the `route_snapshot` table with all columns.

**Run migration:**
```bash
cd backend && php bin/console doctrine:migrations:migrate --no-interaction
```

**Verify all tests pass:**
```bash
cd backend && php vendor/bin/phpunit
```

**Commit:**
```bash
git add backend/migrations/
git commit -m "migration: add route_snapshot table"
```

---

## Phase 2: Frontend — Twig Components + MxoRouteMap JS

### Task 8: Create MxoRouteMap JS class

**File:** NEW: `backend/templates/components/route/_map_js.html.twig`

This is the shared JavaScript class included once per page. It handles:
- Leaflet map initialization
- Rendering N routes with polylines (encoded Google format)
- Fallback straight lines if no polyline
- Stop markers (numbered, color by status)
- Origin marker (green "O")
- Toggle route visibility
- Vehicle marker + Mercure tracking
- Polyline comparison mode (planned vs actual)
- Arrow decorations
- `update(newData)` method for full re-render
- `mxo:route-updated` custom event for external components

**Color constants:**
```javascript
MxoRouteMap.COLORS = {
    routes: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'],
    stopStatus: {
        PENDING: '#3b82f6', DELIVERED: '#22c55e',
        EXCEPTION: '#ef4444', SKIPPED: '#6b7280',
    },
    origin: '#059669',
    vehicle: '#1e3a8a',
};
```

**Commit:**
```bash
git add backend/templates/components/route/_map_js.html.twig
git commit -m "feat: add MxoRouteMap JS class"
```

### Task 9: Create Twig components

**Files:**
- NEW: `backend/templates/components/route/_map.html.twig` — Leaflet map container + init
- NEW: `backend/templates/components/route/_metrics.html.twig` — Global metrics cards
- NEW: `backend/templates/components/route/_stop_list.html.twig` — Stop table with status colors
- NEW: `backend/templates/components/route/_route_card.html.twig` — Per-route detail card

Each component receives `mapView` (MapViewData serialized) or specific fields.

**`_map.html.twig`** receives: `mapId`, `mapData` (JSON), `height`
```twig
<div id="{{ mapId }}" style="height: {{ height|default('500px') }}; width: 100%;" class="rounded-lg border border-gray-200"></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const data = {{ mapData|raw }};
    new MxoRouteMap('{{ mapId }}', data);
});
</script>
```

**`_metrics.html.twig`** receives: `globalMetrics`
Grid of 4 cards: Distance Before, Distance After, Savings %, Total Time

**`_stop_list.html.twig`** receives: `stops` (StopViewData[]), `showStatus`, optional `listenUpdates` (Mercure topic)
Scrollable table with sequence, address, recipient, status badge.

**`_route_card.html.twig`** receives: `route` (RouteViewData), `showMetrics`, `showTiming`, `showValidation`, `showOriginalOrder`
Collapsible card with route details.

**Commit:**
```bash
git add backend/templates/components/route/
git commit -m "feat: add Twig route components (map, metrics, stop list, route card)"
```

---

## Phase 3: View Migration (one at a time)

### Task 10: Migrate customer/route/show

**Files modified:**
- `backend/src/Controller/Customer/CustomerRouteController.php` — inject RouteViewService, build MapViewData
- `backend/templates/customer/route/show.html.twig` — replace inline JS with components

**Controller change:**
```php
public function show(string $publicId): Response
{
    $route = $this->routeRepo->findOneByPublicId($publicId);
    // ... existing checks ...

    $mapView = $this->routeViewService->buildSingleRouteView(
        $route,
        'ROLE_CUSTOMER',
        new MapViewOptions(
            showVehicleTracking: true,
            showStopStatus: true,
            vehiclePublicId: $route->getVehicle()?->getPublicIdString(),
        ),
    );

    return $this->render('customer/route/show.html.twig', [
        'route' => $route,
        'mapView' => $mapView,
        'mapViewJson' => $mapView->toJson(),
    ]);
}
```

**Template:** Replace all inline Leaflet JS with component includes.

**Verify visually, commit.**

### Task 11: Migrate driver/routes/show

Same pattern as customer — inject RouteViewService, use `ROLE_DRIVER`, replace inline JS.

### Task 12: Migrate test-routing/map

**Files modified:**
- `backend/src/Controller/Admin/TestRoutingController.php` — inject RouteViewService, build multi-route MapViewData
- `backend/templates/admin/test-routing/map.html.twig` — replace with components

Use `buildMultiRouteView()` with `MapViewOptions(showOptimizationMetrics: true, showTimingBreakdown: true, showOriginalOrder: true, showOptimizationLog: true)`.

### Task 13: Migrate route_planner step 3

Most complex — Alpine.js wizard. The map portion of step 3 gets replaced with `_map.html.twig` component. The route cards get replaced with `_route_card.html.twig`. Driver suggestion stays custom.

### Task 14: Migrate admin/route/analysis

Use `comparisonMode: 'planned_vs_actual'` in MapViewOptions. Read `actualPolyline` from RouteSnapshot.

---

## Verification

After all tasks:
```bash
cd backend && php vendor/bin/phpunit
cd backend && make lint
```

Verify each migrated view visually loads correctly.

---

## File Summary

### New files (14):
```
src/Entity/RouteSnapshot.php
src/Repository/RouteSnapshotRepository.php
src/Service/RouteSnapshotManager.php
src/EventListener/Domain/RouteSnapshotListener.php
src/View/MapViewOptions.php
src/View/MapViewData.php
src/View/RouteViewData.php
src/View/StopViewData.php
src/View/RouteViewService.php
templates/components/route/_map.html.twig
templates/components/route/_map_js.html.twig
templates/components/route/_metrics.html.twig
templates/components/route/_stop_list.html.twig
templates/components/route/_route_card.html.twig
migrations/VersionXXXX.php
tests/Unit/Entity/RouteSnapshotTest.php
tests/Unit/Service/RouteSnapshotManagerTest.php
tests/Unit/View/MapViewDataTest.php
tests/Unit/View/RouteViewServiceTest.php
tests/Unit/EventListener/Domain/RouteSnapshotListenerTest.php
```

### Modified files (7):
```
src/Service/RouteBuilder.php (add snapshotManager dependency + createSnapshot call)
src/Application/Route/RoutePlanningService.php (add snapshotManager dependency + createSnapshot call)
src/Controller/Customer/CustomerRouteController.php (inject RouteViewService)
src/Controller/DriverWebController.php (inject RouteViewService)
src/Controller/Admin/TestRoutingController.php (inject RouteViewService)
src/Controller/Admin/RoutePlannerController.php (inject RouteViewService)
src/Controller/Admin/RouteAnalysisController.php (inject RouteViewService)
templates/customer/route/show.html.twig (use components)
templates/driver/routes/show.html.twig (use components)
templates/admin/test-routing/map.html.twig (use components)
templates/admin/route_planner/index.html.twig (step 3 uses components)
templates/admin/route/analysis.html.twig (use components)
```
