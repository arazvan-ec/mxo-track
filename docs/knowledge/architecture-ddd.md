# Arquitectura DDD/SOLID

**Última actualización:** 2026-03-16
**Estado:** Vigente

## Principio

Las reglas obligatorias de SOLID y DDD están en **CLAUDE.md** (secciones "SOLID Principles" y "DDD Architecture"). Este módulo es referencia complementaria: análisis de acoplamiento, ejemplos de código, y patrones detallados.

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
| Repositorios (`src/Repository/`) | Clases concretas Doctrine, casi sin interfaces de dominio (solo `RouteSnapshotRepositoryInterface` existe) | DIP |
| Servicios de aplicación | Dependen de `EntityManagerInterface` directamente, `$em->persist()`, `$em->flush()` | DIP |
| Traits (PublicIdTrait, SoftDeleteTrait) | Contienen `#[ORM\...]` attributes | SRP |

## Detalle de Bounded Contexts

### Contextos Críticos — razón para DDD puro

| Bounded Context | Entidades Core | Razón | Estado migración |
|----------------|---------------|-------|-----------------|
| **Route Planning** | Route, RouteStop, RouteSnapshot, RouteEvent | Corazón del negocio — km y tiempo ahorrados | **[PARTIAL]** — RouteSnapshot y modelos de vista (RouteMapView, StopMapView, etc.) son POPOs en `src/Domain/Route/Model/`; RouteSnapshotRepositoryInterface existe en Domain. Pero Route, RouteStop y RouteEvent siguen en `src/Entity/` con ORM attributes. |
| **Shipment/Delivery** | Shipment, Parcel, DeliveryEvidence, POD | Flujo operativo principal | **[PLANNED]** — Todas las entidades siguen en `src/Entity/` con ORM. Sin interfaces de repositorio en Domain. |
| **Route Optimization** | (ya bien separado via port interfaces) | Solo falta repository interfaces | **[PARTIAL]** — Port interfaces existen; repository interfaces en Domain aún no (solo RouteSnapshotRepositoryInterface). |

### Contextos CRUD — razón para quedarse pragmático

| Bounded Context | Entidades | Razón |
|----------------|-----------|-------|
| **Identity/Auth** | User | Acoplado a Symfony Security por diseño |
| **Tenant Management** | Customer, CustomerIntegration | CRUD simple, poco comportamiento de dominio |
| **Fleet** | Vehicle, Driver, GpsDevice | Mayormente datos de configuración |
| **Notifications** | Notification, RealtimeEvent | Infraestructura de comunicación |

## Checklist de Migración por Sprint

Cada sprint de migración de un contexto crítico incluye:
- [ ] Extraer repository interfaces al dominio
- [ ] Crear implementaciones Doctrine que implementen las interfaces
- [ ] Migrar servicios para depender de interfaces, no de concretos
- [ ] Extraer entidad a POPO + mapping externo
- [ ] Tests unitarios sin Doctrine para la lógica de dominio

## Patrones de Código

### Repository Interface (Domain Layer)

**[PLANNED]** — Este ejemplo muestra el patrón objetivo. Actualmente solo existe `RouteSnapshotRepositoryInterface` en Domain; `RouteRepositoryInterface` aún no existe.

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

**[PLANNED]** — No existe aún. Ejemplo del patrón objetivo.

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

**[PLANNED]** — Ejemplo del patrón objetivo. Los servicios actuales dependen de repositorios concretos Doctrine, no de interfaces de dominio.

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

**[PLANNED]** — Route sigue en `src/Entity/Route.php` con ORM attributes. Este ejemplo muestra el estado objetivo. Los únicos POPOs de dominio actuales en `src/Domain/Route/Model/` son RouteSnapshot, RouteMapView, StopMapView, RouteMapMetrics, RouteMapOptions y RouteMapTiming.

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

**[PLANNED]** — No existe mapping externo aún. Todas las entidades usan ORM attributes inline.

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


## Historial

- 2026-03-16: Creación inicial — análisis de acoplamiento, clasificación de contextos, patrones de migración
- 2026-03-16: SOLID y reglas DDD movidos a CLAUDE.md (instrucciones de comportamiento); módulo reducido a referencia (ejemplos, tablas, patrones de código)
