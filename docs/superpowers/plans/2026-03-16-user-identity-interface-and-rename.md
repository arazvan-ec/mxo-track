# Plan: Domain UserIdentity + CustomerIdentity + Rename User → UserAccount

**Spec:** `docs/superpowers/specs/2026-03-16-user-identity-interface-and-rename-design.md` (v3)
**Goal:** Dominio puro (`UserIdentity`, `CustomerIdentity`), bridge Symfony (`AppUserInterface`), rename `User` → `UserAccount`, eliminar tabla explícita.
**Archivos afectados:** ~80+

---

## Fase 1: Crear interfaces de dominio y bridge

### Task 1: Crear CustomerIdentity
**Archivo nuevo:** `backend/src/Domain/Model/CustomerIdentity.php`

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

- [ ] Crear directorio `backend/src/Domain/Model/`
- [ ] Crear archivo
- [ ] Commit: "feat: create CustomerIdentity domain interface"

### Task 2: Customer implements CustomerIdentity
**Archivo:** `backend/src/Entity/Customer.php`

- [ ] Añadir `use App\Domain\Model\CustomerIdentity;`
- [ ] Añadir `CustomerIdentity` a la lista de implements
- [ ] Verificar que `getId()`, `getPublicIdString()`, `getName()` ya existen
- [ ] Correr tests: `cd backend && php vendor/bin/phpunit`
- [ ] Commit: "feat: Customer implements CustomerIdentity"

### Task 3: Crear UserIdentity
**Archivo nuevo:** `backend/src/Domain/Model/UserIdentity.php`

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

- [ ] Crear archivo
- [ ] Commit: "feat: create UserIdentity domain interface"

### Task 4: Crear AppUserInterface (bridge)
**Archivo nuevo:** `backend/src/Security/AppUserInterface.php`

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

- [ ] Crear archivo
- [ ] Commit: "feat: create AppUserInterface bridge (domain + Symfony)"

### Task 5: User implements AppUserInterface
**Archivo:** `backend/src/Entity/User.php`

- [ ] Añadir `use App\Security\AppUserInterface;`
- [ ] Cambiar declaración: `class User implements AppUserInterface, PasswordAuthenticatedUserInterface, SoftDeletableInterface`
- [ ] Eliminar `use Symfony\Component\Security\Core\User\UserInterface;` (viene via AppUserInterface)
- [ ] Verificar: `getCustomer()` retorna `?Customer`. Customer implements `CustomerIdentity`. PHP 8.4 covariant return types → `?Customer` es subtipo de `?CustomerIdentity` ✓
- [ ] Correr tests
- [ ] Commit: "feat: User implements AppUserInterface"

### Task 6: ApiKeyUser implements AppUserInterface
**Archivo:** `backend/src/Security/ApiKeyUser.php`

- [ ] Cambiar `implements UserInterface` → `implements AppUserInterface`
- [ ] Eliminar `use Symfony\Component\Security\Core\User\UserInterface;`
- [ ] Añadir métodos:

```php
public function getId(): ?string
{
    return null;
}

public function getPublicIdString(): string
{
    return 'api-key:' . $this->apiKey->getPublicIdString();
}

public function hasRole(string $role): bool
{
    return in_array($role, $this->getRoles(), true);
}

public function isActive(): bool
{
    return $this->apiKey->isActive();
}

public function getName(): ?string
{
    return $this->apiKey->getName();
}
```

- [ ] `getCustomer()` ya existe, retorna `Customer` (implements `CustomerIdentity`) ✓
- [ ] Correr tests
- [ ] Commit: "feat: ApiKeyUser implements AppUserInterface with domain methods"

---

## Fase 2: Migrar security layer a interfaces

### Task 7: Migrar BaseVoter → AppUserInterface
**Archivo:** `backend/src/Security/Voter/BaseVoter.php`

- [ ] `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `!$user instanceof User` → `!$user instanceof AppUserInterface`
- [ ] `isGrantedForUser(..., User $user)` → `..., AppUserInterface $user`
- [ ] Commit: "refactor: BaseVoter uses AppUserInterface"

### Task 8: Migrar UserVoter → AppUserInterface
**Archivo:** `backend/src/Security/Voter/UserVoter.php`

- [ ] `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `$subject instanceof User` → `$subject instanceof AppUserInterface`
- [ ] `isGrantedForUser(..., User $user)` → `..., AppUserInterface $user`
- [ ] `!$subject instanceof User` → `!$subject instanceof AppUserInterface`
- [ ] Commit: "refactor: UserVoter uses AppUserInterface"

### Task 9: Migrar UserChecker → AppUserInterface
**Archivo:** `backend/src/Security/UserChecker.php`

- [ ] `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `!$user instanceof User` → `!$user instanceof AppUserInterface`
- [ ] Commit: "refactor: UserChecker uses AppUserInterface"

### Task 10: Migrar DoctrineCustomerFilterSubscriber → AppUserInterface
**Archivo:** `backend/src/EventSubscriber/DoctrineCustomerFilterSubscriber.php`

Unificar las 2 ramas (ApiKeyUser y User) en una sola:

- [ ] Eliminar `use App\Entity\User;` y `use App\Security\ApiKeyUser;`
- [ ] Añadir `use App\Security\AppUserInterface;`
- [ ] Reescribir `onKernelRequest()`:

```php
public function onKernelRequest(RequestEvent $event): void
{
    if (!$event->isMainRequest()) {
        return;
    }

    $filters = $this->entityManager->getFilters();
    if (!$filters->has('customer_tenant')) {
        return;
    }

    $user = $this->security->getUser();

    if (!$user instanceof AppUserInterface) {
        if ($filters->isEnabled('customer_tenant')) {
            $filters->disable('customer_tenant');
        }
        return;
    }

    $customer = $user->getCustomer();
    $shouldEnable = $user->hasRole('ROLE_CUSTOMER') && $customer !== null;

    if (!$shouldEnable) {
        if ($filters->isEnabled('customer_tenant')) {
            $filters->disable('customer_tenant');
        }
        return;
    }

    if (!$filters->isEnabled('customer_tenant')) {
        $filters->enable('customer_tenant');
    }

    $filters->getFilter('customer_tenant')->setParameter('customer_id', (string) $customer->getId());
}
```

- [ ] Commit: "refactor: unify DoctrineCustomerFilterSubscriber via AppUserInterface"

### Task 11: Migrar TenantContext → AppUserInterface
**Archivo:** `backend/src/Provider/TenantContext.php`

- [ ] `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `$user instanceof User` → `$user instanceof AppUserInterface`
- [ ] Commit: "refactor: TenantContext uses AppUserInterface"

### Task 12: Migrar TopicResolver → UserIdentity (dominio)
**Archivo:** `backend/src/Security/TopicResolver.php`

TopicResolver no necesita Symfony UserInterface — depende solo del dominio.

- [ ] `use App\Entity\User;` → `use App\Domain\Model\UserIdentity;`
- [ ] `resolveForUser(User $user, ...)` → `resolveForUser(UserIdentity $user, ...)`
- [ ] Commit: "refactor: TopicResolver depends on domain UserIdentity"

### Task 13: Migrar AuditSubscriber (parcial)
**Archivo:** `backend/src/EventSubscriber/AuditSubscriber.php`

- [ ] Añadir `use App\Security\AppUserInterface;`
- [ ] Línea `$securityUser instanceof User` → `$securityUser instanceof AppUserInterface`
- [ ] Mantener `use App\Entity\User;` — necesario para `AUDITED_ENTITIES` y `em->getReference(User::class)` (se renombrará en Fase 4)
- [ ] Commit: "refactor: AuditSubscriber uses AppUserInterface for actor"

### Task 14: Correr tests
- [ ] `cd backend && php vendor/bin/phpunit`
- [ ] Verificar 0 failures
- [ ] Commit si hay fixes: "fix: resolve test failures after security layer migration"

---

## Fase 3: Migrar servicios read-only a UserIdentity (dominio)

### Task 15: Migrar servicios a UserIdentity

Estos servicios solo leen datos del usuario (no persisten relaciones FK):

1. **`Service/MercureJwtFactory.php`**
   - [ ] `use App\Entity\User;` → `use App\Domain\Model\UserIdentity;`
   - [ ] Type hints `User` → `UserIdentity`

2. **`Service/VisibilityScopeService.php`**
   - [ ] Ídem

3. **`Service/SearchService.php`**
   - [ ] Ídem

4. **`Service/AiAssistantService.php`**
   - [ ] Ídem

5. **`Service/ReportingService.php`**
   - [ ] Verificar que solo usa métodos de UserIdentity, luego migrar

6. **`Application/Fleet/FleetOverviewService.php`**
   - [ ] Ídem

- [ ] Commit: "refactor: migrate read-only services to domain UserIdentity"

### Task 16: Actualizar controllers que pasan a servicios migrados

Controllers que llaman a servicios ya migrados necesitan cast a `UserIdentity`:

```php
use App\Domain\Model\UserIdentity;

$user = $this->getUser();
if (!$user instanceof UserIdentity) {
    throw $this->createAccessDeniedException();
}
```

Controllers afectados:
- [ ] `Controller/MercureTokenController.php`
- [ ] `Controller/FleetMapController.php`
- [ ] `Controller/SearchController.php`
- [ ] `Controller/Admin/ReportController.php`
- [ ] `Controller/Admin/AiAssistantController.php`
- [ ] Otros que usen servicios migrados

- [ ] Commit: "refactor: controllers cast to UserIdentity for domain services"

### Task 17: Correr tests
- [ ] `cd backend && php vendor/bin/phpunit`
- [ ] Verificar 0 failures

---

## Fase 4: Rename User → UserAccount

### Task 18: Rename Entity + Repository (archivos)

- [ ] `git mv backend/src/Entity/User.php backend/src/Entity/UserAccount.php`
- [ ] `git mv backend/src/Repository/UserRepository.php backend/src/Repository/UserAccountRepository.php`

**En `UserAccount.php`:**
- [ ] `class User` → `class UserAccount`
- [ ] Eliminar `#[ORM\Table(name: 'user_account')]`
- [ ] `repositoryClass: UserRepository::class` → `UserAccountRepository::class`
- [ ] `use App\Repository\UserRepository;` → `use App\Repository\UserAccountRepository;`

**En `UserAccountRepository.php`:**
- [ ] `class UserRepository` → `class UserAccountRepository`
- [ ] `User::class` → `UserAccount::class`

- [ ] Commit: "refactor: rename User to UserAccount, remove explicit table name"

### Task 19: Actualizar 10 entidades con relaciones FK

Para cada una: `use App\Entity\User;` → `use App\Entity\UserAccount;`, `targetEntity: User::class` → `UserAccount::class`, type hints `?User` → `?UserAccount`.

1. - [ ] `Entity/AuditLog.php`
2. - [ ] `Entity/DriverAction.php`
3. - [ ] `Entity/DriverAvailability.php`
4. - [ ] `Entity/DriverFeedback.php`
5. - [ ] `Entity/Notification.php`
6. - [ ] `Entity/Pod.php`
7. - [ ] `Entity/PushSubscription.php`
8. - [ ] `Entity/Route.php`
9. - [ ] `Entity/RouteEvent.php`
10. - [ ] `Entity/VehicleInspection.php`

- [ ] Commit: "refactor: update entity FK relations from User to UserAccount"

### Task 20: Actualizar servicios con FK (clase concreta)

1. - [ ] `Service/NotificationService.php`
2. - [ ] `Service/AuditLogger.php`
3. - [ ] `Service/DriverScoringService.php`
4. - [ ] `Service/DriverAvailabilityService.php`
5. - [ ] `Service/DriverActionService.php`
6. - [ ] `Service/WebPushService.php`
7. - [ ] `Service/EmailNotificationService.php`
8. - [ ] `Service/DemoScenarioBuilder.php`
9. - [ ] `Service/DemoScenarioResult.php`
10. - [ ] `Service/OperatorKpiService.php`
11. - [ ] `Application/Route/RouteLifecycleService.php`
12. - [ ] `Application/Delivery/DeliveryService.php`
13. - [ ] `Application/Delivery/DeliveryContext.php`

Cada uno: `use App\Entity\User;` → `use App\Entity\UserAccount;`, type hints.

- [ ] Commit: "refactor: update FK services from User to UserAccount"

### Task 21: Actualizar controllers (import + type hints)

20 controllers — cambiar imports, type hints, docblocks:

- [ ] `Controller/RouteEtaApiController.php`
- [ ] `Controller/DriverApiController.php`
- [ ] `Controller/DriverPushSubscriptionController.php`
- [ ] `Controller/DriverWebController.php`
- [ ] `Controller/FleetMapController.php`
- [ ] `Controller/MercureTokenController.php`
- [ ] `Controller/NotificationController.php`
- [ ] `Controller/SearchController.php`
- [ ] `Controller/VehicleApiController.php`
- [ ] `Controller/Admin/AccountingExportController.php`
- [ ] `Controller/Admin/CustomerAdminController.php`
- [ ] `Controller/Admin/DriverAdminController.php`
- [ ] `Controller/Admin/DriverAvailabilityController.php`
- [ ] `Controller/Admin/ReportController.php`
- [ ] `Controller/Admin/RouteAdminController.php`
- [ ] `Controller/Admin/RoutePlannerController.php`
- [ ] `Controller/Admin/RouteTemplateController.php`
- [ ] `Controller/Admin/UserAdminController.php`
- [ ] `Controller/Operator/OperatorDashboardController.php`
- [ ] `Controller/Customer/CustomerReportController.php`

**Nota:** Controllers que ya migraron a `UserIdentity` en Task 16 pueden no necesitar import de UserAccount si no lo usan directamente.

- [ ] Commit: "refactor: update controllers from User to UserAccount"

### Task 22: Actualizar forms
- [ ] `Form/CustomerUserType.php`
- [ ] `Form/DriverType.php`
- [ ] `Form/RouteType.php`
- [ ] Commit: "refactor: update forms from User to UserAccount"

### Task 23: Actualizar EventSubscribers
- [ ] `EventSubscriber/AuditSubscriber.php` — `User::class` en AUDITED_ENTITIES + `em->getReference`
- [ ] `EventSubscriber/LoginAuditSubscriber.php` — entityType string (cosmético, 'User' → 'UserAccount' para consistencia)
- [ ] Commit: "refactor: update event subscribers from User to UserAccount"

### Task 24: Actualizar fixtures, commands, listeners, repositories
- [ ] `DataFixtures/AdminUserFixture.php` — `new User(` → `new UserAccount(`
- [ ] `Command/CreateAdminCommand.php` — ídem
- [ ] `Command/TestRoutingCommand.php` — type hints
- [ ] `EventListener/Domain/RouteEventLogListener.php` — `UserRepository` → `UserAccountRepository`
- [ ] `Repository/NotificationRepository.php` — imports y type hints
- [ ] `Geocoding/NominatimGeocoder.php` — verificar si referencia User
- [ ] Commit: "refactor: update fixtures, commands, listeners from User to UserAccount"

---

## Fase 5: Actualizar tests

### Task 25: Actualizar todos los tests (18 archivos)

Cada uno: imports, `new User(` → `new UserAccount(`, `User::class` → `UserAccount::class`, type hints.

1. - [ ] `tests/Factory/TestEntityFactory.php`
2. - [ ] `tests/Functional/CustomerTenantFilterTest.php`
3. - [ ] `tests/Functional/DriverApiTest.php`
4. - [ ] `tests/Functional/Smoke/AdminPageSmokeTest.php`
5. - [ ] `tests/Functional/Smoke/PageSmokeTest.php`
6. - [ ] `tests/Unit/AiAssistantControllerTest.php`
7. - [ ] `tests/Unit/AiAssistantServiceTest.php`
8. - [ ] `tests/Unit/Command/DemoSetupCommandTest.php`
9. - [ ] `tests/Unit/DeliveryServiceTest.php`
10. - [ ] `tests/Unit/DriverActionServiceTest.php`
11. - [ ] `tests/Unit/EventListener/Domain/RouteEventLogListenerTest.php`
12. - [ ] `tests/Unit/MercureJwtFactoryTest.php`
13. - [ ] `tests/Unit/Provider/TenantContextTest.php`
14. - [ ] `tests/Unit/RouteLifecycleServiceTest.php`
15. - [ ] `tests/Unit/SearchServiceTest.php`
16. - [ ] `tests/Unit/SecurityTest.php`
17. - [ ] `tests/Unit/Service/DemoScenarioBuilderTest.php`
18. - [ ] `tests/Unit/TopicResolverTest.php`

- [ ] Commit: "refactor: update all tests from User to UserAccount"

---

## Fase 6: Verificación y documentación

### Task 26: Schema validation
- [ ] `cd backend && php bin/console doctrine:schema:validate`
- [ ] Verificar mapping correcto, tabla sigue siendo `user_account`

### Task 27: Test suite completo
- [ ] `cd backend && php vendor/bin/phpunit`
- [ ] 0 failures, 0 errors

### Task 28: Lint
- [ ] `cd backend && make lint` (o `find src -name "*.php" -exec php -l {} \;`)

### Task 29: Grep final — verificar que no quedan referencias antiguas
- [ ] `grep -r "App\\Entity\\User[^A]" backend/src/ backend/tests/` — 0 resultados
- [ ] `grep -r "new User(" backend/src/ backend/tests/` — 0 resultados
- [ ] `grep -r "instanceof User[^I]" backend/src/ backend/tests/` — 0 (UserIdentity es OK)
- [ ] `grep -r "use App\\Entity\\User;" backend/src/ backend/tests/` — 0 resultados

### Task 30: Actualizar knowledge modules
- [ ] `docs/knowledge/domain-model.md` — User → UserAccount en tabla de entidades y árbol
- [ ] `docs/knowledge/security.md` — si existe, actualizar referencias
- [ ] `docs/knowledge/domain-driven-architecture.md` — marcar UserIdentity y CustomerIdentity como implementados
- [ ] Commit: "docs: update knowledge modules for UserAccount rename and domain layer"

### Task 31: Actualizar FEATURES.md si existe
- [ ] Verificar `docs/FEATURES.md`
- [ ] Actualizar si referencia User
- [ ] Commit si necesario
