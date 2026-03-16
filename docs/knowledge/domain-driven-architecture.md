# Arquitectura Domain-Driven

**Última actualización:** 2026-03-16
**Estado:** Vigente

## Principio

Los servicios de aplicación dependen de **interfaces de dominio puro** (PHP sin framework), nunca directamente de entidades Doctrine o interfaces de Symfony. Esto desacopla la lógica de negocio de la infraestructura.

## Estructura de capas

```
┌─────────────────────────────────────────────────┐
│  Domain Layer  (App\Domain\Model\)               │
│  Interfaces puras PHP — sin Doctrine, sin Symfony │
│  Ejemplo: UserIdentity                            │
└──────────────────────┬──────────────────────────┘
                       │ extends
┌──────────────────────▼──────────────────────────┐
│  Infrastructure Bridge  (App\Security\, etc.)     │
│  Interfaces que unen dominio con framework        │
│  Ejemplo: AppUserInterface extends                │
│           UserIdentity + Symfony\UserInterface     │
└──────────────────────┬──────────────────────────┘
                       │ implements
┌──────────────────────▼──────────────────────────┐
│  Infrastructure  (App\Entity\, App\Repository\)   │
│  Entidades Doctrine, repositorios, providers      │
│  Ejemplo: UserAccount implements AppUserInterface  │
└─────────────────────────────────────────────────┘
```

## Regla de dependencias

| Capa | Puede depender de | NO puede depender de |
|------|-------------------|---------------------|
| Domain (`App\Domain\`) | Solo PHP nativo + otras interfaces de dominio | Symfony, Doctrine, entidades |
| Application Services (`App\Service\`, `App\Application\`) | Domain interfaces | Entidades Doctrine directamente* |
| Security/Infrastructure | Domain + Framework | — |
| Controllers | Framework + Domain (cast) | — |

*Excepción: servicios que persisten relaciones FK necesitan la clase concreta de la entidad. Ver sección "Cuándo usar clase concreta".

## Cuándo crear una interfaz de dominio

Evaluar durante brainstorming (antes de diseñar). Crear interfaz si:

1. **3+ servicios** dependerán del concepto (alta reutilización)
2. **Es un concepto core del negocio** (User, Route, Vehicle, Customer, Shipment)
3. **Tiene múltiples implementaciones** posibles (User vs ApiKeyUser)
4. **Servicios read-only** necesitan datos pero no persisten relaciones

**NO crear interfaz si:**
- Solo 1-2 servicios lo usan
- Es una entidad de soporte/auxiliar (VehicleCheckpoint, CsvImportRun)
- Todos los consumidores necesitan la clase concreta para FK

## Cuándo usar la clase concreta (entity)

Usar la entidad Doctrine directamente cuando:

1. **Se persisten relaciones FK** — `$route->setDriver($userAccount)` necesita la entidad
2. **Se crean instancias** — `new UserAccount($email)` en fixtures, commands
3. **Doctrine relations** — `targetEntity: UserAccount::class` en otras entidades
4. **Repository queries** — el repository trabaja con la entidad concreta
5. **Forms** — Symfony forms se vinculan a la entidad concreta

## Patrón aplicado: UserIdentity

```php
// Dominio (puro PHP)
namespace App\Domain\Model;
interface UserIdentity {
    public function getId(): ?string;
    public function getPublicIdString(): string;
    public function hasRole(string $role): bool;
    public function getCustomer(): ?Customer;
    public function isActive(): bool;
    public function getName(): ?string;
}

// Bridge (Symfony)
namespace App\Security;
interface AppUserInterface extends UserIdentity, UserInterface {}

// Implementaciones
class UserAccount implements AppUserInterface, PasswordAuthenticatedUserInterface {...}
class ApiKeyUser implements AppUserInterface {...}
```

**Quién depende de qué:**
- `SearchService`, `FleetOverviewService`, etc. → `UserIdentity`
- `BaseVoter`, `UserChecker`, etc. → `AppUserInterface`
- `RouteLifecycleService`, `DeliveryService`, etc. → `UserAccount` (FK)

## Cómo aplicar a un nuevo concepto de dominio

Ejemplo: si `Vehicle` empieza a tener múltiples consumidores o implementaciones:

1. Crear `App\Domain\Model\VehicleIdentity` con métodos read-only
2. Crear bridge si necesario (ej: si hay integración con framework específico)
3. `Vehicle implements VehicleIdentity`
4. Migrar servicios read-only a `VehicleIdentity`
5. Servicios con FK siguen usando `Vehicle`

## Convenciones de namespace

```
backend/src/
  Domain/
    Model/           ← interfaces de dominio
      UserIdentity.php
      (futuro: VehicleIdentity.php, RouteIdentity.php...)
  Security/
    AppUserInterface.php  ← bridge dominio ↔ Symfony
  Entity/
    UserAccount.php       ← implementación Doctrine
```

## Anti-patterns a evitar

| Anti-pattern | Problema | Solución |
|-------------|----------|----------|
| Servicio depende de `App\Entity\X` solo para leer | Acoplado a Doctrine | Crear interfaz de dominio |
| Interfaz de dominio extiende `UserInterface` | Dominio acoplado a Symfony | Crear bridge separado |
| Crear interfaz para entidad con 1 consumidor | Over-engineering | Evaluar con regla de 3+ servicios |
| Poner lógica de negocio en el bridge | Mezcla responsabilidades | Bridge solo une interfaces |

## Historial

- 2026-03-16: Creación inicial — patrón extraído de refactoring UserIdentity/UserAccount
