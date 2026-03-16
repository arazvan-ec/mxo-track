# Design: Domain UserIdentity + Rename User → UserAccount

**Fecha:** 2026-03-16
**Estado:** Borrador para revisión (v2 — con capa de dominio)

## Problema

La entidad `User` mapea a la tabla `user_account` vía `#[ORM\Table(name: 'user_account')]`. Cuando se generan migraciones, Doctrine produce `REFERENCES "user"` en vez de `REFERENCES "user_account"`. Esto ha causado fallos de deploy en producción al menos 2 veces.

Además, `User` está acoplada directamente a 69 archivos de src y 18 de tests, sin capa de abstracción. Los servicios de aplicación dependen de la entidad Doctrine (infraestructura) directamente.

## Decisiones del usuario

1. **Reducir acoplamiento primero**, luego renombrar
2. **Interfaz de identidad + seguridad**
3. **Todo junto** en un mismo PR
4. **Unificar ApiKeyUser** bajo la misma interfaz
5. **Capa de dominio pura** — servicios dependen de dominio, no de Symfony/Doctrine
6. **Namespace:** `App\Domain\Model`

## Arquitectura de 3 capas

```
┌─────────────────────────────────────────────────┐
│  Domain Layer (puro PHP, sin framework)          │
│  App\Domain\Model\UserIdentity                   │
│  - getId(), getPublicIdString(), hasRole()        │
│  - getCustomer(), isActive(), getName()           │
│  NO extiende UserInterface de Symfony             │
└──────────────────────┬──────────────────────────┘
                       │ extends
┌──────────────────────▼──────────────────────────┐
│  Security Layer (Symfony)                         │
│  App\Security\AppUserInterface                    │
│  extends UserIdentity + Symfony\UserInterface     │
│                                                   │
│  Implementaciones:                                │
│  - App\Entity\UserAccount (Doctrine entity)       │
│  - App\Security\ApiKeyUser (lightweight)          │
└─────────────────────────────────────────────────┘

Dependencias:
  - Services/Application → UserIdentity (dominio puro)
  - Controllers          → cast UserInterface → UserIdentity
  - Security layer       → AppUserInterface
  - Entities con FK      → UserAccount (clase concreta)
```

## Diseño detallado

### Capa 1: `UserIdentity` (Dominio)

**Archivo:** `src/Domain/Model/UserIdentity.php`

```php
<?php
declare(strict_types=1);

namespace App\Domain\Model;

use App\Entity\Customer;

interface UserIdentity
{
    public function getId(): ?string;
    public function getPublicIdString(): string;
    public function hasRole(string $role): bool;
    public function getCustomer(): ?Customer;
    public function isActive(): bool;
    public function getName(): ?string;
}
```

**Nota sobre `Customer`:** La interfaz de dominio referencia `Customer` entity. En un DDD estricto, esto también sería una interfaz de dominio. Pero para este proyecto, `Customer` es estable y no tiene el mismo problema de acoplamiento. Si en el futuro se necesita, se puede extraer `CustomerIdentity` siguiendo el mismo patrón.

**Justificación de cada método:**

| Método | Consumidores principales | Capa |
|--------|--------------------------|------|
| `getId()` | TopicResolver, Mercure topics | Security/Infra |
| `getPublicIdString()` | UserVoter, APIs | Security/Application |
| `hasRole(string)` | BaseVoter, DoctrineCustomerFilterSubscriber, servicios | Todas |
| `getCustomer()` | TenantContext, TopicResolver, servicios | Todas |
| `isActive()` | UserChecker, BaseVoter | Security |
| `getName()` | Servicios, templates | Application |

### Capa 2: `AppUserInterface` (Security/Symfony)

**Archivo:** `src/Security/AppUserInterface.php`

```php
<?php
declare(strict_types=1);

namespace App\Security;

use App\Domain\Model\UserIdentity;
use Symfony\Component\Security\Core\User\UserInterface;

interface AppUserInterface extends UserIdentity, UserInterface
{
}
```

No añade métodos propios — es un "puente" que une dominio con framework. Permite que el security layer trabaje con un tipo que es a la vez `UserIdentity` (dominio) y `UserInterface` (Symfony).

### Capa 3: Implementaciones

**`UserAccount` (antes `User`):**
```php
class UserAccount implements AppUserInterface, PasswordAuthenticatedUserInterface, SoftDeletableInterface
```
Ya tiene todos los métodos. Solo cambia la declaración de interfaces.

**`ApiKeyUser`:**
```php
final class ApiKeyUser implements AppUserInterface
```
Métodos a añadir:

| Método | Implementación |
|--------|----------------|
| `getId()` | `return null;` |
| `getPublicIdString()` | `return 'api-key:' . $this->apiKey->getPublicIdString();` |
| `hasRole(string $role)` | `return in_array($role, $this->getRoles(), true);` |
| `isActive()` | `return $this->apiKey->isActive();` |
| `getName()` | `return $this->apiKey->getName();` |

### Quién depende de qué

| Archivo | Depende de | Razón |
|---------|-----------|-------|
| **Servicios read-only** (6) | `UserIdentity` | Solo leen datos del usuario |
| MercureJwtFactory | `UserIdentity` | Genera token con datos del user |
| VisibilityScopeService | `UserIdentity` | Filtra vehículos por rol/customer |
| SearchService | `UserIdentity` | Scoped search |
| AiAssistantService | `UserIdentity` | Contexto de usuario |
| ReportingService | `UserIdentity` | Filtra reportes |
| FleetOverviewService | `UserIdentity` | Scoped fleet data |
| **Security layer** (5) | `AppUserInterface` | Necesitan UserInterface de Symfony |
| BaseVoter | `AppUserInterface` | |
| UserChecker | `AppUserInterface` | |
| DoctrineCustomerFilterSubscriber | `AppUserInterface` | |
| TenantContext | `AppUserInterface` | |
| TopicResolver | `UserIdentity` | No necesita Symfony UserInterface |
| **Servicios con FK** (8+) | `UserAccount` | Persisten relaciones Doctrine |
| RouteLifecycleService | `UserAccount` | `$route->setDriver($driver)` |
| DeliveryService | `UserAccount` | Crea Pod con driver FK |
| NotificationService | `UserAccount` | Persiste Notification con user FK |
| DriverAvailabilityService | `UserAccount` | FK |
| DriverActionService | `UserAccount` | FK |
| DriverScoringService | `UserAccount` | Queries relaciones |
| AuditLogger | `UserAccount` | FK a actor en AuditLog |
| AuditSubscriber | `UserAccount` | `em->getReference(UserAccount::class)` |
| **Controllers** | cast `→ UserIdentity` | `$this->getUser()` retorna `UserInterface` |
| **Entidades con relaciones** | `UserAccount` | FK Doctrine |
| **Forms, Fixtures, Commands** | `UserAccount` | `new UserAccount()`, form binding |

### Rename User → UserAccount

1. `src/Entity/User.php` → `src/Entity/UserAccount.php`
2. `src/Repository/UserRepository.php` → `src/Repository/UserAccountRepository.php`
3. Eliminar `#[ORM\Table(name: 'user_account')]` — automático por naming strategy
4. Actualizar relaciones, imports, type hints en archivos que usan clase concreta
5. **No se necesita migración de DB**

## Impacto en migraciones existentes

**Las migraciones NO se tocan.** Ya ejecutadas en producción. Solo `Version20260313200000` (ya corregida) tenía el bug.

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Autowiring con 2 implementaciones de AppUserInterface | `ApiKeyUser` se crea manualmente en authenticator, no por DI |
| Customer en interfaz de dominio | Aceptable — Customer es estable. Extraer si crece |
| Tests que instancian `User` | Buscar `new User(` → `new UserAccount(` |
| Templates con `app.user` | Twig usa propiedades, no clase. Sin impacto |

## Orden de ejecución

1. Crear `UserIdentity` (dominio puro)
2. Crear `AppUserInterface` (extends UserIdentity + UserInterface)
3. `User implements AppUserInterface`
4. `ApiKeyUser implements AppUserInterface`
5. Migrar security layer a `AppUserInterface`
6. Migrar servicios read-only a `UserIdentity`
7. Tests: verificar
8. Rename `User` → `UserAccount`
9. Actualizar entidades, servicios con FK, controllers, forms, fixtures, tests
10. Eliminar `#[ORM\Table]`
11. Schema validate + tests finales
