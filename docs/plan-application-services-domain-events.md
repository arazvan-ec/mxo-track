# Plan: Application Services + Domain Events

## Objetivo

Extraer la lógica de negocio de los controllers a Application Services reutilizables
desde cualquier entry point (API HTTP, CLI command, message handler, tests).
Introducir Domain Events para desacoplar side-effects (audit, notificaciones, webhooks, Mercure).

---

## Estructura de directorios

```
backend/src/
├── Application/              # Use cases (Application Services)
│   ├── Delivery/             # Entrega y excepciones
│   ├── Route/                # Ciclo de vida de rutas, planificación, optimización
│   ├── Fleet/                # Vista de flota, KPIs, mapa
│   ├── Tracking/             # Tracking público
│   └── Shipment/             # Importación CSV (ya delegado, wrappear)
├── Domain/
│   └── Event/                # Domain Events (value objects inmutables)
├── EventListener/            # Listeners de domain events (side-effects)
│   └── Domain/
```

---

## Fase A — Domain Events (infraestructura + eventos)

### A.1 — Infraestructura de eventos

No se necesita framework custom. Usamos `Symfony EventDispatcher` nativo:
- Los domain events son clases PHP simples (no extienden nada de Symfony)
- Se despachan via `EventDispatcherInterface::dispatch()`
- Los listeners se registran con `#[AsEventListener]`

### A.2 — Domain Events (value objects)

Cada evento es `final readonly class` con los datos relevantes.

| Evento | Namespace | Datos | Disparado desde |
|--------|-----------|-------|-----------------|
| `StopDelivered` | `App\Domain\Event` | stopPublicId, shipmentPublicId, routePublicId, driverUserId, podId, timestamp | DeliveryService |
| `StopExceptionReported` | `App\Domain\Event` | stopPublicId, shipmentPublicId, routePublicId, driverUserId, reason, notes, timestamp | DeliveryService |
| `RouteStarted` | `App\Domain\Event` | routePublicId, driverUserId, timestamp | RouteLifecycleService |
| `RouteCompleted` | `App\Domain\Event` | routePublicId, driverUserId, timestamp | RouteLifecycleService |
| `RouteOptimized` | `App\Domain\Event` | routePublicId, improvementPercent, distanceKm, durationMinutes | RoutePlanningService |
| `RoutesBuilt` | `App\Domain\Event` | routePublicIds[], shipmentCount, vehicleCount | RoutePlanningService |
| `VehiclePositionReceived` | `App\Domain\Event` | vehiclePublicId, lat, lng, speed, course, deviceTime | TraccarIngestionService |
| `ShipmentsImported` | `App\Domain\Event` | importRunId, customerId, createdCount, skippedCount | ShipmentCsvImporter |

### A.3 — Event Listeners (side-effects desacoplados)

| Listener | Escucha | Acción |
|----------|---------|--------|
| `AuditDeliveryListener` | `StopDelivered`, `StopExceptionReported` | Escribe AuditLog (extrae lógica de DriverApiController) |
| `NotifyDeliveryListener` | `StopDelivered`, `StopExceptionReported` | Llama a NotificationService / WebhookNotificationService |
| `MercureRouteProgressListener` | `StopDelivered`, `StopExceptionReported`, `RouteStarted`, `RouteCompleted` | Publica actualización Mercure a topic del customer |
| `MercurePositionListener` | `VehiclePositionReceived` | Publica posición a Mercure (extrae de TraccarIngestionService) |

---

## Fase B — Application Services

### B.1 — `DeliveryService` (PRIORIDAD ALTA)

**Extrae de:** `DriverApiController::deliver()` + `exception()`

```php
namespace App\Application\Delivery;

final readonly class DeliveryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DriverActionService $driverActionService,
        private DeliveryEvidenceFactory $evidenceFactory,
        private EventDispatcherInterface $eventDispatcher,
        private RouteStopRepository $stopRepo,
        private ShipmentRepository $shipmentRepo,
    ) {}

    /** @throws StopNotFoundException|AccessDeniedException|IdempotentActionException */
    public function deliverStop(string $stopPublicId, DeliverStopInput $input, User $driver): DeliveryResult;

    /** @throws StopNotFoundException|AccessDeniedException|IdempotentActionException */
    public function reportException(string $stopPublicId, ExceptionStopInput $input, User $driver): ExceptionResult;
}
```

**DeliveryResult** y **ExceptionResult**: DTOs en `App\Application\Delivery\` con los datos de la operación.

**Lógica extraída:**
- Verificación de ownership (driver → route)
- Registro de idempotencia via DriverActionService
- Validación de `confirmedByDriver`
- Creación de Pod entity
- Creación de ShipmentEvent entity
- Mutación de RouteStop (markDelivered/markException)
- Dispatch de `StopDelivered` / `StopExceptionReported`

**El controller queda:**
```php
public function deliver(string $stopPublicId, Request $request): JsonResponse
{
    $input = DeliverStopInput::fromArray($this->decodePayload($request));
    // validate $input...
    $result = $this->deliveryService->deliverStop($stopPublicId, $input, $this->getUser());
    return $this->json($result->toArray());
}
```

### B.2 — `RouteLifecycleService`

**Extrae de:** `DriverApiController::start()` + `finish()`

```php
namespace App\Application\Route;

final readonly class RouteLifecycleService
{
    public function startRoute(string $routePublicId, User $driver): Route;
    public function finishRoute(string $routePublicId, User $driver): Route;
}
```

**Lógica extraída:**
- Verificación de ownership (driver)
- Mutación de estado de Route
- Dispatch de `RouteStarted` / `RouteCompleted`

### B.3 — `RoutePlanningService`

**Extrae de:** `RouteAdminController` + `RouteOptimizationApiController`

```php
namespace App\Application\Route;

final readonly class RoutePlanningService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouteBuilder $routeBuilder,
        private RouteOptimizationService $optimizationService,
        private RouteCapacityValidator $capacityValidator,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function buildRoutes(BuildRoutesInput $input): BuildRoutesResult;
    public function optimizeRoute(string $routePublicId, bool $preview = true): OptimizationResult;
    public function addStop(string $routePublicId, AddStopInput $input): RouteStop;
    public function removeStop(string $routePublicId, string $stopPublicId): void;
    public function reorderStops(string $routePublicId, array $order): void;
    public function syncOriginStop(Route $route): void;
}
```

**Lógica extraída:**
- `createOriginStopIfNeeded` / `syncOriginStop` de RouteAdminController
- Carga de entities por publicId + buildRoutes de RouteOptimizationApiController
- Optimización con preview/confirm
- Reorder de stops
- Dispatch de `RouteOptimized` / `RoutesBuilt`

### B.4 — `FleetOverviewService`

**Extrae de:** `FleetMapController` + `CustomerDashboardController`

```php
namespace App\Application\Fleet;

final readonly class FleetOverviewService
{
    public function getFleetMapData(User $user): FleetMapData;
    public function getFleetSummary(): FleetSummary;
    public function getCustomerKpis(Customer $customer): CustomerKpis;
    public function getActiveRoutesProgress(Customer $customer, int $limit = 5): array;
}
```

**DTOs de salida:** `FleetMapData`, `FleetSummary`, `CustomerKpis` en `App\Application\Fleet\`.

**Lógica extraída:**
- Las 5-6 queries DQL agregadas de CustomerDashboardController (eliminando duplicación dashboard/kpis)
- El ensamblaje de datos del mapa de FleetMapController
- El summary de FleetMapController

### B.5 — `PublicTrackingService`

**Extrae de:** `PublicTrackingController::track()`

```php
namespace App\Application\Tracking;

final readonly class PublicTrackingService
{
    public function trackByToken(string $trackingToken): ?TrackingInfo;
}
```

**TrackingInfo** DTO con: shipment data, events timeline, anonymized position, route stop info.

**Lógica extraída:**
- Validación de formato de token
- Desactivación controlada del tenant filter
- Anonimización de coordenadas
- Ensamblaje de timeline

---

## Fase C — Refactor Controllers

Cada controller se simplifica a: **validar input → llamar application service → devolver response**.

| Controller | Antes | Después |
|-----------|-------|---------|
| `DriverApiController::deliver` | ~80 líneas con Pod, ShipmentEvent, audit inline | ~10 líneas: decode → validate → `deliveryService->deliverStop()` → json |
| `DriverApiController::exception` | ~60 líneas similares | ~10 líneas: decode → validate → `deliveryService->reportException()` → json |
| `DriverApiController::start/finish` | ~25 líneas cada uno | ~8 líneas: `routeLifecycle->startRoute()` → json |
| `RouteAdminController::optimize` | Duplicado con API controller | Ambos llaman `routePlanning->optimizeRoute()` |
| `RouteAdminController::addStop` | Cálculo de secuencia inline | `routePlanning->addStop()` |
| `RouteAdminController::edit` | `syncOriginStop` inline | `routePlanning->syncOriginStop()` |
| `RouteOptimizationApiController::buildRoutes` | Carga entities + delegación | `routePlanning->buildRoutes()` |
| `FleetMapController::__invoke` | ~60 líneas de queries | `fleetOverview->getFleetMapData()` |
| `FleetMapController::summary` | 3 queries inline | `fleetOverview->getFleetSummary()` |
| `CustomerDashboardController` | 5 queries duplicadas | `fleetOverview->getCustomerKpis()` + `getActiveRoutesProgress()` |
| `PublicTrackingController::track` | Tenant bypass + anonimización | `publicTracking->trackByToken()` |

---

## Fase D — Adaptar Commands existentes

Con los Application Services disponibles, los commands que dupliquen lógica pueden reutilizarlos:

| Command | Cambio |
|---------|--------|
| `SmokeCsvImportCommand` | Ya usa `ShipmentCsvImporter` — sin cambio necesario |
| `TraccarStreamCommand` | Ya usa `TraccarIngestionService` — añadir dispatch de `VehiclePositionReceived` dentro del service |

---

## Orden de implementación

| Step | Qué | Ficheros nuevos | Ficheros modificados |
|------|-----|-----------------|---------------------|
| 1 | Domain Events (A.2) | 8 event classes en `src/Domain/Event/` | — |
| 2 | DeliveryService (B.1) + DTOs | 3 ficheros en `src/Application/Delivery/` | — |
| 3 | Refactor DriverApiController deliver/exception (C) | — | `DriverApiController.php` |
| 4 | Event Listeners (A.3) | 4 listeners en `src/EventListener/Domain/` | — |
| 5 | RouteLifecycleService (B.2) | 1 fichero en `src/Application/Route/` | — |
| 6 | Refactor DriverApiController start/finish (C) | — | `DriverApiController.php` |
| 7 | RoutePlanningService (B.3) + DTOs | 4 ficheros en `src/Application/Route/` | — |
| 8 | Refactor RouteAdminController + RouteOptimizationApiController (C) | — | 2 controllers |
| 9 | FleetOverviewService (B.4) + DTOs | 4 ficheros en `src/Application/Fleet/` | — |
| 10 | Refactor FleetMapController + CustomerDashboardController (C) | — | 2 controllers |
| 11 | PublicTrackingService (B.5) + DTO | 2 ficheros en `src/Application/Tracking/` | — |
| 12 | Refactor PublicTrackingController (C) | — | 1 controller |
| 13 | VehiclePositionReceived en TraccarIngestionService (D) | — | 1 service |

**Total estimado: ~30 ficheros nuevos, ~7 ficheros modificados.**

---

## Principios

1. **Un Application Service = un bounded context** (Delivery, Route, Fleet, Tracking)
2. **Domain Events son inmutables** — solo datos, no lógica
3. **Side-effects van en listeners** — nunca en el application service
4. **Controllers solo orquestan** — input → service → output
5. **Application Services no dependen de HTTP** — no Request, no Response, no session
6. **Exceptions tipadas** para errores de negocio (`StopNotFoundException`, `RouteNotOwnedException`, etc.)
