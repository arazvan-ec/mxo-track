# Design: Domain UserIdentity + CustomerIdentity + Rename User → UserAccount

**Fecha:** 2026-03-16
**Estado:** Aprobado (v3 — dominio puro con CustomerIdentity)

## Problema

1. La entidad `User` mapea a tabla `user_account` vía `#[ORM\Table]`. Doctrine genera `REFERENCES "user"` en migraciones, causando fallos de deploy recurrentes (al menos 2 veces).
2. `User` está acoplada a 79 archivos sin abstracción. Servicios dependen de infraestructura (Doctrine entity) directamente.
3. `User` y `ApiKeyUser` implementan `UserInterface` de Symfony con branching `instanceof` en 7+ sitios.

## Decisiones

1. **Capa de dominio pura** — interfaces sin Symfony/Doctrine
2. **CustomerIdentity también** — dominio 100% puro, sin `App\Entity\Customer`
3. **Customer NO se renombra** — no tiene problema de nombre reservado PostgreSQL
4. **Un solo bridge** — `AppUserInterface extends UserIdentity + UserInterface`
5. **Rename User → UserAccount** — Doctrine genera `user_account` automáticamente
6. **Todo en un PR**

## Arquitectura

```
┌──────────────────────────────────────────────────┐
│  Domain Layer  (App\Domain\Model\)                │
│  Puro PHP — sin Symfony, sin Doctrine             │
│                                                    │
│  CustomerIdentity                                  │
│    getId(): ?string                                │
│    getPublicIdString(): string                     │
│    getName(): string                               │
│                                                    │
│  UserIdentity                                      │
│    getId(): ?string                                │
│    getPublicIdString(): string                     │
│    hasRole(string $role): bool                     │
│    getCustomer(): ?CustomerIdentity                │
│    isActive(): bool                                │
│    getName(): ?string                              │
└──────────────────────┬───────────────────────────┘
                       │ extends
┌──────────────────────▼───────────────────────────┐
│  Bridge Layer  (App\Security\)                     │
│                                                    │
│  AppUserInterface                                  │
│    extends UserIdentity + Symfony\UserInterface     │
│    (no añade métodos propios)                      │
└──────────────────────┬───────────────────────────┘
                       │ implements
┌──────────────────────▼───────────────────────────┐
│  Infrastructure  (App\Entity\, App\Security\)      │
│                                                    │
│  Customer implements CustomerIdentity              │
│  UserAccount implements AppUserInterface +         │
│              PasswordAuthenticatedUserInterface     │
│  ApiKeyUser implements AppUserInterface             │
└──────────────────────────────────────────────────┘
```

## Interfaces de dominio

### CustomerIdentity

```php
<?php
declare(strict_types=1);

namespace App\Domain\Model;

interface CustomerIdentity
{
    public function getId(): ?string;
    public function getPublicIdString(): string;
    public function getName(): string;
}
```

### UserIdentity

```php
<?php
declare(strict_types=1);

namespace App\Domain\Model;

interface UserIdentity
{
    public function getId(): ?string;
    public function getPublicIdString(): string;
    public function hasRole(string $role): bool;
    public function getCustomer(): ?CustomerIdentity;
    public function isActive(): bool;
    public function getName(): ?string;
}
```

### AppUserInterface (bridge)

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

## Implementaciones

### Customer implements CustomerIdentity

Customer ya tiene `getId()`, `getPublicIdString()`, `getName()`. Solo añadir `implements CustomerIdentity`.

### UserAccount (rename de User)

```php
class UserAccount implements AppUserInterface, PasswordAuthenticatedUserInterface, SoftDeletableInterface
```

- Eliminar `#[ORM\Table(name: 'user_account')]` — naming strategy lo genera automáticamente
- `getCustomer()` retorna `?Customer` que implements `?CustomerIdentity` — compatible con la interfaz

### ApiKeyUser implements AppUserInterface

Métodos nuevos a añadir:

| Método | Implementación |
|--------|----------------|
| `getId()` | `return null;` |
| `getPublicIdString()` | `return 'api-key:' . $this->apiKey->getPublicIdString();` |
| `hasRole(string $role)` | `return in_array($role, $this->getRoles(), true);` |
| `isActive()` | `return $this->apiKey->isActive();` |
| `getName()` | `return $this->apiKey->getName();` |
| `getCustomer()` | Ya existe, retorna `Customer` (implements `CustomerIdentity`) |

## Regla de dependencias por capa

| Consumidor | Depende de | Razón |
|-----------|-----------|-------|
| Servicios read-only (6) | `UserIdentity` | Solo leen datos |
| TopicResolver | `UserIdentity` | No necesita Symfony |
| BaseVoter, UserChecker | `AppUserInterface` | Necesitan Symfony `UserInterface` |
| DoctrineCustomerFilterSubscriber | `AppUserInterface` | Unifica User + ApiKeyUser |
| TenantContext | `AppUserInterface` | Security context |
| Servicios con FK (8+) | `UserAccount` | Persisten relaciones |
| Entidades con relaciones | `UserAccount` | `targetEntity:` |
| Forms, Fixtures, Commands | `UserAccount` | `new UserAccount()` |
| Controllers | cast `→ UserIdentity` | `$this->getUser()` retorna `?UserInterface` |

## Impacto

- **No se necesita migración de DB** — tabla ya es `user_account`
- **Migraciones existentes no se tocan**
- **Migraciones futuras** generarán `user_account` automáticamente
- **Twig templates** no se afectan (`app.user` usa propiedades)

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Autowiring con 2 impl de AppUserInterface | ApiKeyUser se crea manualmente en authenticator, no DI |
| `getCustomer()` retorna `?CustomerIdentity` pero servicios con FK necesitan `Customer` | Esos servicios usan `UserAccount` directamente, que retorna `?Customer` |
| Covariance en return type | PHP 8.4 soporta covariant return types: `Customer` es subtipo de `?CustomerIdentity` |
| 79 archivos que cambiar | Plan detallado por fases, tests después de cada fase |
