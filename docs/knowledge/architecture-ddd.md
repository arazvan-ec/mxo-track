# Arquitectura DDD/SOLID

**Última actualización:** 2026-03-16
**Estado:** Vigente

## Principio

El codebase migra progresivamente hacia DDD con pureza híbrida: **contextos críticos van DDD puro, contextos CRUD se quedan pragmáticos Symfony.** Todo código nuevo sigue DDD desde el inicio.

## Principios SOLID en Este Codebase

Cada principio tiene ejemplos concretos de violaciones actuales y buenas prácticas ya existentes. **Todo código nuevo debe cumplir los 5 principios.**

### S — Single Responsibility (Responsabilidad Única)

**Una clase debe tener una sola razón para cambiar.**

**Violación actual:** `src/Entity/User.php` maneja 5 responsabilidades:
1. Identidad (email, nombre)
2. Credenciales de autenticación (password)
3. Roles de seguridad (getRoles(), assignRole())
4. Scoping multi-tenant (relación con Customer)
5. Lifecycle de persistencia (#[ORM\PrePersist])

Cada una es una "razón para cambiar" diferente. Si cambia la política de roles, hay que tocar la misma clase que si cambia el esquema de persistencia.

**Buen ejemplo:** `src/Domain/Event/StopDelivered.php` — un POPO inmutable con un solo trabajo: transportar datos del evento. Sin lógica de negocio, sin persistencia, sin validación.

**Regla para código nuevo:**
- Entidades: solo estado de dominio + transiciones de estado (start(), finish(), markDelivered())
- Persistencia: en Infrastructure (mapping externo, repositories)
- Validación: en Value Objects (auto-validación en constructor) o Application layer (DTOs con Validator)
- Seguridad: en Security layer (voters, authenticators), no en la entidad

### O — Open/Closed (Abierto/Cerrado)

**Abierto para extensión, cerrado para modificación.**

**Buen ejemplo (ya en el codebase):** El Provider Framework es ejemplar:
- `ProviderFactoryInterface` define el contrato
- `ProviderFactoryRegistry` auto-registra factories via `#[AutoconfigureTag('app.provider_factory')]`
- Para añadir un nuevo optimizer: crear una clase que implemente `ProviderFactoryInterface` → Symfony la registra automáticamente → **cero cambios en código existente**

**Violación a evitar:**
```php
// MAL: Cerrado para extensión, requiere modificación
if ($type === 'vroom') {
    return new VroomFactory(...);
} elseif ($type === 'greedy') {
    return new GreedyFactory(...);
}
// Añadir un tercer optimizer requiere modificar este if/else
```

**Regla para código nuevo:**
- Cuando haya múltiples implementaciones posibles → interface + registry o tagged services
- Nunca if/switch sobre tipos para seleccionar implementación → usar polimorfismo
- Nuevas funcionalidades se añaden con nuevas clases, no modificando las existentes

### L — Liskov Substitution (Sustitución de Liskov)

**Las subclases deben poder sustituir a sus clases base sin romper el comportamiento.**

**Violación actual:** `src/Provider/Gps/WebhookGpsProvider.php` implementa `GpsDeviceProviderInterface` pero:
- `login()` → no-op (no hace nada)
- `getSessionCookie()` → siempre retorna null
- `getDevices()` → siempre retorna array vacío

Código que depende de `GpsDeviceProviderInterface` no puede sustituir `WebhookGpsProvider` sin comprobar qué implementación es. **Esto rompe LSP.**

**Buen ejemplo:** `src/Tracking/TraccarGpsProvider.php` cumple el contrato completo: login() autentica, getSessionCookie() retorna cookie real, getDevices() lista dispositivos reales.

**Cómo se arregla (deuda técnica documentada):** Separar la interface en dos:
```php
// Interface para providers push-based (webhook)
interface GpsPositionReceiverInterface {
    public function receivePosition(PositionPayload $payload): void;
    public function isAvailable(): bool;
}

// Interface para providers pull-based (Traccar)
interface GpsDeviceManagerInterface extends GpsPositionReceiverInterface {
    public function login(string $server, string $user, string $pass): void;
    public function getSessionCookie(): ?string;
    public function getDevices(): array;
}
```

**Regla para código nuevo:**
- Si una implementación necesita stubs o no-ops para métodos de la interface → la interface es demasiado amplia → dividirla
- Todas las implementaciones deben cumplir el contrato **completo** de la interface
- Nunca `throw new \RuntimeException('Not supported')` en un método de interface

### I — Interface Segregation (Segregación de Interfaces)

**Los clientes no deben depender de interfaces que no usan.**

**Buen ejemplo (ya en el codebase):**
- `CustomerScopedEntityInterface` → 1 solo método: `getCustomer(): Customer`
- `SoftDeletableInterface` → 3 métodos cohesivos: `getDeletedAt()`, `isDeleted()`, `softDelete()`

Cada interface es estrecha y enfocada. Un cliente que solo necesita filtrar por tenant depende solo de `CustomerScopedEntityInterface`, no de una interface gorda con 20 métodos.

**Violación a evitar:**
```php
// MAL: Interface gorda que obliga a implementar métodos irrelevantes
interface VehicleServiceInterface {
    public function getPosition(): Position;
    public function getMaintenanceHistory(): array;
    public function calculateFuelCost(): Money;
    public function sendNotification(): void;  // ¿Por qué el vehículo envía notificaciones?
}
```

**Regla para código nuevo:**
- Interfaces pequeñas y cohesivas (1-5 métodos relacionados)
- Si una implementación tiene stubs → la interface es demasiado amplia → ISP + LSP violados juntos
- Preferir composición de interfaces: `class TraccarProvider implements GpsPositionReceiver, GpsDeviceManager`
- Interfaces marker (sin métodos) son aceptables para patterns como multi-tenancy scoping

### D — Dependency Inversion (Inversión de Dependencias)

**Los módulos de alto nivel no deben depender de módulos de bajo nivel. Ambos deben depender de abstracciones.**

**Violación actual:** `src/Application/Delivery/DeliveryService.php` depende de concretos:
```php
public function __construct(
    private EntityManagerInterface $em,           // ← Doctrine directo
    private RouteStopRepository $stopRepo,        // ← Repositorio concreto
    private ShipmentRepository $shipmentRepo,     // ← Repositorio concreto
) {}
```

El servicio de alto nivel (lógica de delivery) depende directamente de módulos de bajo nivel (Doctrine repositories). Imposible testear sin Doctrine, imposible cambiar persistence.

**Buen ejemplo (ya en el codebase):** `src/Service/RouteOptimizationService.php`:
```php
public function __construct(
    private readonly RouteOptimizerInterface $routeOptimizer,   // ← Abstracción
    private readonly RoutingEngineInterface $routingEngine,     // ← Abstracción
) {}
```

Depende de port interfaces. Funciona con VroomOptimizer, GreedyOptimizer, NullOptimizer o cualquier implementación futura.

**Regla para código nuevo:**
- Servicios de dominio y aplicación → dependen de interfaces definidas en Domain layer
- Infrastructure implementa las interfaces → Doctrine repositories, API clients, etc.
- La flecha de dependencia siempre apunta hacia el dominio:

```
Controller → Application Service → Domain Interface ← Infrastructure Implementation
              (alto nivel)           (abstracción)       (bajo nivel)
```

- `EntityManagerInterface` en servicios → prohibido en contextos críticos. Usar `RepositoryInterface::save()`
- En contextos CRUD/pragmáticos → aceptable depender de repositorios concretos Symfony

---

## Estado Actual del Acoplamiento

### Lo que ya está bien separado

| Componente | Patrón | Estado |
|-----------|--------|--------|
| Domain Events (`src/Domain/Event/`) | POPOs puros sin dependencias de framework | Excelente |
| Port Interfaces (RouteOptimizer, GPS, Realtime) | Interfaces hexagonales con múltiples implementaciones | Excelente |
| Provider Framework | Pluggable per-tenant via ProviderResolver | Excelente |
| Enums de dominio (RouteStatus, ShipmentPriority, etc.) | Value Objects nativos PHP 8.1 | Bueno |
| State transitions en entidades (start(), finish(), markDelivered()) | Lógica de negocio en entidad | Bueno |

### Lo que está acoplado a infraestructura

| Componente | Problema | Violación SOLID |
|-----------|----------|-----------------|
| Entidades (`src/Entity/`) | Doctrine ORM attributes embebidos, Symfony Security interfaces (User), Validator constraints | SRP, DIP |
| Repositorios (`src/Repository/`) | Clases concretas Doctrine, sin interfaces de dominio | DIP |
| Servicios de aplicación | Dependen de `EntityManagerInterface` directamente, `$em->persist()`, `$em->flush()` | DIP |
| Traits (PublicIdTrait, SoftDeleteTrait) | Contienen `#[ORM\...]` attributes | SRP |

## Clasificación de Bounded Contexts

### Contextos Críticos → DDD Puro

Estos contextos contienen la lógica de negocio que genera valor (km y tiempo ahorrados). Deben estar completamente desacoplados de infraestructura.

| Bounded Context | Entidades Core | Prioridad de migración |
|----------------|---------------|----------------------|
| **Route Planning** | Route, RouteStop, RouteSnapshot, RouteEvent | Alta — corazón del negocio |
| **Shipment/Delivery** | Shipment, Parcel, DeliveryEvidence, POD | Alta — flujo operativo principal |
| **Route Optimization** | (ya bien separado via port interfaces) | Baja — solo falta repository interfaces |

### Contextos CRUD → Pragmático Symfony

Estos contextos son infraestructura de soporte. El costo de DDD puro no justifica el beneficio.

| Bounded Context | Entidades | Razón para quedarse pragmático |
|----------------|-----------|-------------------------------|
| **Identity/Auth** | User | Acoplado a Symfony Security por diseño; la abstracción añadiría complejidad sin beneficio real |
| **Tenant Management** | Customer, CustomerIntegration | CRUD simple con poco comportamiento de dominio |
| **Fleet** | Vehicle, Driver, GpsDevice | Mayormente datos de configuración |
| **Notifications** | Notification, RealtimeEvent | Infraestructura de comunicación |

## Cuándo Aplicar DDD

### Regla 1: Código nuevo → siempre DDD

Todo código nuevo en contextos críticos DEBE seguir la estructura DDD:

```
src/Domain/{BoundedContext}/
├── Model/           # Entidades puras (POPOs), Value Objects
├── Repository/      # Interfaces de repositorio
├── Service/         # Domain services (lógica que no pertenece a una entidad)
└── Event/           # Domain events (ya existe src/Domain/Event/)

src/Infrastructure/{BoundedContext}/
├── Doctrine/        # Implementaciones de repositorio, mapping XML/PHP
├── Symfony/         # Controllers, commands, listeners específicos de framework
└── External/        # Adapters a servicios externos
```

### Regla 2: Migración planificada por sprints

Los contextos críticos se migran en sprints dedicados, ordenados por prioridad:

1. **Route Planning** — Extraer Route, RouteStop, RouteSnapshot, RouteEvent a modelos puros
2. **Shipment/Delivery** — Extraer Shipment, Parcel, DeliveryEvidence a modelos puros
3. **Revisar** — Evaluar si más contextos necesitan migración

Cada sprint de migración incluye:
- [ ] Extraer repository interfaces al dominio
- [ ] Crear implementaciones Doctrine que implementen las interfaces
- [ ] Migrar servicios para depender de interfaces, no de concretos
- [ ] Si el contexto es DDD puro: extraer entidad a POPO + mapping externo
- [ ] Tests unitarios sin Doctrine para la lógica de dominio

### Regla 3: Al tocar código acoplado en contexto crítico

Si una feature nueva requiere modificar código acoplado en un contexto crítico, **primero refactorizar la parte que necesitas**:

1. Extraer la interface de repositorio que vas a necesitar
2. Crear la implementación Doctrine
3. Cambiar el servicio para depender de la interface
4. Implementar tu feature contra la interface

No intentar migrar todo el contexto — solo lo que necesitas para tu feature.

## Patrones a Seguir

### Repository Interface (Domain Layer)

```php
// src/Domain/Route/Repository/RouteRepositoryInterface.php
namespace App\Domain\Route\Repository;

use App\Domain\Route\Model\Route;

interface RouteRepositoryInterface
{
    public function findByPublicId(string $publicId): ?Route;
    public function save(Route $route): void;
    public function remove(Route $route): void;
}
```

### Repository Implementation (Infrastructure Layer)

```php
// src/Infrastructure/Route/Doctrine/DoctrineRouteRepository.php
namespace App\Infrastructure\Route\Doctrine;

use App\Domain\Route\Repository\RouteRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRouteRepository implements RouteRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findByPublicId(string $publicId): ?Route
    {
        return $this->em->getRepository(Route::class)
            ->findOneBy(['publicId' => Ulid::fromString($publicId)]);
    }

    public function save(Route $route): void
    {
        $this->em->persist($route);
        $this->em->flush();
    }

    public function remove(Route $route): void
    {
        $this->em->remove($route);
        $this->em->flush();
    }
}
```

### Service dependiendo de Interface

```php
// src/Application/Route/RouteLifecycleService.php
final readonly class RouteLifecycleService
{
    public function __construct(
        private RouteRepositoryInterface $routes,  // ← Interface, no concreto
        private EventDispatcherInterface $events,
    ) {}
}
```

### Entidad DDD Pura (contextos críticos)

```php
// src/Domain/Route/Model/Route.php
namespace App\Domain\Route\Model;

final class Route  // Sin #[ORM\Entity], sin Doctrine attributes
{
    private RouteStatus $status;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        private readonly string $publicId,
        private readonly Vehicle $vehicle,
        private readonly Driver $driver,
    ) {
        $this->status = RouteStatus::PLANNED;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function start(): void
    {
        if ($this->status !== RouteStatus::PLANNED) {
            throw new \DomainException('Route can only start from PLANNED status');
        }
        $this->status = RouteStatus::ACTIVE;
    }
}
```

Con mapping Doctrine separado:

```php
// src/Infrastructure/Route/Doctrine/Mapping/RouteMapping.php
// O via XML: config/doctrine/Route.orm.xml
```

### Entidad Pragmática Symfony (contextos CRUD)

```php
// src/Entity/User.php — Se queda como está
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Doctrine attributes inline — aceptable para contextos CRUD
}
```

## Anti-Patterns a Evitar

| Anti-Pattern | Por qué es malo | Qué hacer en su lugar |
|-------------|-----------------|----------------------|
| `$em->persist()` en servicios de dominio | Acopla lógica de negocio a persistencia | Usar `RepositoryInterface::save()` |
| `$em->getRepository()->createQueryBuilder()` en servicios | Queries SQL en capa de aplicación | Método en RepositoryInterface con nombre de dominio |
| Validator constraints en entidades DDD puras | Mezcla validación de framework con dominio | Validación en Value Objects o Domain Services |
| `EntityManagerInterface` en constructor de servicios | Dependencia directa a Doctrine | Depender solo de RepositoryInterface |
| Lifecycle callbacks (`#[ORM\PrePersist]`) en entidades DDD | El ORM controla el ciclo de vida del dominio | Timestamps en constructor o domain service |

## Checklist para Code Review

Al revisar código nuevo o refactorizado, verificar:

### SOLID
- [ ] **SRP:** ¿Cada clase tiene una sola razón para cambiar? Entidades no mezclan persistencia + validación + seguridad.
- [ ] **OCP:** ¿Se puede extender sin modificar? Nuevas implementaciones no requieren cambios en código existente.
- [ ] **LSP:** ¿Todas las implementaciones cumplen el contrato completo de su interface? Sin stubs ni no-ops.
- [ ] **ISP:** ¿Las interfaces son estrechas y cohesivas? Ningún implementador tiene métodos que no necesita.
- [ ] **DIP:** ¿Los servicios dependen de abstracciones? No de `EntityManagerInterface`, no de repositorios concretos.

### DDD
- [ ] **¿En qué contexto está?** Crítico → DDD puro. CRUD → Pragmático Symfony.
- [ ] **¿Las entidades DDD son POPOs?** Sin `#[ORM\...]`, sin `UserInterface`, sin Validator constraints.
- [ ] **¿El domain event es un POPO?** Sin dependencias de Symfony/Doctrine.
- [ ] **¿Se puede testear sin base de datos?** La lógica de dominio debe ser testeable con unit tests puros.
- [ ] **¿La flecha de dependencia apunta al dominio?** Controller → App → Domain ← Infrastructure.

## Historial

- 2026-03-16: Creación inicial — análisis de acoplamiento, clasificación de contextos, patrones de migración
- 2026-03-16: Añadida sección completa de principios SOLID con ejemplos concretos del codebase (violaciones actuales + buenas prácticas), reglas para código nuevo, y checklist SOLID para code review
