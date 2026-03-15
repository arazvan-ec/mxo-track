# Plan: Route Event History (Fase 1)

**Goal:** Implement an immutable, append-only event history for routes that records every lifecycle change, enabling audit, analytics, and real-time timeline views.

**Spec:** `docs/superpowers/specs/2026-03-13-route-event-history-design.md`

**Architecture:** Symfony 7.4 + Doctrine ORM 3.x + PostgreSQL + Mercure SSE

**Branch:** `claude/fix-routing-map-inconsistency-Vnnyo`

---

## File Structure

### New Files
| File | Purpose |
|------|---------|
| `backend/src/Enum/RouteEventType.php` | String-backed enum with 15 event types |
| `backend/src/Entity/RouteEvent.php` | Append-only entity (no updates/deletes) |
| `backend/src/Repository/RouteEventRepository.php` | Repository with `findByRoute()` |
| `backend/src/Domain/Event/RouteCancelled.php` | Domain event |
| `backend/src/Domain/Event/RouteAssigned.php` | Domain event |
| `backend/src/EventListener/Domain/RouteEventLogListener.php` | Central listener that persists RouteEvent for every domain event |
| `backend/src/Controller/Api/RouteEventApiController.php` | API endpoint for event history |
| `backend/migrations/Version20260313200000.php` | Migration for `route_event` table |
| `backend/tests/Unit/EventListener/Domain/RouteEventLogListenerTest.php` | Unit tests for the listener |
| `backend/tests/Unit/Controller/Api/RouteEventApiControllerTest.php` | Tests for API endpoint |

### Modified Files
| File | Change |
|------|--------|
| `backend/src/Controller/Admin/RouteAdminController.php` | Add EventDispatcher, dispatch `RouteCancelled` in `delete()`, dispatch `RouteAssigned` in `edit()` |
| `backend/templates/admin/route/show.html.twig` | Add timeline section |
| `docs/knowledge/domain-model.md` | Document RouteEvent, RouteEventType, new domain events |

---

## Tasks

### Task 1: Create `RouteEventType` enum
- [ ] Create `backend/src/Enum/RouteEventType.php`

```php
<?php

declare(strict_types=1);

namespace App\Enum;

enum RouteEventType: string
{
    // Lifecycle
    case CREATED = 'CREATED';
    case OPTIMIZED = 'OPTIMIZED';
    case ASSIGNED = 'ASSIGNED';
    case STARTED = 'STARTED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    // Stop changes
    case STOP_DELIVERED = 'STOP_DELIVERED';
    case STOP_EXCEPTION = 'STOP_EXCEPTION';
    case STOP_SKIPPED = 'STOP_SKIPPED';

    // Optimization
    case REOPTIMIZED = 'REOPTIMIZED';
    case STOPS_REORDERED = 'STOPS_REORDERED';

    // Deviations (prepared for Phase 3)
    case DEVIATION_DETECTED = 'DEVIATION_DETECTED';
    case ETA_CHANGED = 'ETA_CHANGED';

    // External
    case NOTE_ADDED = 'NOTE_ADDED';
}
```

**Verify:** `php -l backend/src/Enum/RouteEventType.php`
**Commit:** `feat: add RouteEventType enum with 15 event types`

---

### Task 2: Create `RouteEvent` entity + migration
- [ ] Create `backend/src/Entity/RouteEvent.php`

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RouteEventType;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\RouteEventRepository;

#[ORM\Entity(repositoryClass: RouteEventRepository::class)]
#[ORM\Table(name: 'route_event')]
#[ORM\Index(name: 'idx_route_event_route_occurred', columns: ['route_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_route_event_type_occurred', columns: ['event_type', 'occurred_at'])]
class RouteEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\Column(length: 40, enumType: RouteEventType::class)]
    private RouteEventType $eventType;

    #[ORM\Column(length: 20)]
    private string $actorType;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actorUser = null;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $snapshotMetrics = null;

    #[ORM\Column]
    private DateTimeImmutable $occurredAt;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Route $route,
        RouteEventType $eventType,
        string $actorType,
        ?User $actorUser = null,
        array $payload = [],
        ?array $snapshotMetrics = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->route = $route;
        $this->eventType = $eventType;
        $this->actorType = $actorType;
        $this->actorUser = $actorUser;
        $this->payload = $payload;
        $this->snapshotMetrics = $snapshotMetrics;
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getRoute(): Route { return $this->route; }
    public function getEventType(): RouteEventType { return $this->eventType; }
    public function getActorType(): string { return $this->actorType; }
    public function getActorUser(): ?User { return $this->actorUser; }
    public function getPayload(): array { return $this->payload; }
    public function getSnapshotMetrics(): ?array { return $this->snapshotMetrics; }
    public function getOccurredAt(): DateTimeImmutable { return $this->occurredAt; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
```

- [ ] Create `backend/migrations/Version20260313200000.php`

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create route_event table for immutable route event history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE route_event (
                id BIGSERIAL NOT NULL,
                route_id BIGINT NOT NULL,
                event_type VARCHAR(40) NOT NULL,
                actor_type VARCHAR(20) NOT NULL,
                actor_user_id BIGINT DEFAULT NULL,
                payload JSON NOT NULL DEFAULT '{}',
                snapshot_metrics JSON DEFAULT NULL,
                occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('COMMENT ON COLUMN route_event.occurred_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN route_event.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE INDEX idx_route_event_route_occurred ON route_event (route_id, occurred_at)');
        $this->addSql('CREATE INDEX idx_route_event_type_occurred ON route_event (event_type, occurred_at)');

        $this->addSql('ALTER TABLE route_event ADD CONSTRAINT fk_route_event_route FOREIGN KEY (route_id) REFERENCES route_plan (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE route_event ADD CONSTRAINT fk_route_event_actor_user FOREIGN KEY (actor_user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE route_event');
    }
}
```

**Verify:** `php -l backend/src/Entity/RouteEvent.php && cd backend && php bin/console doctrine:schema:validate --skip-sync 2>&1 | head -5`
**Commit:** `feat: add RouteEvent entity and migration`

---

### Task 3: Create `RouteEventRepository`
- [ ] Create `backend/src/Repository/RouteEventRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Route;
use App\Entity\RouteEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RouteEvent>
 */
final class RouteEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RouteEvent::class);
    }

    /**
     * @return RouteEvent[]
     */
    public function findByRoute(Route $route): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.route = :route')
            ->setParameter('route', $route)
            ->orderBy('e.occurredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

**Verify:** `php -l backend/src/Repository/RouteEventRepository.php`
**Commit:** `feat: add RouteEventRepository with findByRoute()`

---

### Task 4: Create domain events `RouteCancelled` and `RouteAssigned`
- [ ] Create `backend/src/Domain/Event/RouteCancelled.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class RouteCancelled
{
    public function __construct(
        public string $routePublicId,
        public int $cancelledByUserId,
        public ?string $reason = null,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
```

- [ ] Create `backend/src/Domain/Event/RouteAssigned.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Event;

final readonly class RouteAssigned
{
    public function __construct(
        public string $routePublicId,
        public ?string $vehiclePublicId,
        public ?int $driverUserId,
        public int $assignedByUserId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
```

**Verify:** `php -l backend/src/Domain/Event/RouteCancelled.php && php -l backend/src/Domain/Event/RouteAssigned.php`
**Commit:** `feat: add RouteCancelled and RouteAssigned domain events`

---

### Task 5: Create `RouteEventLogListener` with tests (TDD)

**Step 5a: Write failing test**
- [ ] Create `backend/tests/Unit/EventListener/Domain/RouteEventLogListenerTest.php`

The test mocks `EntityManagerInterface`, `RouteRepository`, `UserRepository`, and `RouteStopRepository`. For each domain event handler, assert that `$em->persist()` is called with a `RouteEvent` of the expected `eventType` and `actorType`.

Test cases:
1. `onRoutesBuilt` — creates a CREATED event per route with actor_type=system
2. `onRouteOptimized` — creates an OPTIMIZED event with actor_type=system, payload has improvement_percent
3. `onRouteStarted` — creates a STARTED event with actor_type=driver
4. `onRouteCompleted` — creates a COMPLETED event with actor_type=driver
5. `onStopDelivered` — creates a STOP_DELIVERED event with actor_type=driver, payload has stop/shipment/pod IDs
6. `onStopExceptionReported` — creates a STOP_EXCEPTION event with actor_type=driver
7. `onRouteCancelled` — creates a CANCELLED event with actor_type=admin
8. `onRouteAssigned` — creates an ASSIGNED event with actor_type=admin
9. `missingRouteIsNoOp` — returns early when route not found

**Verify:** Run tests — all should FAIL (listener not yet implemented).
```bash
cd backend && php vendor/bin/phpunit tests/Unit/EventListener/Domain/RouteEventLogListenerTest.php
```

**Step 5b: Implement listener**
- [ ] Create `backend/src/EventListener/Domain/RouteEventLogListener.php`

```php
<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\RouteAssigned;
use App\Domain\Event\RouteCancelled;
use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteOptimized;
use App\Domain\Event\RouteStarted;
use App\Domain\Event\RoutesBuilt;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Entity\RouteEvent;
use App\Enum\RouteEventType;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class RouteEventLogListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouteRepository $routeRepo,
        private UserRepository $userRepo,
        private RouteStopRepository $stopRepo,
    ) {}

    #[AsEventListener]
    public function onRoutesBuilt(RoutesBuilt $event): void
    {
        foreach ($event->routePublicIds as $routePublicId) {
            $route = $this->routeRepo->findOneByPublicId($routePublicId);
            if (!$route) {
                continue;
            }

            $this->em->persist(new RouteEvent(
                route: $route,
                eventType: RouteEventType::CREATED,
                actorType: 'system',
                payload: [
                    'shipment_count' => $event->shipmentCount,
                    'vehicle_count' => $event->vehicleCount,
                ],
                snapshotMetrics: $this->buildSnapshotMetrics($route),
                occurredAt: $event->occurredAt,
            ));
        }
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteOptimized(RouteOptimized $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::OPTIMIZED,
            actorType: 'system',
            payload: [
                'improvement_percent' => $event->improvementPercent,
                'distance_km' => $event->distanceKm,
                'duration_minutes' => $event->durationMinutes,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteStarted(RouteStarted $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::STARTED,
            actorType: 'driver',
            actorUser: $actor,
            payload: ['driver_user_id' => $event->driverUserId],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteCompleted(RouteCompleted $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::COMPLETED,
            actorType: 'driver',
            actorUser: $actor,
            payload: ['driver_user_id' => $event->driverUserId],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::STOP_DELIVERED,
            actorType: 'driver',
            actorUser: $actor,
            payload: [
                'stop_public_id' => $event->stopPublicId,
                'shipment_public_id' => $event->shipmentPublicId,
                'pod_public_id' => $event->podPublicId,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->driverUserId);

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::STOP_EXCEPTION,
            actorType: 'driver',
            actorUser: $actor,
            payload: [
                'stop_public_id' => $event->stopPublicId,
                'exception_code' => $event->reason->value,
                'notes' => $event->notes,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteCancelled(RouteCancelled $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->cancelledByUserId);

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::CANCELLED,
            actorType: 'admin',
            actorUser: $actor,
            payload: ['reason' => $event->reason],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    #[AsEventListener]
    public function onRouteAssigned(RouteAssigned $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $actor = $this->userRepo->find($event->assignedByUserId);

        $this->em->persist(new RouteEvent(
            route: $route,
            eventType: RouteEventType::ASSIGNED,
            actorType: 'admin',
            actorUser: $actor,
            payload: [
                'vehicle_public_id' => $event->vehiclePublicId,
                'driver_user_id' => $event->driverUserId,
            ],
            snapshotMetrics: $this->buildSnapshotMetrics($route),
            occurredAt: $event->occurredAt,
        ));
        $this->em->flush();
    }

    private function buildSnapshotMetrics(\App\Entity\Route $route): array
    {
        $stops = $this->stopRepo->findByRoute($route);

        $total = 0;
        $delivered = 0;
        $exceptions = 0;
        $pending = 0;

        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                continue;
            }
            $total++;
            match ($stop->getStatus()) {
                \App\Enum\RouteStopStatus::DELIVERED => $delivered++,
                \App\Enum\RouteStopStatus::EXCEPTION => $exceptions++,
                default => $pending++,
            };
        }

        return [
            'total_stops' => $total,
            'delivered' => $delivered,
            'exceptions' => $exceptions,
            'pending' => $pending,
        ];
    }
}
```

**Verify:** Run tests — all should PASS.
```bash
cd backend && php vendor/bin/phpunit tests/Unit/EventListener/Domain/RouteEventLogListenerTest.php
```

**Commit:** `feat: add RouteEventLogListener with tests for all domain events`

---

### Task 6: Dispatch `RouteCancelled` from `RouteAdminController::delete()`

- [ ] Modify `backend/src/Controller/Admin/RouteAdminController.php`

Add `EventDispatcherInterface` to constructor:
```php
use Psr\EventDispatcher\EventDispatcherInterface;
// In constructor:
private readonly EventDispatcherInterface $dispatcher,
```

In `delete()`, after `$route->setStatus(RouteStatus::CANCELLED)` and before `$this->em->flush()`:
```php
$this->dispatcher->dispatch(new \App\Domain\Event\RouteCancelled(
    routePublicId: $route->getPublicIdString(),
    cancelledByUserId: $this->getUser()->getId(),
));
```

**Verify:** `php -l backend/src/Controller/Admin/RouteAdminController.php`
**Commit:** `feat: dispatch RouteCancelled event from admin delete action`

---

### Task 7: Dispatch `RouteAssigned` from `RouteAdminController::edit()`

- [ ] Modify `backend/src/Controller/Admin/RouteAdminController.php`

In `edit()`, inside the `if ($form->isSubmitted() && $form->isValid())` block, before flush, detect vehicle/driver changes using Doctrine UnitOfWork:

```php
$uow = $this->em->getUnitOfWork();
$uow->computeChangeSets();
$changeset = $uow->getEntityChangeSet($route);

$vehicleChanged = isset($changeset['vehicle']);
$driverChanged = isset($changeset['driver']);

if ($vehicleChanged || $driverChanged) {
    $this->dispatcher->dispatch(new \App\Domain\Event\RouteAssigned(
        routePublicId: $route->getPublicIdString(),
        vehiclePublicId: $route->getVehicle()?->getPublicIdString(),
        driverUserId: $route->getDriver()?->getId(),
        assignedByUserId: $this->getUser()->getId(),
    ));
}
```

**Verify:** `php -l backend/src/Controller/Admin/RouteAdminController.php`
**Commit:** `feat: dispatch RouteAssigned event when driver/vehicle changes in edit`

---

### Task 8: Create API endpoint for route event history

- [ ] Create `backend/src/Controller/Api/RouteEventApiController.php`

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\RouteEventRepository;
use App\Repository\RouteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/routes')]
#[IsGranted('ROLE_ADMIN')]
class RouteEventApiController extends AbstractController
{
    public function __construct(
        private readonly RouteRepository $routeRepo,
        private readonly RouteEventRepository $eventRepo,
    ) {}

    #[Route('/{publicId}/events', name: 'api_route_events', methods: ['GET'])]
    public function events(string $publicId): JsonResponse
    {
        $route = $this->routeRepo->findOneByPublicId($publicId);

        if (!$route) {
            return new JsonResponse(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $events = $this->eventRepo->findByRoute($route);

        $data = array_map(static fn ($e) => [
            'type' => $e->getEventType()->value,
            'actor_type' => $e->getActorType(),
            'actor_email' => $e->getActorUser()?->getEmail(),
            'payload' => $e->getPayload(),
            'snapshot_metrics' => $e->getSnapshotMetrics(),
            'occurred_at' => $e->getOccurredAt()->format('c'),
        ], $events);

        return new JsonResponse(['events' => $data]);
    }
}
```

**Verify:** `php -l backend/src/Controller/Api/RouteEventApiController.php`
**Commit:** `feat: add API endpoint GET /api/routes/{publicId}/events`

---

### Task 9: Add Mercure publishing for route events

- [ ] Modify `backend/src/EventListener/Domain/RouteEventLogListener.php`

Add `HubInterface` to constructor. After each `$this->em->persist(new RouteEvent(...))`, publish to Mercure topic `/routes/{publicId}/events`:

```php
private function publishToMercure(string $routePublicId, RouteEvent $routeEvent): void
{
    try {
        $this->hub->publish(new \Symfony\Component\Mercure\Update(
            sprintf('/routes/%s/events', $routePublicId),
            json_encode([
                'type' => $routeEvent->getEventType()->value,
                'actor_type' => $routeEvent->getActorType(),
                'actor_email' => $routeEvent->getActorUser()?->getEmail(),
                'payload' => $routeEvent->getPayload(),
                'snapshot_metrics' => $routeEvent->getSnapshotMetrics(),
                'occurred_at' => $routeEvent->getOccurredAt()->format('c'),
            ], JSON_THROW_ON_ERROR),
        ));
    } catch (\Throwable) {
        // Mercure failure must not break event logging
    }
}
```

**Verify:** Run listener tests still pass.
```bash
cd backend && php vendor/bin/phpunit tests/Unit/EventListener/Domain/RouteEventLogListenerTest.php
```

**Commit:** `feat: publish route events to Mercure for real-time timeline`

---

### Task 10: Add timeline section to admin route show

- [ ] Modify `backend/templates/admin/route/show.html.twig`

After the split layout `</div>` (line 124), add a new section:

```twig
{# ── Timeline: Route Event History ─────────────────────────────── #}
<div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
     x-data="routeTimeline()" x-init="init()">
  <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-gray-900">Historial de eventos</h2>
    <span class="text-xs text-gray-400" x-text="events.length + ' eventos'"></span>
  </div>
  <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
    <template x-for="ev in events" :key="ev.occurred_at + ev.type">
      <div class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50">
        <div class="mt-0.5 flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-sm"
             :class="iconClass(ev.type)">
          <span x-text="iconEmoji(ev.type)"></span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-900" x-text="label(ev.type)"></p>
          <p class="text-xs text-gray-500 mt-0.5">
            <span x-text="ev.actor_type"></span>
            <template x-if="ev.actor_email">
              <span> · <span x-text="ev.actor_email"></span></span>
            </template>
          </p>
          <template x-if="ev.snapshot_metrics">
            <p class="text-xs text-gray-400 mt-0.5">
              <span x-text="ev.snapshot_metrics.delivered + '/' + ev.snapshot_metrics.total_stops + ' entregados'"></span>
              <template x-if="ev.snapshot_metrics.exceptions > 0">
                <span class="text-red-400" x-text="' · ' + ev.snapshot_metrics.exceptions + ' excepciones'"></span>
              </template>
            </p>
          </template>
        </div>
        <time class="text-xs text-gray-400 flex-shrink-0 whitespace-nowrap" x-text="formatTime(ev.occurred_at)"></time>
      </div>
    </template>
    <div x-show="events.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
      Sin eventos registrados
    </div>
  </div>
</div>

<script>
function routeTimeline() {
  return {
    events: [],
    init() {
      fetch('{{ path('api_route_events', {publicId: route.publicIdString}) }}', {credentials: 'same-origin'})
        .then(r => r.json())
        .then(d => { this.events = d.events || []; });
      {% if mercurePublicUrl is defined and mercurePublicUrl %}
      const hub = new URL('{{ mercurePublicUrl }}');
      hub.searchParams.append('topic', '/routes/{{ route.publicIdString }}/events');
      const es = new EventSource(hub, {withCredentials: true});
      es.onmessage = (e) => {
        try { this.events.unshift(JSON.parse(e.data)); } catch(_) {}
      };
      {% endif %}
    },
    label(type) {
      const labels = {
        CREATED:'Ruta creada', OPTIMIZED:'Optimizada', ASSIGNED:'Asignada',
        STARTED:'Iniciada', COMPLETED:'Completada', CANCELLED:'Cancelada',
        STOP_DELIVERED:'Entrega realizada', STOP_EXCEPTION:'Excepción reportada',
        STOP_SKIPPED:'Parada omitida', REOPTIMIZED:'Re-optimizada',
        STOPS_REORDERED:'Paradas reordenadas', DEVIATION_DETECTED:'Desvío detectado',
        ETA_CHANGED:'ETA actualizada', NOTE_ADDED:'Nota añadida'
      };
      return labels[type] || type;
    },
    iconClass(type) {
      const m = {
        CREATED:'bg-blue-100 text-blue-600', OPTIMIZED:'bg-purple-100 text-purple-600',
        ASSIGNED:'bg-indigo-100 text-indigo-600', STARTED:'bg-amber-100 text-amber-600',
        COMPLETED:'bg-green-100 text-green-600', CANCELLED:'bg-red-100 text-red-600',
        STOP_DELIVERED:'bg-green-100 text-green-600', STOP_EXCEPTION:'bg-red-100 text-red-600',
        STOP_SKIPPED:'bg-gray-100 text-gray-600'
      };
      return m[type] || 'bg-gray-100 text-gray-600';
    },
    iconEmoji(type) {
      const m = {
        CREATED:'+', OPTIMIZED:'⚡', ASSIGNED:'→', STARTED:'▶',
        COMPLETED:'✓', CANCELLED:'✕', STOP_DELIVERED:'✓', STOP_EXCEPTION:'!',
        STOP_SKIPPED:'—', REOPTIMIZED:'⚡', NOTE_ADDED:'✎'
      };
      return m[type] || '•';
    },
    formatTime(iso) {
      try { return new Date(iso).toLocaleTimeString('es', {hour:'2-digit',minute:'2-digit'}); } catch(_) { return iso; }
    }
  };
}
</script>
```

- [ ] Also pass `mercurePublicUrl` to the template from the controller show method (check if it's already available).

**Verify:** `cd backend && php bin/console lint:twig templates/admin/route/show.html.twig`
**Commit:** `feat: add real-time event timeline to admin route show`

---

### Task 11: Run migration and full test suite

- [ ] Run migration:
```bash
cd backend && php bin/console doctrine:migrations:migrate -n
```

- [ ] Run full test suite:
```bash
cd backend && php vendor/bin/phpunit
```

- [ ] Fix any new failures.

**Commit:** (only if fixes needed)

---

### Task 12: Update documentation

- [ ] Update `docs/knowledge/domain-model.md` — add RouteEvent entity, RouteEventType enum, RouteCancelled and RouteAssigned to domain events registry
- [ ] Update `docs/knowledge/realtime.md` — add `/routes/{publicId}/events` Mercure topic
- [ ] Update `docs/knowledge/api-surface.md` — add `GET /api/routes/{publicId}/events` endpoint

**Commit:** `docs: update knowledge modules with RouteEvent system`

---

## Execution Order

Tasks 1-4 are foundational (enum, entity, repo, domain events) — execute sequentially.
Task 5 is the core listener — TDD: test first, then implement.
Tasks 6-7 are controller modifications — sequential (same file).
Task 8 is the API endpoint — independent after Task 3.
Task 9 adds Mercure to the listener — after Task 5.
Task 10 is the UI — after Tasks 8 and 9.
Tasks 11-12 are verification and docs — last.
