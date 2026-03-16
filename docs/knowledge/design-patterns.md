# Design Patterns

**Última actualización:** 2026-03-16
**Estado:** Vigente

Las reglas obligatorias de cuándo usar cada patrón están en **CLAUDE.md > "Design Patterns (mandatory)"**. Este módulo es referencia con ejemplos de código del codebase.

## Catálogo de Patrones en Uso

### Creacionales

#### Factory Method

Patrón más importante del proyecto. Habilita providers pluggables per-tenant.

```php
// Interface — src/Provider/ProviderFactoryInterface.php
interface ProviderFactoryInterface
{
    public function create(array $config): object;
    public function getProviderType(): string;
    public function getServiceType(): ServiceType;
}

// Implementación — src/Provider/RouteOptimizer/VroomFactory.php
#[AutoconfigureTag('app.provider_factory')]
final class VroomFactory implements ProviderFactoryInterface { ... }

// Registry — src/Provider/ProviderFactoryRegistry.php
// Auto-colecta factories via tag, las indexa por type
```

**12 factories:** VroomFactory, GreedyOptimizerFactory, OsrmFactory, GoogleDirectionsFactory, TraccarFactory, WebhookGpsFactory, MercureFactory, HttpPollingFactory, TwilioSmsTransportFactory, NullSmsTransportFactory, DeliveryEvidenceFactory, MercureJwtFactory.

#### Builder

Encapsula construcción compleja de objetos en fases.

```php
// src/Service/RouteBuilder.php
// Fases: mapear entidades → value objects optimizables → llamar optimizer → materializar Route + RouteStops
```

**En uso:** RouteBuilder (entidades → OptimizableJob/Vehicle → optimization → Route), DemoScenarioBuilder.

#### Null Object

Implementaciones safe-default para cuando un provider no está disponible. **Evita null checks en todo el código.**

```php
// src/RouteOptimization/NullRouteOptimizer.php
final readonly class NullRouteOptimizer implements RouteOptimizerInterface
{
    public function optimize(array $vehicles, array $jobs): OptimizationResult
    {
        return new OptimizationResult(routes: [], unassigned: $jobs);
    }
}
```

**12 Null Objects:** NullRouteOptimizer, NullGpsProvider, NullRoutingEngine, NullPublisher, NullTokenProvider, NullGeocoder, NullDemandForecaster, NullEtaPredictor, NullAnomalyDetector, NullLlmClient, NullEmbeddingClient, NullSmsTransport.

---

### Estructurales

#### Adapter

Envuelve APIs externas para conformar con port interfaces del dominio.

```php
// src/Provider/RouteOptimizer/VroomRouteOptimizer.php
// Adapta la API HTTP de VROOM a RouteOptimizerInterface del dominio

// src/Provider/Routing/GoogleDirectionsEngine.php
// Adapta Google Directions API a RoutingEngineInterface del dominio
```

**Adapters:** VROOM → RouteOptimizerInterface, Google → RoutingEngineInterface, Traccar → GpsDeviceProviderInterface, OSRM → RoutingEngineInterface.

#### Proxy (Transparent)

Proxies multi-tenant que interceptan llamadas y resuelven el provider correcto para el tenant actual.

```php
// src/Provider/Gps/TenantAwareGpsProvider.php
final readonly class TenantAwareGpsProvider implements GpsDeviceProviderInterface
{
    public function __construct(
        private ProviderResolverInterface $resolver,
        private TenantContext $tenantContext,
    ) {}

    public function getDevices(): array
    {
        return $this->resolve()->getDevices();
    }

    private function resolve(): GpsDeviceProviderInterface
    {
        return $this->resolver->resolve(ServiceType::Gps, $this->tenantContext->getCustomer());
    }
}
```

**4 proxies:** TenantAwareGpsProvider, TenantAwareRoutingEngine, TenantAwareRouteOptimizer, TenantAwareRealtimePublisher.

#### Decorator

Añade comportamiento (caching) sin modificar la clase original.

```php
// src/Provider/CachedProviderResolver.php
// Envuelve ProviderResolverInterface con cache keyed por "{serviceType}:{customerId}"
```

#### Facade

Servicios de alto nivel que simplifican subsistemas complejos.

```php
// src/Application/Route/RoutePlanningService.php
// Orquesta: RouteBuilder + RouteOptimizationService + RouteCapacityValidator
//           + RouteSnapshotManager + EventDispatcher

// src/Application/Delivery/DeliveryService.php
// Orquesta: DriverActionService + DeliveryEvidenceFactory + AuditLogger
//           + EventDispatcher + MessageBus
```

**Facades:** RoutePlanningService, RouteLifecycleService, DeliveryService, PublicTrackingService, FleetOverviewService.

---

### De Comportamiento

#### Observer / Domain Events

13 domain events como POPOs inmutables + 13 listeners + 13 subscribers.

```php
// src/Domain/Event/StopDelivered.php — POPO inmutable
final readonly class StopDelivered
{
    public function __construct(
        public string $stopPublicId,
        public string $shipmentPublicId,
        public string $routePublicId,
        public int $driverUserId,
        public string $podPublicId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}

// Listener — src/EventListener/Domain/NotifyDeliveryListener.php
#[AsEventListener(event: StopDelivered::class)]
final class NotifyDeliveryListener { ... }
```

**Eventos:** RouteStarted, RouteCompleted, RouteCancelled, RouteAssigned, RoutesBuilt, RouteOptimized, StopDelivered, StopExceptionReported, ShipmentsImported, VehiclePositionReceived, DeviationDetected, DeviationEnded, EtaChanged.

#### Strategy

Múltiples algoritmos intercambiables para el mismo problema.

```php
// Port — src/RouteOptimization/RouteOptimizerInterface.php
interface RouteOptimizerInterface
{
    public function optimize(array $vehicles, array $jobs): OptimizationResult;
}

// Estrategias: VroomRouteOptimizer (metaheurístico), GreedyOptimizer (nearest-neighbor), NullRouteOptimizer (fallback)
```

**Familias de estrategias:** Optimization (3), Routing (3), GPS (3), Realtime (3), SMS (2), LLM (2), Embedding (2).

#### Command (via Messenger)

Encapsula operaciones async como objetos mensaje.

```php
// src/Message/PostRouteAnalysisMessage.php
final readonly class PostRouteAnalysisMessage
{
    public function __construct(public string $routePublicId) {}
}

// src/MessageHandler/PostRouteAnalysisHandler.php
#[AsMessageHandler]
final class PostRouteAnalysisHandler { ... }
```

**4 comandos async:** EnrichRouteNotesMessage, PostRouteAnalysisMessage, FleetAnomalyCheckMessage, NlpClassificationMessage.

#### Chain of Responsibility

Cadena de fallback cuando un provider falla.

```php
// src/Provider/FallbackChain.php
// Itera proveedores en orden, captura ProviderUnavailableException, intenta el siguiente
```

#### Template Method

Clase base con hooks abstractos para subclases.

```php
// src/Security/Voter/BaseVoter.php
// final voteOnAttribute() maneja: extracción de user, bypass admin
// delega a abstract isGrantedForUser() para reglas específicas del dominio
```

---

### Patrones DDD

#### Repository

17 repositorios extienden `ServiceEntityRepository`. Encapsulan persistencia por aggregate root.

**Nota:** Actualmente son clases concretas Doctrine. La migración DDD incluye extraer interfaces al dominio (ver CLAUDE.md > DDD Architecture).

#### Value Object

Objetos inmutables que representan conceptos de dominio sin identidad propia.

```php
// src/RouteOptimization/OptimizableJob.php (readonly)
// src/Routing/Coordinate.php
// src/Dto/DeviationCheckResult.php
// + 17 enums: RouteStatus, ShipmentPriority, VehicleSkill, ExceptionCode, etc.
// + 85+ readonly classes/structs en el codebase
```

#### Aggregate Root

Entidades raíz que controlan la consistencia de sus hijos.

- **Route** → RouteStop[] (OneToMany, cascade persist/remove)
- **Shipment** → Parcel[] (OneToMany)
- **Customer** → configuración y relaciones

---

## Patrones Ausentes — Candidatos para Implementar

### Specification

**Problema actual:** Lógica de queries compleja dispersa en repositorios y servicios con `QueryBuilder` ad-hoc.

```php
// ACTUAL (disperso):
$qb->where('s.status = :status')->andWhere('s.priority > :min')...

// CON SPECIFICATION:
interface Specification { public function toExpression(QueryBuilder $qb): Expr; }

class UndeliveredHighPriority implements Specification
{
    public function toExpression(QueryBuilder $qb): Expr
    {
        return $qb->expr()->andX(
            $qb->expr()->eq('s.status', ':pending'),
            $qb->expr()->gt('s.priority', ':minPriority')
        );
    }
}
```

**Cuándo implementar:** Cuando las queries se vuelvan complejas y combinables.

### State

**Problema actual:** Transiciones de estado via enums con guards en métodos de entidad. Funciona pero no escala si las transiciones se complican.

```php
// ACTUAL:
public function start(): void {
    if ($this->status !== RouteStatus::PLANNED) throw ...;
    $this->status = RouteStatus::ACTIVE;
}

// CON STATE PATTERN (si se necesita):
interface RouteState { public function start(Route $route): RouteState; }
class PlannedState implements RouteState { ... }
class ActiveState implements RouteState { ... }
```

**Cuándo implementar:** Si las transiciones de estado requieren side-effects complejos o reglas condicionales.

### Policy

**Problema actual:** Reglas de negocio (umbrales de anomalía, triggers de re-optimización) hardcodeadas en servicios.

```php
// CON POLICY:
interface ReoptimizationPolicy {
    public function shouldReoptimize(Route $route, RouteEvent $event): bool;
}

class ConsecutiveExceptionsPolicy implements ReoptimizationPolicy {
    public function shouldReoptimize(Route $route, RouteEvent $event): bool {
        return $route->getConsecutiveExceptions() >= 2;
    }
}
```

**Cuándo implementar:** Al definir la política de re-optimización (backlog CLAUDE.md).

### Saga / Process Manager

**Problema actual:** Workflows multi-paso (creación ruta → optimización → asignación → activación) orquestados implícitamente por servicios facade.

**Cuándo implementar:** Si los workflows necesitan compensación (rollback parcial) o coordinación entre bounded contexts.

## Historial

- 2026-03-16: Creación inicial — catálogo completo de 15 patrones en uso + 4 candidatos, con ejemplos del codebase
