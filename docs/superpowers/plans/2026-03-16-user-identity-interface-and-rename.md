# Plan: User Identity Interface + Rename User → UserAccount

**Spec:** `docs/superpowers/specs/2026-03-16-user-identity-interface-and-rename-design.md`
**Goal:** Eliminar el acoplamiento directo a `User` entity, unificar `User` y `ApiKeyUser` bajo `AppUserInterface`, renombrar `User` → `UserAccount` para que Doctrine genere `user_account` automáticamente.
**Archivos afectados:** 79 (62 src + 17 tests)

---

## Fase 1: Crear AppUserInterface y adaptar clases existentes

### Task 1: Crear AppUserInterface
**Archivo:** `backend/src/Security/AppUserInterface.php` (nuevo)

- [ ] Crear la interfaz:
```php
<?php
declare(strict_types=1);
namespace App\Security;

use App\Entity\Customer;
use Symfony\Component\Security\Core\User\UserInterface;

interface AppUserInterface extends UserInterface
{
    public function getId(): ?string;
    public function getPublicIdString(): string;
    public function hasRole(string $role): bool;
    public function getCustomer(): ?Customer;
    public function isActive(): bool;
    public function getName(): ?string;
}
```
- [ ] Commit: "feat: create AppUserInterface extending UserInterface"

### Task 2: User implements AppUserInterface
**Archivo:** `backend/src/Entity/User.php`

- [ ] Añadir `use App\Security\AppUserInterface;`
- [ ] Cambiar `class User implements UserInterface, PasswordAuthenticatedUserInterface, SoftDeletableInterface` → `class User implements AppUserInterface, PasswordAuthenticatedUserInterface, SoftDeletableInterface`
- [ ] (AppUserInterface ya extiende UserInterface, así que no se pierde nada)
- [ ] Verificar que User ya tiene todos los métodos de la interfaz: `getId()`, `getPublicIdString()`, `hasRole()`, `getCustomer()`, `isActive()`, `getName()` — todos existen
- [ ] Commit: "feat: User implements AppUserInterface"

### Task 3: ApiKeyUser implements AppUserInterface
**Archivo:** `backend/src/Security/ApiKeyUser.php`

- [ ] Cambiar `implements UserInterface` → `implements AppUserInterface`
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
- [ ] `getCustomer()` ya existe y retorna `Customer` (compatible con `?Customer` de la interfaz)
- [ ] Correr tests: `php vendor/bin/phpunit`
- [ ] Commit: "feat: ApiKeyUser implements AppUserInterface"

---

## Fase 2: Migrar security layer a AppUserInterface

### Task 4: Migrar BaseVoter
**Archivo:** `backend/src/Security/Voter/BaseVoter.php`

- [ ] Cambiar `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] Línea 18: `!$user instanceof User` → `!$user instanceof AppUserInterface`
- [ ] Línea 29: `abstract protected function isGrantedForUser(string $attribute, mixed $subject, User $user): bool;` → `..., AppUserInterface $user): bool;`
- [ ] Commit: "refactor: BaseVoter uses AppUserInterface"

### Task 5: Migrar UserVoter
**Archivo:** `backend/src/Security/Voter/UserVoter.php`

- [ ] Cambiar `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `isGrantedForUser(... User $user)` → `... AppUserInterface $user`
- [ ] `$subject instanceof User` → `$subject instanceof AppUserInterface`
- [ ] Commit: "refactor: UserVoter uses AppUserInterface"

### Task 6: Migrar UserChecker
**Archivo:** `backend/src/Security/UserChecker.php`

- [ ] Cambiar `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `!$user instanceof User` → `!$user instanceof AppUserInterface`
- [ ] Commit: "refactor: UserChecker uses AppUserInterface"

### Task 7: Migrar DoctrineCustomerFilterSubscriber
**Archivo:** `backend/src/EventSubscriber/DoctrineCustomerFilterSubscriber.php`

- [ ] Reemplazar imports: `use App\Entity\User;` y `use App\Security\ApiKeyUser;` → `use App\Security\AppUserInterface;`
- [ ] Unificar las dos ramas (ApiKeyUser y User) en una sola:
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
- [ ] Commit: "refactor: DoctrineCustomerFilterSubscriber uses AppUserInterface, eliminates instanceof branching"

### Task 8: Migrar TenantContext
**Archivo:** `backend/src/Provider/TenantContext.php`

- [ ] Cambiar `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `$user instanceof User` → `$user instanceof AppUserInterface`
- [ ] Commit: "refactor: TenantContext uses AppUserInterface"

### Task 9: Migrar TopicResolver
**Archivo:** `backend/src/Security/TopicResolver.php`

- [ ] Cambiar `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `resolveForUser(User $user, ...)` → `resolveForUser(AppUserInterface $user, ...)`
- [ ] Commit: "refactor: TopicResolver uses AppUserInterface"

### Task 10: Migrar AuditSubscriber
**Archivo:** `backend/src/EventSubscriber/AuditSubscriber.php`

- [ ] Cambiar `use App\Entity\User;` → `use App\Security\AppUserInterface;` (mantener también `use App\Entity\User;` porque se usa en AUDITED_ENTITIES y `em->getReference()`)
- [ ] Nota: `AUDITED_ENTITIES` contiene `User::class` para auditar cambios a la entidad User — esto sigue necesitando la clase concreta. Cambiar a `UserAccount::class` en la Fase 3.
- [ ] Línea 134: `$securityUser instanceof User` → `$securityUser instanceof AppUserInterface`
- [ ] Línea 135: `$em->getReference(User::class, ...)` — mantener como User::class por ahora, cambiar en Fase 3
- [ ] Commit: "refactor: AuditSubscriber uses AppUserInterface for actor resolution"

### Task 11: Migrar AuditLogger
**Archivo:** `backend/src/Service/AuditLogger.php`

- [ ] Cambiar `use App\Entity\User;` → `use App\Security\AppUserInterface;`
- [ ] `log(?User $actor, ...)` → `log(?AppUserInterface $actor, ...)`
- [ ] Nota: `AuditLog` entity tiene `ManyToOne(targetEntity: User::class)` en `$actor` — el constructor de AuditLog espera `?User`. Necesitamos evaluar si AuditLogger puede recibir la interfaz pero pasar solo User al entity. **Decisión:** AuditLog sigue recibiendo `?User` en su constructor (es una FK directa a user_account). AuditLogger recibirá `?AppUserInterface` pero necesitará resolver a User para persistir. Alternativa más simple: mantener `?User` en AuditLogger y cambiar a `?UserAccount` en Fase 3.
- [ ] **Revisión:** Mantener `?User` en AuditLogger por ahora. Se cambiará a `?UserAccount` en Fase 3.
- [ ] Commit: (no se necesita commit separado, se hace en Fase 3)

### Task 12: Migrar servicios que solo usan métodos de la interfaz
**Archivos que pueden cambiar `User` → `AppUserInterface` en type hints:**

Los siguientes servicios solo usan métodos de AppUserInterface (`getId()`, `getRoles()`, `getCustomer()`, `hasRole()`, `getPublicIdString()`, `getName()`, `isActive()`):

- [ ] `Service/MercureJwtFactory.php` — `User $user` → `AppUserInterface $user`
- [ ] `Service/VisibilityScopeService.php` — `User $user` → `AppUserInterface $user`
- [ ] `Service/NotificationService.php` — Verificar si usa métodos más allá de la interfaz. `notify(User $user, ...)` — necesita FK a user_account para persistir Notification. **Mantener User** (cambiar a UserAccount en Fase 3).
- [ ] `Service/SearchService.php` — `User $user` → `AppUserInterface $user`
- [ ] `Service/AiAssistantService.php` — `User $user` → `AppUserInterface $user`
- [ ] `Service/ReportingService.php` — Verificar uso. Si solo filtra por user ID → `AppUserInterface`
- [ ] `Application/Fleet/FleetOverviewService.php` — `User $user` → `AppUserInterface $user`
- [ ] `Application/Route/RouteLifecycleService.php` — Usa `$driver->getId()` para comparar. **Pero** también hace `$route->setDriver($driver)` que necesita `?User`. **Mantener User** (cambiar en Fase 3).
- [ ] `Application/Delivery/DeliveryService.php` — Crea Pod con `$driver`. **Mantener User** (cambiar en Fase 3).
- [ ] `Service/DriverScoringService.php` — Accede a relaciones de User. **Mantener User** (cambiar en Fase 3).
- [ ] `Service/DriverAvailabilityService.php` — Persiste DriverAvailability con FK a User. **Mantener User**.
- [ ] `Service/DriverActionService.php` — Persiste DriverAction con FK a User. **Mantener User**.
- [ ] `Service/WebPushService.php` — Queries PushSubscription por User. **Mantener User**.
- [ ] `Service/EmailNotificationService.php` — Queries User. **Mantener User**.

**Servicios que SÍ pueden migrar a AppUserInterface (no persisten relaciones con User):**
1. `Service/MercureJwtFactory.php`
2. `Service/VisibilityScopeService.php`
3. `Service/SearchService.php`
4. `Service/AiAssistantService.php`
5. `Service/ReportingService.php`
6. `Application/Fleet/FleetOverviewService.php`

- [ ] Actualizar estos 6 servicios
- [ ] Commit: "refactor: migrate read-only services to AppUserInterface"

### Task 13: Migrar controllers que solo leen User
**Controllers que hacen `$this->getUser()` y pasan a servicios:**

Para controllers: `$this->getUser()` retorna `?UserInterface`. Necesitamos cast a `AppUserInterface`. El patrón será:
```php
$user = $this->getUser();
assert($user instanceof AppUserInterface);
```

O usar un helper method. **Decisión:** Esto se resuelve mejor en Fase 3 cuando el rename está hecho. Los controllers que pasan User a servicios que ya usan AppUserInterface necesitarán el cast, pero los que pasan a servicios que siguen usando User concreto no.

- [ ] Actualizar controllers que pasan a servicios ya migrados (MercureJwtFactory, VisibilityScopeService, SearchService, etc.)
- [ ] Commit: "refactor: controllers use AppUserInterface for read-only operations"

### Task 14: Correr tests completos
- [ ] `cd backend && php vendor/bin/phpunit`
- [ ] Verificar 0 failures
- [ ] Commit tag: "checkpoint: all tests pass after AppUserInterface migration"

---

## Fase 3: Rename User → UserAccount

### Task 15: Rename Entity + Repository
**Archivos:**
- `backend/src/Entity/User.php` → `backend/src/Entity/UserAccount.php`
- `backend/src/Repository/UserRepository.php` → `backend/src/Repository/UserAccountRepository.php`

- [ ] Renombrar archivos
- [ ] En `UserAccount.php`:
  - Cambiar `class User` → `class UserAccount`
  - Eliminar `#[ORM\Table(name: 'user_account')]` (Doctrine lo generará automáticamente)
  - Actualizar `repositoryClass: UserRepository::class` → `UserAccountRepository::class`
  - Actualizar `use App\Repository\UserRepository;` → `UserAccountRepository`
- [ ] En `UserAccountRepository.php`:
  - Cambiar `class UserRepository` → `class UserAccountRepository`
  - Actualizar todas las referencias internas a `User::class` → `UserAccount::class`
- [ ] Commit: "refactor: rename User entity to UserAccount"

### Task 16: Actualizar 10 entidades con relaciones
**Archivos:**
1. `Entity/AuditLog.php` — `targetEntity: User::class` → `UserAccount::class`, import
2. `Entity/DriverAction.php` — ídem
3. `Entity/DriverAvailability.php` — ídem
4. `Entity/DriverFeedback.php` — ídem
5. `Entity/Notification.php` — ídem
6. `Entity/Pod.php` — ídem
7. `Entity/PushSubscription.php` — ídem
8. `Entity/Route.php` — ídem
9. `Entity/RouteEvent.php` — ídem
10. `Entity/VehicleInspection.php` — ídem

Para cada uno:
- [ ] `use App\Entity\User;` → `use App\Entity\UserAccount;`
- [ ] `targetEntity: User::class` → `targetEntity: UserAccount::class`
- [ ] Type hints `?User` → `?UserAccount` en properties y setters/getters
- [ ] Commit: "refactor: update entity relations from User to UserAccount"

### Task 17: Actualizar servicios que persisten con User
**Archivos que mantuvieron User en Fase 2 (necesitan la clase concreta para FKs):**
1. `Service/NotificationService.php`
2. `Service/AuditLogger.php`
3. `Service/DriverScoringService.php`
4. `Service/DriverAvailabilityService.php`
5. `Service/DriverActionService.php`
6. `Service/WebPushService.php`
7. `Service/EmailNotificationService.php`
8. `Service/DemoScenarioBuilder.php`
9. `Service/DemoScenarioResult.php`
10. `Service/OperatorKpiService.php`
11. `Application/Route/RouteLifecycleService.php`
12. `Application/Delivery/DeliveryService.php`
13. `Application/Delivery/DeliveryContext.php`

Para cada uno:
- [ ] `use App\Entity\User;` → `use App\Entity\UserAccount;`
- [ ] Type hints `User` → `UserAccount` en parámetros y variables
- [ ] Commit: "refactor: update services from User to UserAccount"

### Task 18: Actualizar controllers
**20 controllers:**
1-20. (lista completa del inventario)

Para cada uno:
- [ ] `use App\Entity\User;` → `use App\Entity\UserAccount;`
- [ ] Type hints y docblocks `User` → `UserAccount`
- [ ] `/** @var User $user */` → `/** @var UserAccount $user */`
- [ ] Commit: "refactor: update controllers from User to UserAccount"

### Task 19: Actualizar forms
**3 archivos:**
1. `Form/CustomerUserType.php`
2. `Form/DriverType.php`
3. `Form/RouteType.php`

- [ ] Imports y `User::class` → `UserAccount::class`
- [ ] Commit: "refactor: update forms from User to UserAccount"

### Task 20: Actualizar EventSubscribers
**2 archivos:**
1. `EventSubscriber/AuditSubscriber.php` — `User::class` en AUDITED_ENTITIES, `em->getReference(User::class)`
2. `EventSubscriber/LoginAuditSubscriber.php` — entityType string 'User' (cosmético, no rompe)

- [ ] Actualizar imports y references
- [ ] Commit: "refactor: update event subscribers from User to UserAccount"

### Task 21: Actualizar Security layer con la clase concreta
**Archivos que necesitan UserAccount por `instanceof` de la entidad específica (UserVoter):**
- `UserVoter.php` — `$subject instanceof User` en `supports()`. Este voter verifica permisos sobre una entidad User específica pasada como subject. Mantener como `$subject instanceof UserAccount` ya que solo aplica a la entidad persistida.

- [ ] Commit: "refactor: UserVoter subject check uses UserAccount"

### Task 22: Actualizar fixtures, commands
**3 archivos:**
1. `DataFixtures/AdminUserFixture.php` — `new User(...)` → `new UserAccount(...)`
2. `Command/CreateAdminCommand.php` — `new User(...)` → `new UserAccount(...)`
3. `Command/TestRoutingCommand.php` — type hints

- [ ] Actualizar imports, `new User(` → `new UserAccount(`
- [ ] Commit: "refactor: update fixtures and commands from User to UserAccount"

### Task 23: Actualizar Enum (si referencia User)
**Archivo:** `backend/src/Enum/UserRole.php`

- [ ] Verificar si referencia la clase User — probablemente solo define roles, no necesita cambios
- [ ] Commit: solo si necesario

### Task 24: Actualizar EventListener
**Archivo:** `backend/src/EventListener/Domain/RouteEventLogListener.php`

- [ ] Tiene `UserRepository` import — cambiar a `UserAccountRepository`
- [ ] Commit: "refactor: update RouteEventLogListener from User to UserAccount"

### Task 25: Actualizar Repository NotificationRepository
**Archivo:** `backend/src/Repository/NotificationRepository.php`

- [ ] Imports y type hints de User → UserAccount
- [ ] Commit: "refactor: update NotificationRepository from User to UserAccount"

### Task 26: Actualizar tests (17 archivos)
**Archivos:**
1. `tests/Factory/TestEntityFactory.php` — `new User(` → `new UserAccount(`
2. `tests/Functional/CustomerTenantFilterTest.php`
3. `tests/Functional/DriverApiTest.php`
4. `tests/Functional/Smoke/AdminPageSmokeTest.php`
5. `tests/Functional/Smoke/PageSmokeTest.php`
6. `tests/Unit/AiAssistantControllerTest.php`
7. `tests/Unit/AiAssistantServiceTest.php`
8. `tests/Unit/Command/DemoSetupCommandTest.php`
9. `tests/Unit/DeliveryServiceTest.php`
10. `tests/Unit/DriverActionServiceTest.php`
11. `tests/Unit/EventListener/Domain/RouteEventLogListenerTest.php`
12. `tests/Unit/MercureJwtFactoryTest.php`
13. `tests/Unit/Provider/TenantContextTest.php`
14. `tests/Unit/RouteLifecycleServiceTest.php`
15. `tests/Unit/SearchServiceTest.php`
16. `tests/Unit/SecurityTest.php`
17. `tests/Unit/Service/DemoScenarioBuilderTest.php`
18. `tests/Unit/TopicResolverTest.php`

Para cada uno:
- [ ] `use App\Entity\User;` → `use App\Entity\UserAccount;`
- [ ] `new User(` → `new UserAccount(`
- [ ] `User::class` → `UserAccount::class`
- [ ] Type hints
- [ ] Commit: "refactor: update tests from User to UserAccount"

---

## Fase 4: Verificación

### Task 27: Schema validation
- [ ] `cd backend && php bin/console doctrine:schema:validate`
- [ ] Verificar que el mapping es correcto y la tabla sigue siendo `user_account`
- [ ] Commit: solo si hay ajustes

### Task 28: Correr test suite completo
- [ ] `cd backend && php vendor/bin/phpunit`
- [ ] Verificar 0 failures, 0 errors
- [ ] Si hay fallos, corregir y commitear

### Task 29: Lint
- [ ] `cd backend && make lint` (o `find src -name "*.php" -exec php -l {} \;`)
- [ ] Verificar 0 syntax errors

### Task 30: Verificar que no queden referencias a `App\Entity\User`
- [ ] `grep -r "App\\\\Entity\\\\User[^A]" backend/src/ backend/tests/` — no debería haber resultados
- [ ] `grep -r "new User(" backend/src/ backend/tests/` — no debería haber resultados
- [ ] `grep -r "instanceof User[^A]" backend/src/ backend/tests/` — solo en UserVoter para subject check

### Task 31: Actualizar docs/knowledge si necesario
- [ ] Verificar `docs/knowledge/domain-model.md` y `docs/knowledge/security.md`
- [ ] Actualizar referencias a User → UserAccount
- [ ] Commit: "docs: update knowledge modules for UserAccount rename"
