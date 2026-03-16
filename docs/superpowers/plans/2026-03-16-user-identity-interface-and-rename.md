# Plan: Domain UserIdentity + Rename User → UserAccount

**Spec:** `docs/superpowers/specs/2026-03-16-user-identity-interface-and-rename-design.md`
**Goal:** Introducir capa de dominio `UserIdentity`, desacoplar servicios de infraestructura, renombrar `User` → `UserAccount`.
**Archivos afectados:** ~80 (62 src + 17 tests + 1 nuevo dominio + 1 nueva interfaz)

---

## Fase 1: Crear interfaces (dominio + security bridge)

### Task 1: Crear UserIdentity (interfaz de dominio pura)
**Archivo nuevo:** `backend/src/Domain/Model/UserIdentity.php`

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

- [ ] Crear directorio `backend/src/Domain/Model/`
- [ ] Crear archivo
- [ ] Commit: "feat: create UserIdentity domain interface"

### Task 2: Crear AppUserInterface (bridge dominio ↔ Symfony)
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
- [ ] Commit: "feat: create AppUserInterface bridging domain and Symfony security"

### Task 3: User implements AppUserInterface
**Archivo:** `backend/src/Entity/User.php`

- [ ] Añadir `use App\Security\AppUserInterface;`
- [ ] Cambiar `class User implements UserInterface, PasswordAuthenticatedUserInterface, SoftDeletableInterface` → `class User implements AppUserInterface, PasswordAuthenticatedUserInterface, SoftDeletableInterface`
- [ ] Eliminar `use Symfony\Component\Security\Core\User\UserInterface;` (ya viene via AppUserInterface)
- [ ] Verificar que todos los métodos de UserIdentity ya existen en User
- [ ] Commit: "feat: User implements AppUserInterface"

### Task 4: ApiKeyUser implements AppUserInterface
**Archivo:** `backend/src/Security/ApiKeyUser.php`

- [ ] Cambiar `implements UserInterface` → `implements AppUserInterface`
- [ ] Eliminar `use Symfony\Component\Security\Core\User\UserInterface;`
- [ ] Añadir métodos faltantes:

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

- [ ] Correr tests: `cd backend && php vendor/bin/phpunit`
- [ ] Commit: "feat: ApiKeyUser implements AppUserInterface with domain methods"

---

## Fase 2: Migrar security layer a AppUserInterface

### Task 5: Migrar BaseVoter
**Archivo:** `backend/src/Security/Voter/BaseVoter.php`

Cambios:
- [ ] `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] Línea 18: `!$user instanceof User` → `!$user instanceof AppUserInterface`
- [ ] Línea 22: no cambia (`hasRole` está en AppUserInterface)
- [ ] Línea 29: `abstract protected function isGrantedForUser(string $attribute, mixed $subject, User $user): bool;` → `..., AppUserInterface $user): bool;`
- [ ] Commit: "refactor: BaseVoter uses AppUserInterface"

### Task 6: Migrar UserVoter
**Archivo:** `backend/src/Security/Voter/UserVoter.php`

Cambios:
- [ ] `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `supports()`: `$subject instanceof User` → `$subject instanceof AppUserInterface`
- [ ] `isGrantedForUser(... User $user)` → `... AppUserInterface $user`
- [ ] Dentro del método: `!$subject instanceof User` → `!$subject instanceof AppUserInterface`
- [ ] Commit: "refactor: UserVoter uses AppUserInterface"

### Task 7: Migrar UserChecker
**Archivo:** `backend/src/Security/UserChecker.php`

Cambios:
- [ ] `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `!$user instanceof User` → `!$user instanceof AppUserInterface`
- [ ] Commit: "refactor: UserChecker uses AppUserInterface"

### Task 8: Migrar DoctrineCustomerFilterSubscriber
**Archivo:** `backend/src/EventSubscriber/DoctrineCustomerFilterSubscriber.php`

Rewrite completo del método `onKernelRequest` para unificar ramas:
- [ ] Eliminar `use App\Entity\User;` y `use App\Security\ApiKeyUser;`
- [ ] Añadir `use App\Security\AppUserInterface;`
- [ ] Nuevo cuerpo:

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

### Task 9: Migrar TenantContext
**Archivo:** `backend/src/Provider/TenantContext.php`

Cambios:
- [ ] `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `$user instanceof User` → `$user instanceof AppUserInterface`
- [ ] Commit: "refactor: TenantContext uses AppUserInterface"

### Task 10: Migrar TopicResolver a UserIdentity (dominio)
**Archivo:** `backend/src/Security/TopicResolver.php`

TopicResolver no necesita Symfony UserInterface — solo usa métodos de dominio.
- [ ] `use App\Entity\User;` → `use App\Domain\Model\UserIdentity;`
- [ ] `resolveForUser(User $user, ...)` → `resolveForUser(UserIdentity $user, ...)`
- [ ] Commit: "refactor: TopicResolver depends on domain UserIdentity"

### Task 11: Migrar AuditSubscriber
**Archivo:** `backend/src/EventSubscriber/AuditSubscriber.php`

- [ ] Añadir `use App\Security\AppUserInterface;`
- [ ] Línea 134: `$securityUser instanceof User` → `$securityUser instanceof AppUserInterface`
- [ ] Mantener `use App\Entity\User;` porque `AUDITED_ENTITIES` y `em->getReference(User::class)` necesitan la clase concreta (se renombrará en Fase 3)
- [ ] Commit: "refactor: AuditSubscriber uses AppUserInterface for actor resolution"

### Task 12: Correr tests
- [ ] `cd backend && php vendor/bin/phpunit`
- [ ] Verificar 0 failures
- [ ] Commit solo si hay fixes

---

## Fase 3: Migrar servicios read-only a UserIdentity (dominio)

### Task 13: Migrar servicios a UserIdentity

Estos servicios solo leen datos del usuario (no persisten relaciones FK):

1. **`Service/MercureJwtFactory.php`**
   - [ ] `use App\Entity\User;` → `use App\Domain\Model\UserIdentity;`
   - [ ] Type hints `User $user` → `UserIdentity $user`

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

### Task 14: Actualizar controllers que pasan a servicios migrados

Controllers que llaman a servicios ya migrados necesitan pasar `UserIdentity`. Como `$this->getUser()` retorna `?UserInterface`, y `AppUserInterface extends UserIdentity`, el cast funciona:

```php
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
- [ ] Otros controllers que usan servicios migrados

**Nota:** Si el controller también usa servicios que aún esperan `User` concreto, mantener el import de User por ahora.

- [ ] Commit: "refactor: controllers cast to UserIdentity for domain services"

### Task 15: Correr tests
- [ ] `cd backend && php vendor/bin/phpunit`
- [ ] Verificar 0 failures
- [ ] Commit tag: "checkpoint: domain layer migration complete, all tests pass"

---

## Fase 4: Rename User → UserAccount

### Task 16: Rename Entity + Repository

**Pasos de filesystem:**
- [ ] `git mv backend/src/Entity/User.php backend/src/Entity/UserAccount.php`
- [ ] `git mv backend/src/Repository/UserRepository.php backend/src/Repository/UserAccountRepository.php`

**En `UserAccount.php`:**
- [ ] `class User` → `class UserAccount`
- [ ] Eliminar `#[ORM\Table(name: 'user_account')]`
- [ ] `repositoryClass: UserRepository::class` → `UserAccountRepository::class`
- [ ] `use App\Repository\UserRepository;` → `use App\Repository\UserAccountRepository;`

**En `UserAccountRepository.php`:**
- [ ] `class UserRepository` → `class UserAccountRepository`
- [ ] `User::class` → `UserAccount::class` internamente

- [ ] Commit: "refactor: rename User to UserAccount, remove explicit table name"

### Task 17: Actualizar 10 entidades con relaciones FK

Cada una: `use App\Entity\User;` → `use App\Entity\UserAccount;`, `targetEntity: User::class` → `UserAccount::class`, type hints `?User` → `?UserAccount`.

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

### Task 18: Actualizar servicios que usan clase concreta

Servicios que persisten relaciones FK (necesitan `UserAccount`):

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

Cada uno: `use App\Entity\User;` → `use App\Entity\UserAccount;`, type hints `User` → `UserAccount`.

- [ ] Commit: "refactor: update services with FK dependencies from User to UserAccount"

### Task 19: Actualizar controllers

20 controllers. Cada uno: import + type hints + docblocks.

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

**Nota:** Controllers que ya migraron a `UserIdentity` en Task 14 pueden no necesitar import de UserAccount si no lo usan directamente. Verificar caso por caso.

- [ ] Commit: "refactor: update controllers from User to UserAccount"

### Task 20: Actualizar forms
- [ ] `Form/CustomerUserType.php`
- [ ] `Form/DriverType.php`
- [ ] `Form/RouteType.php`
- [ ] Commit: "refactor: update forms from User to UserAccount"

### Task 21: Actualizar EventSubscribers
- [ ] `EventSubscriber/AuditSubscriber.php` — `User::class` en AUDITED_ENTITIES + `em->getReference`
- [ ] `EventSubscriber/LoginAuditSubscriber.php` — entityType string (cosmético)
- [ ] Commit: "refactor: update event subscribers from User to UserAccount"

### Task 22: Actualizar fixtures, commands, event listeners
- [ ] `DataFixtures/AdminUserFixture.php` — `new User(` → `new UserAccount(`
- [ ] `Command/CreateAdminCommand.php` — ídem
- [ ] `Command/TestRoutingCommand.php` — type hints
- [ ] `EventListener/Domain/RouteEventLogListener.php` — `UserRepository` → `UserAccountRepository`
- [ ] `Repository/NotificationRepository.php` — imports y type hints
- [ ] Commit: "refactor: update fixtures, commands, listeners from User to UserAccount"

### Task 23: Actualizar Geocoding (si referencia User)
- [ ] Verificar `Geocoding/NominatimGeocoder.php` — aparece en grep con 2 matches
- [ ] Actualizar si necesario
- [ ] Commit solo si cambia

---

## Fase 5: Actualizar tests

### Task 24: Actualizar todos los tests (17 archivos)

Cada uno: `use App\Entity\User;` → `use App\Entity\UserAccount;`, `new User(` → `new UserAccount(`, `User::class` → `UserAccount::class`.

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

## Fase 6: Verificación final

### Task 25: Schema validation
- [ ] `cd backend && php bin/console doctrine:schema:validate`
- [ ] Verificar mapping correcto, tabla sigue siendo `user_account`

### Task 26: Test suite completo
- [ ] `cd backend && php vendor/bin/phpunit`
- [ ] 0 failures, 0 errors

### Task 27: Lint
- [ ] `cd backend && make lint`
- [ ] 0 syntax errors

### Task 28: Grep final — no quedan referencias a `App\Entity\User`
- [ ] `grep -r "App\\Entity\\User[^A]" backend/src/ backend/tests/` — 0 resultados
- [ ] `grep -r "new User(" backend/src/ backend/tests/` — 0 resultados
- [ ] `grep -r "instanceof User[^AIi]" backend/src/ backend/tests/` — 0 resultados (UserIdentity y AppUserInterface son OK)

### Task 29: Actualizar docs/knowledge
- [ ] Verificar `docs/knowledge/domain-model.md` y `docs/knowledge/security.md`
- [ ] Actualizar referencias User → UserAccount
- [ ] Documentar nueva capa de dominio `App\Domain\Model\UserIdentity`
- [ ] Commit: "docs: update knowledge modules for UserAccount rename and domain layer"
