# Design: User Identity Interface + Rename User → UserAccount

**Fecha:** 2026-03-16
**Estado:** Borrador para revisión

## Problema

La entidad `User` mapea a la tabla `user_account` vía `#[ORM\Table(name: 'user_account')]`. Cuando se generan migraciones (manual o `doctrine:migrations:diff`), Doctrine usa el nombre de la clase como referencia, produciendo `REFERENCES "user"` en vez de `REFERENCES "user_account"`. Esto ha causado fallos de deploy en producción al menos 2 veces.

Además, `User` está acoplada directamente a 69 archivos de src y 18 de tests, sin capa de abstracción.

## Decisiones del usuario

1. **Reducir acoplamiento primero**, luego renombrar
2. **Interfaz de identidad + seguridad** (no mínima)
3. **Todo junto** en un mismo PR
4. **Unificar ApiKeyUser** bajo la misma interfaz

## Diseño

### Fase 1: Crear `AppUserInterface`

Nueva interfaz en `src/Security/AppUserInterface.php` que extiende `UserInterface` de Symfony:

```php
<?php
declare(strict_types=1);

namespace App\Security;

use App\Entity\Customer;
use Symfony\Component\Security\Core\User\UserInterface;

interface AppUserInterface extends UserInterface
{
    public function getId(): ?int;
    public function getPublicIdString(): string;
    public function hasRole(string $role): bool;
    public function getCustomer(): ?Customer;
    public function isActive(): bool;
    public function getName(): ?string;
}
```

**Justificación de cada método:**

| Método | Usado en | Justificación |
|--------|----------|---------------|
| `getId()` | TopicResolver, Mercure topics | Identidad interna (no exponer en API) |
| `getPublicIdString()` | UserVoter, APIs | Identidad pública |
| `hasRole(string)` | BaseVoter, DoctrineCustomerFilterSubscriber | Autorización |
| `getCustomer()` | TopicResolver, TenantContext, DoctrineCustomerFilterSubscriber | Multi-tenancy |
| `isActive()` | UserChecker, BaseVoter | Verificación de estado |
| `getName()` | Servicios, templates | Display |

### Fase 2: Implementar en ambas clases

**User (futuro UserAccount)** ya tiene todos estos métodos. Solo necesita `implements AppUserInterface`.

**ApiKeyUser** necesita implementar los métodos que le faltan:

| Método | Implementación en ApiKeyUser |
|--------|------------------------------|
| `getId()` | `return null;` (no tiene PK propia) |
| `getPublicIdString()` | `return 'api-key:' . $this->apiKey->getPublicIdString();` |
| `hasRole(string $role)` | `return in_array($role, $this->getRoles(), true);` |
| `getCustomer()` | Ya existe (siempre non-null) |
| `isActive()` | `return $this->apiKey->isActive();` (delegado a ApiKey) |
| `getName()` | `return $this->apiKey->getName();` (o null) |

### Fase 3: Migrar consumidores a la interfaz

Reemplazar `User` por `AppUserInterface` en:

**Security (elimina instanceof branching):**
- `BaseVoter` — type hint `AppUserInterface` en vez de `User`
- `UserChecker` — `$user instanceof AppUserInterface` en vez de `instanceof User`
- `DoctrineCustomerFilterSubscriber` — unificar las 2 ramas (ApiKeyUser y User) en una sola vía interfaz
- `TenantContext` — `$user instanceof AppUserInterface`
- `TopicResolver` — parámetro `AppUserInterface` en vez de `User`

**Servicios (14 servicios):**
- Cambiar type hints de `User` a `AppUserInterface` donde el servicio solo usa métodos de la interfaz
- Servicios que necesitan métodos específicos de User (ej: `getEmail()`, `getPasswordHash()`) mantienen `User`/`UserAccount`

**Controllers:**
- `$this->getUser()` retorna `UserInterface` de Symfony. Cast a `AppUserInterface` donde se necesiten métodos extendidos.

### Fase 4: Rename User → UserAccount

Con la interfaz en su lugar, el rename afecta menos archivos:

1. `src/Entity/User.php` → `src/Entity/UserAccount.php` (clase `UserAccount`)
2. `src/Repository/UserRepository.php` → `src/Repository/UserAccountRepository.php`
3. Eliminar `#[ORM\Table(name: 'user_account')]` — Doctrine lo genera automáticamente
4. Actualizar relaciones en las 10 entidades: `targetEntity: User::class` → `targetEntity: UserAccount::class`
5. Actualizar `use App\Entity\User` → `use App\Entity\UserAccount` en archivos que aún referencien la clase concreta
6. Actualizar fixtures, commands, forms, templates
7. **No se necesita migración de DB** — la tabla ya se llama `user_account`

### Archivos que siguen usando la clase concreta (no la interfaz)

Estos archivos necesitan `UserAccount` directamente (no pueden usar solo la interfaz):

- `UserAccountRepository` — el propio repository
- `AdminUserFixture` — crea instancias con `new UserAccount()`
- `CreateAdminCommand` — crea instancias
- `DemoScenarioBuilder` — crea instancias
- `CustomerUserType` / `DriverType` — forms vinculados a la entidad
- `UserAdminController` / `DriverAdminController` — CRUD de la entidad
- `LoginAuditSubscriber` — necesita `getEmail()` (no está en la interfaz)
- Entidades con relaciones — `targetEntity: UserAccount::class`

## Impacto en migraciones existentes

**Las migraciones NO se tocan.** Ya están ejecutadas en producción. Solo la migración `Version20260313200000` (ya corregida en este branch) tenía el bug.

Migraciones futuras generadas por Doctrine usarán `user_account` automáticamente porque el naming strategy `underscore_number_aware` convertirá `UserAccount` → `user_account`.

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Romper autowiring | Symfony autowire por interfaz funciona si hay una sola implementación concreta. `User`/`UserAccount` es la principal; `ApiKeyUser` se crea manualmente en el authenticator, no por DI |
| Tests que instancian `User` | Buscar `new User(` y actualizar a `new UserAccount(` |
| Templates con `app.user` | Twig no referencia la clase, usa propiedades. Sin impacto |
| Voters con `instanceof User` en `supports()` | `UserVoter` verifica `$subject instanceof User` — cambiar a `UserAccount` |

## Orden de ejecución

1. Crear `AppUserInterface`
2. `User implements AppUserInterface` (además de las que ya tiene)
3. `ApiKeyUser implements AppUserInterface` (añadir métodos faltantes)
4. Migrar security layer a interfaz (voters, checker, subscriber, tenant)
5. Migrar servicios a interfaz
6. Tests: verificar que todo pasa
7. Rename `User` → `UserAccount` (clase + archivo + repository)
8. Actualizar imports y relaciones en entidades
9. Actualizar fixtures, commands, forms, controllers que usan clase concreta
10. Eliminar `#[ORM\Table(name: 'user_account')]`
11. Verificar que `doctrine:schema:validate` pasa
12. Tests finales
