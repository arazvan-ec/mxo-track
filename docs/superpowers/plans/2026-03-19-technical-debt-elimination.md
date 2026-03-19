# Plan: Eliminación Completa de Deuda Técnica

**Spec:** `docs/superpowers/specs/2026-03-19-technical-debt-elimination-design.md`
**Goal:** Eliminar toda la deuda técnica documentada en CLAUDE.md siguiendo DDD, SOLID y TDD
**Approach:** C — Por impacto de negocio

---

## Fase 1: Foundation — Repository Interfaces + DIP Fixes

**Objetivo:** Desbloquear testabilidad sin cambiar entidades. Crear interfaces de repositorio en el dominio y migrar servicios críticos para depender de ellas.

**Archivos a crear:**
- `src/Domain/Route/Repository/RouteRepositoryInterface.php`
- `src/Domain/Route/Repository/RouteStopRepositoryInterface.php`
- `src/Domain/Route/Repository/RouteEventRepositoryInterface.php`
- `src/Infrastructure/Route/Doctrine/DoctrineRouteRepository.php`
- `src/Infrastructure/Route/Doctrine/DoctrineRouteStopRepository.php`
- `src/Infrastructure/Route/Doctrine/DoctrineRouteEventRepository.php`

**Archivos a modificar:**
- `src/Service/RouteOptimizationService.php` — cambiar EntityManager → RouteRepositoryInterface + RouteStopRepositoryInterface
- `src/Service/RouteBuilder.php` — cambiar EntityManager → interfaces
- `src/Service/DeliveryService.php` — cambiar repos concretos → interfaces
- `src/Service/RouteSnapshotManager.php` — evaluar dependencias
- `config/services.yaml` — bind interfaces a implementaciones (o autowire)

### Preparación

- [ ] **Task 1.0: Auditar servicios y sus dependencias concretas**
  1. `grep -r "EntityManagerInterface" backend/src/Service/` — listar todos los servicios con EM
  2. Para cada servicio: listar qué métodos de repositorio usa realmente
  3. Documentar hallazgos para diseñar interfaces mínimas
  4. Commit: `docs: audit service dependencies for DIP migration`

### Route Repository Interface

- [ ] **Task 1.1: Test — RouteRepositoryInterface contract test**
  1. Crear `tests/Domain/Route/Repository/RouteRepositoryContractTest.php`
  2. Test abstracto que verifica: `findByPublicId()`, `save()`, `remove()`, `findById()`
  3. Tests: `testFindByPublicIdReturnsRouteWhenExists`, `testFindByPublicIdReturnsNullWhenNotExists`, `testSavePersistsRoute`, `testRemoveDeletesRoute`
  4. Run tests → RED (interface no existe)
  5. Commit: `test: add RouteRepositoryInterface contract test`

- [ ] **Task 1.2: Implement — RouteRepositoryInterface + DoctrineRouteRepository**
  1. Crear `src/Domain/Route/Repository/RouteRepositoryInterface.php` con métodos del test
  2. Crear `src/Infrastructure/Route/Doctrine/DoctrineRouteRepository.php` implementando interface
  3. Mover métodos relevantes desde `src/Repository/RouteRepository.php` existente
  4. Crear test concreto que extienda contract test con Doctrine implementation
  5. Run tests → GREEN
  6. Commit: `feat: add RouteRepositoryInterface and Doctrine implementation`

### RouteStop Repository Interface

- [ ] **Task 1.3: Test + Implement — RouteStopRepositoryInterface**
  1. Analizar qué métodos usan RouteOptimizationService, RouteBuilder, DeliveryService sobre RouteStop
  2. Crear `src/Domain/Route/Repository/RouteStopRepositoryInterface.php` con métodos mínimos
  3. Crear `src/Infrastructure/Route/Doctrine/DoctrineRouteStopRepository.php`
  4. Tests: contract test + Doctrine implementation test
  5. Run tests → GREEN
  6. Commit: `feat: add RouteStopRepositoryInterface and Doctrine implementation`

### RouteEvent Repository Interface

- [ ] **Task 1.4: Test + Implement — RouteEventRepositoryInterface**
  1. Analizar uso de RouteEventRepository en servicios
  2. Crear `src/Domain/Route/Repository/RouteEventRepositoryInterface.php`
  3. Crear `src/Infrastructure/Route/Doctrine/DoctrineRouteEventRepository.php`
  4. Tests: contract test + Doctrine implementation test
  5. Run tests → GREEN
  6. Commit: `feat: add RouteEventRepositoryInterface and Doctrine implementation`

### Migrar servicios a interfaces

- [ ] **Task 1.5: Migrar RouteOptimizationService a interfaces**
  1. Leer `src/Service/RouteOptimizationService.php` completo
  2. Escribir test unitario con mock de RouteRepositoryInterface (sin DB)
  3. Run test → RED
  4. Cambiar constructor: `EntityManagerInterface $em` → repository interfaces
  5. Adaptar métodos internos: `$this->em->getRepository()` → `$this->routes->findBy...`
  6. Run test → GREEN
  7. Run full test suite → verify no regressions
  8. Commit: `refactor: migrate RouteOptimizationService to repository interfaces`

- [ ] **Task 1.6: Migrar RouteBuilder a interfaces**
  1. Leer `src/Service/RouteBuilder.php` completo
  2. Escribir test unitario con mocks
  3. Cambiar constructor y adaptar métodos
  4. Run tests → GREEN
  5. Commit: `refactor: migrate RouteBuilder to repository interfaces`

- [ ] **Task 1.7: Migrar DeliveryService a interfaces**
  1. Leer `src/Service/DeliveryService.php` completo — depende de RouteStopRepository y ShipmentRepository concretos
  2. Nota: ShipmentRepositoryInterface aún no existe (viene en Fase 5). Para ahora, solo migrar la dependencia de RouteStop.
  3. Test unitario con mock de RouteStopRepositoryInterface
  4. Cambiar constructor parcialmente
  5. Run tests → GREEN
  6. Commit: `refactor: migrate DeliveryService to RouteStopRepositoryInterface`

- [ ] **Task 1.8: Verificación de Fase 1**
  1. Run full test suite: `php vendor/bin/phpunit`
  2. Run lint: `make lint`
  3. Verificar que no quedan `EntityManagerInterface` en servicios migrados
  4. Commit: `chore: verify Phase 1 completion — DIP fixes for Route context`

---

## Fase 2: Event Sourcing + DDD — Route Planning

**Objetivo:** Migrar Route Planning a event sourcing puro + entidades como Domain POPOs. RouteEvent[] se convierte en la fuente de verdad del estado.

**Pre-requisito:** Fase 1 completada (servicios ya dependen de interfaces)

**Sub-fases:** 2A → 2B → 2C → 2D (estrictamente secuencial)

**Estado actual (auditoría):**
- 15 RouteEventType definidos, solo 9 dispatched
- Patrón state-first: `$route->start()` → luego `dispatch(RouteStarted)`
- State mutations sin evento: name, originLocation, autoReoptimize, aiAnalysis, capacity metrics, stop address/notes/aiNotes
- Servicios crean RouteEvent via `RouteEventLogListener` que escucha domain events

---

### Sub-fase 2A: Event Dispatch Completeness

**Objetivo:** Todos los state mutations producen un evento. Prerequisito para invertir el flujo.

- [ ] **Task 2A.0: Auditar dispatch sites actuales**
  1. `grep -r "new RouteEvent\|dispatch.*Route" backend/src/` — listar todos los puntos de dispatch
  2. Para cada state mutation en Route y RouteStop: verificar si produce evento
  3. Documentar gaps exactos (mutation sin evento)
  4. Commit: `docs: audit RouteEvent dispatch completeness gaps`

- [ ] **Task 2A.1: Test + Implement — Dispatch ASSIGNED event**
  1. Test: cuando se cambia driver/vehicle en RouteAdminController, se debe crear RouteEvent(ASSIGNED)
  2. Verificar que `RouteAssigned` domain event ya existe y se dispatcha desde controller
  3. Si no se dispatcha: añadir dispatch en RouteAdminController.edit()
  4. Verificar que RouteEventLogListener persiste RouteEvent(ASSIGNED) con payload {vehicle_public_id, driver_user_id}
  5. Run tests → GREEN
  6. Commit: `feat: ensure ASSIGNED event dispatched on driver/vehicle change`

- [ ] **Task 2A.2: Test + Implement — Dispatch CANCELLED event**
  1. Test: cuando se cancela una ruta, se debe crear RouteEvent(CANCELLED)
  2. Verificar/añadir dispatch de `RouteCancelled` domain event
  3. Verificar RouteEventLogListener persiste con payload {reason, cancelled_by_user_id}
  4. Run tests → GREEN
  5. Commit: `feat: ensure CANCELLED event dispatched on route cancellation`

- [ ] **Task 2A.3: Test + Implement — Dispatch REOPTIMIZED event**
  1. Test: cuando RouteOptimizationService re-optimiza, se debe crear RouteEvent(REOPTIMIZED)
  2. Crear domain event `RouteReoptimized` si no existe
  3. Dispatch desde RouteOptimizationService después de reordenar stops
  4. RouteEventLogListener persiste con payload {new_stop_order, improvement_percent}
  5. Run tests → GREEN
  6. Commit: `feat: dispatch REOPTIMIZED event from RouteOptimizationService`

- [ ] **Task 2A.4: Test + Implement — Dispatch STOPS_REORDERED event**
  1. Test: cuando se reordenan stops manualmente (no via optimización), se crea RouteEvent(STOPS_REORDERED)
  2. Crear domain event `StopsReordered` si no existe
  3. Dispatch desde el punto donde se cambia RouteStop.sequence manualmente
  4. Payload: {previous_order, new_order}
  5. Run tests → GREEN
  6. Commit: `feat: dispatch STOPS_REORDERED event on manual reorder`

- [ ] **Task 2A.5: Test + Implement — Dispatch STOP_SKIPPED event**
  1. Test: cuando se salta una parada, se crea RouteEvent(STOP_SKIPPED)
  2. Crear lógica de skip en RouteStop (si no existe): `markSkipped(string $reason)`
  3. Crear domain event `StopSkipped`
  4. Dispatch + listener
  5. Run tests → GREEN
  6. Commit: `feat: implement stop skipping with STOP_SKIPPED event`

- [ ] **Task 2A.6: Test + Implement — Nuevos event types para mutations no cubiertas**
  1. Añadir a RouteEventType enum: `ROUTE_RENAMED`, `ORIGIN_CHANGED`, `CONFIG_CHANGED`, `METRICS_CALCULATED`, `AI_ANALYSIS_COMPLETED`
  2. Para cada uno:
     - Crear domain event POPO (o reutilizar genérico con payload)
     - Dispatch desde el punto de mutation
     - RouteEventLogListener persiste con payload apropiado
  3. Test: cada mutation produce su evento correspondiente
  4. Run tests → GREEN
  5. Commit: `feat: add event types for all Route state mutations`

- [ ] **Task 2A.7: Verificación de Sub-fase 2A**
  1. Full test suite → GREEN
  2. Verificar: CADA setter/mutation en Route y RouteStop tiene un evento asociado
  3. `grep -r "RouteEventType::" backend/src/` → todos los 20 tipos se usan
  4. Commit: `chore: verify Phase 2A — all Route mutations produce events`

---

### Sub-fase 2B: Event-First State Transitions

**Objetivo:** Invertir el patrón. Los eventos se dispatchen PRIMERO, y listeners aplican el cambio de estado. Route Aggregate aplica eventos via `apply()`.

- [ ] **Task 2B.0: Diseñar Route Aggregate con apply()**
  1. Diseñar método `Route::apply(RouteEvent $event): void` que aplica un evento al estado
  2. Diseñar cómo el aggregate decide si un evento es válido (invariants)
  3. Diseñar cómo se reconstruye desde stream: `Route::rebuildFromEvents(array $events): self`
  4. Documentar diseño
  5. Commit: `docs: design Route Aggregate event application pattern`

- [ ] **Task 2B.1: Test — Route Aggregate apply() para lifecycle events**
  1. Crear `tests/Domain/Route/Model/RouteAggregateTest.php`
  2. Tests:
     - `testApplyCreatedSetsPlannedStatus`
     - `testApplyStartedTransitionsToActive`
     - `testApplyCompletedTransitionsToDone`
     - `testApplyCancelledTransitionsToCancelled`
     - `testCannotApplyStartedToNonPlannedRoute`
     - `testApplyAssignedSetsDriverAndVehicle`
  3. Run → RED
  4. Commit: `test: add Route Aggregate apply() tests for lifecycle events`

- [ ] **Task 2B.2: Implement — Route::apply() para lifecycle events**
  1. Añadir `apply(RouteEvent $event): void` a Route
  2. Switch sobre event type → aplicar cambio de estado correspondiente
  3. Mantener invariants (ej: no puede start() si no es PLANNED)
  4. Run tests → GREEN
  5. Commit: `feat: implement Route::apply() for lifecycle events`

- [ ] **Task 2B.3: Test + Implement — Route::apply() para stop events**
  1. Tests:
     - `testApplyStopDeliveredUpdatesStopStatus`
     - `testApplyStopExceptionSetsExceptionCode`
     - `testApplyStopSkippedUpdatesStatus`
     - `testApplyStopsReorderedUpdatesSequence`
  2. Implementar apply() para STOP_DELIVERED, STOP_EXCEPTION, STOP_SKIPPED, STOPS_REORDERED
  3. Nota: Route delega a RouteStop para aplicar stop-level changes
  4. Run tests → GREEN
  5. Commit: `feat: implement Route::apply() for stop events`

- [ ] **Task 2B.4: Test + Implement — Route::apply() para optimization + metadata events**
  1. Tests para: OPTIMIZED, REOPTIMIZED, METRICS_CALCULATED, ROUTE_RENAMED, ORIGIN_CHANGED, CONFIG_CHANGED, AI_ANALYSIS_COMPLETED
  2. Implementar apply() para cada uno
  3. Run tests → GREEN
  4. Commit: `feat: implement Route::apply() for optimization and metadata events`

- [ ] **Task 2B.5: Test + Implement — Route::rebuildFromEvents()**
  1. Tests:
     - `testRebuildFromEmptyStreamThrows`
     - `testRebuildFromCreatedEventReturnsPlannedRoute`
     - `testRebuildFullLifecycleReturnsCorrectFinalState`
     - `testRebuildWithStopEventsReturnsCorrectStopStates`
  2. Implementar `static rebuildFromEvents(array $events): self`
  3. Aplica eventos secuencialmente via `apply()`
  4. Run tests → GREEN
  5. Commit: `feat: implement Route::rebuildFromEvents() for event replay`

- [ ] **Task 2B.6: Refactor — Invertir flujo en RouteLifecycleService**
  1. Antes: `$route->start()` → `dispatch(RouteStarted)`
  2. Después: crear RouteEvent(STARTED) → `$route->apply($event)` → persist event → dispatch domain event para side effects
  3. Test: verificar que el evento se persiste ANTES de que el estado cambie
  4. Run tests → GREEN
  5. Commit: `refactor: invert RouteLifecycleService to events-first pattern`

- [ ] **Task 2B.7: Refactor — Invertir flujo en DeliveryService**
  1. Misma inversión para deliverStop() y reportException()
  2. Antes: `$stop->markDelivered()` → dispatch
  3. Después: crear RouteEvent(STOP_DELIVERED) → `$route->apply($event)` → persist → dispatch
  4. Tests → GREEN
  5. Commit: `refactor: invert DeliveryService to events-first pattern`

- [ ] **Task 2B.8: Refactor — Invertir flujo en RouteOptimizationService**
  1. Misma inversión para re-optimization y reordering
  2. Tests → GREEN
  3. Commit: `refactor: invert RouteOptimizationService to events-first pattern`

- [ ] **Task 2B.9: Refactor — Invertir flujo en RouteBuilder + RouteAdminController**
  1. RouteBuilder: creation + assignment events-first
  2. RouteAdminController: edit (assignment, cancellation) events-first
  3. Tests → GREEN
  4. Commit: `refactor: invert RouteBuilder and RouteAdminController to events-first`

- [ ] **Task 2B.10: Verificación de Sub-fase 2B**
  1. Full test suite → GREEN
  2. Verificar: NINGÚN servicio muta estado directamente sin pasar por apply()
  3. `grep -r "->setStatus\|->setDriver\|->setVehicle" backend/src/Service/` → 0 hits (solo apply() muta)
  4. Commit: `chore: verify Phase 2B — events-first pattern complete`

---

### Sub-fase 2C: Projections + Read Models

**Objetivo:** Queries usan projections materializadas en vez de leer estado ORM directamente. Esto desacopla reads de writes.

- [ ] **Task 2C.0: Diseñar projections**
  1. Listar todas las queries actuales que leen Route/RouteStop state:
     - `findActiveRoutePublicIdsForCustomer()` → usa Route.status
     - `findActiveRoutePublicIdsForDriver()` → usa Route.status + Route.driver
     - Controllers que leen `$route->getStatus()`, `$route->getDriver()`
  2. Diseñar tablas de projection:
     - `route_current_state`: route_id, public_id, status, driver_id, vehicle_id, total_distance_km, etc.
     - `stop_current_status`: stop_id, route_id, status, delivered_at, exception_code
  3. Documentar diseño
  4. Commit: `docs: design Route projections for event sourcing read models`

- [ ] **Task 2C.1: Test + Implement — RouteProjectionListener**
  1. Crear `src/Infrastructure/Route/Projection/RouteProjectionListener.php`
  2. Escucha domain events → actualiza tabla `route_current_state`
  3. Tests:
     - `testProjectionUpdatedOnRouteCreated`
     - `testProjectionUpdatedOnRouteStarted`
     - `testProjectionUpdatedOnDriverAssigned`
  4. Migration: crear tabla `route_current_state`
  5. Run tests → GREEN
  6. Commit: `feat: add RouteProjectionListener for route_current_state`

- [ ] **Task 2C.2: Test + Implement — StopProjectionListener**
  1. Crear `src/Infrastructure/Route/Projection/StopProjectionListener.php`
  2. Escucha stop events → actualiza tabla `stop_current_status`
  3. Tests:
     - `testProjectionUpdatedOnStopDelivered`
     - `testProjectionUpdatedOnStopException`
     - `testProjectionUpdatedOnStopSkipped`
  4. Migration: crear tabla `stop_current_status`
  5. Run tests → GREEN
  6. Commit: `feat: add StopProjectionListener for stop_current_status`

- [ ] **Task 2C.3: Test + Implement — ProjectionRebuilder**
  1. Crear `src/Infrastructure/Route/Projection/ProjectionRebuilder.php`
  2. Console command: `app:projection:rebuild` — replay todos los eventos → reconstruir projections
  3. Tests: rebuild produce el mismo estado que las projections incrementales
  4. Run tests → GREEN
  5. Commit: `feat: add ProjectionRebuilder command for full replay`

- [ ] **Task 2C.4: Migrate — Queries to use projections**
  1. RouteRepository: `findActiveRoutePublicIdsForCustomer()` → query `route_current_state`
  2. RouteRepository: `findActiveRoutePublicIdsForDriver()` → query `route_current_state`
  3. Controllers que leen estado → usar projection o `rebuildFromEvents()` para detail views
  4. Tests → GREEN
  5. Commit: `refactor: migrate Route queries to use projections`

- [ ] **Task 2C.5: Test — Consistency check: rebuild vs projection**
  1. Test de integración que:
     - Crea una ruta con varios eventos
     - Compara resultado de `rebuildFromEvents()` con projection table
     - Verifica que son consistentes
  2. Este test detecta si algún projection listener está out-of-sync
  3. Commit: `test: add projection consistency verification test`

- [ ] **Task 2C.6: Verificación de Sub-fase 2C**
  1. Full test suite → GREEN
  2. `php bin/console doctrine:migrations:migrate -n` → OK (nuevas tablas de projection)
  3. `php bin/console app:projection:rebuild` → OK
  4. Verificar que queries críticas usan projections
  5. Commit: `chore: verify Phase 2C — projections operational`

---

### Sub-fase 2D: Route Entities → Domain POPOs

**Objetivo:** Migrar Route, RouteStop, RouteEvent a POPOs puros con Doctrine XML mapping. Route como Aggregate Root con event replay + time-travel.

- [ ] **Task 2D.0: Mapear todas las referencias a entidades Route**
  1. `grep -r "use App\\Entity\\Route;" backend/src/ backend/tests/` — listar TODOS los imports
  2. `grep -r "use App\\Entity\\RouteStop;" backend/src/ backend/tests/`
  3. `grep -r "use App\\Entity\\RouteEvent;" backend/src/ backend/tests/`
  4. Documentar count de archivos afectados por entidad
  5. Verificar relaciones con otras entidades (RouteSnapshot, Shipment, Vehicle, User)
  6. Commit: `docs: map all Route entity references for DDD migration`

- [ ] **Task 2D.1: Test — Route POPO domain tests (incluyendo event sourcing)**
  1. Crear `tests/Domain/Route/Model/RouteTest.php` (o extender el existente de 2B)
  2. Tests de dominio puro (sin DB):
     - `testRouteStartsInPlannedStatus`
     - `testStartTransitionsToActive`
     - `testCannotStartNonPlannedRoute`
     - `testRebuildFromEventsRestoresFullState`
     - `testTimeTravel_stateAtTimestamp`
  3. Run → RED (clase aún en Entity namespace con ORM)
  4. Commit: `test: add Route POPO domain model tests with event sourcing`

- [ ] **Task 2D.2: Implement — Route POPO**
  1. Copiar `src/Entity/Route.php` → `src/Domain/Route/Model/Route.php`
  2. Cambiar namespace a `App\Domain\Route\Model`
  3. Eliminar TODOS los `#[ORM\...]` attributes
  4. Mantener `apply()`, `rebuildFromEvents()` de fase 2B
  5. Añadir `stateAtTimestamp(DateTimeImmutable $at): self` — replay events hasta timestamp
  6. Run unit tests → GREEN
  7. Commit: `feat: create Route POPO domain model with event sourcing`

- [ ] **Task 2D.3: Implement — RouteStop POPO**
  1. `src/Entity/RouteStop.php` → `src/Domain/Route/Model/RouteStop.php`
  2. Eliminar ORM attributes, mantener lógica de dominio
  3. Tests: `testStopStartsInPendingStatus`, `testMarkDelivered`, `testMarkException`, `testMarkSkipped`
  4. Run tests → GREEN
  5. Commit: `feat: create RouteStop POPO domain model`

- [ ] **Task 2D.4: Implement — RouteEvent POPO**
  1. `src/Entity/RouteEvent.php` → `src/Domain/Route/Model/RouteEvent.php`
  2. POPO inmutable (append-only, no setters)
  3. Tests: `testEventIsImmutable`, `testEventHasCorrectType`
  4. Run tests → GREEN
  5. Commit: `feat: create RouteEvent POPO domain model`

- [ ] **Task 2D.5: Implement — Doctrine XML mappings**
  1. Crear `config/doctrine/Route.orm.xml` — todas las columnas y relaciones
  2. Crear `config/doctrine/RouteStop.orm.xml`
  3. Crear `config/doctrine/RouteEvent.orm.xml`
  4. Configurar `doctrine.yaml` para leer XML mappings
  5. Run: `php bin/console doctrine:schema:validate` → OK
  6. Commit: `feat: add Doctrine XML mappings for Route domain models`

- [ ] **Task 2D.6: Migrate — Update all imports**
  1. Replace `use App\Entity\Route;` → `use App\Domain\Route\Model\Route;` en todos los archivos
  2. Replace `use App\Entity\RouteStop;` → `use App\Domain\Route\Model\RouteStop;`
  3. Replace `use App\Entity\RouteEvent;` → `use App\Domain\Route\Model\RouteEvent;`
  4. Actualizar repositories, listeners, controllers, DTOs, tests
  5. Run full test suite → GREEN
  6. Eliminar `src/Entity/Route.php`, `src/Entity/RouteStop.php`, `src/Entity/RouteEvent.php`
  7. Commit: `refactor: migrate all code to Route domain models`

- [ ] **Task 2D.7: Implement — Aggregate version field (optimistic locking)**
  1. Añadir `version: int` a Route aggregate
  2. Doctrine XML mapping con `<version>` tag
  3. Migration: `ALTER TABLE route ADD COLUMN version INT DEFAULT 1`
  4. Test: concurrent modification raises OptimisticLockException
  5. Commit: `feat: add optimistic locking to Route aggregate`

- [ ] **Task 2D.8: Verificación de Fase 2 completa**
  1. `php bin/console doctrine:schema:validate` → OK
  2. `php bin/console doctrine:migrations:migrate -n` → OK
  3. `php bin/console app:projection:rebuild` → OK
  4. Full test suite → GREEN
  5. Lint → OK
  6. Verificar: 0 imports de `App\Entity\Route|RouteStop|RouteEvent`
  7. Verificar: Route state SOLO se modifica via `apply()` + event
  8. Verificar: queries críticas usan projections
  9. Commit: `chore: verify Phase 2 completion — Route Planning event sourcing + DDD`

---

## Fase 3: Security — Credential Encryption

**Objetivo:** Encriptar credenciales en CustomerIntegration para producción.
**Independiente de fases 1-2.**

- [ ] **Task 3.0: Leer CustomerIntegration.php**
  1. Entender estructura de `credentials` field (JSON? array?)
  2. Identificar dónde se leen y escriben las credenciales
  3. Commit: (no commit, solo research)

- [ ] **Task 3.1: Test — CredentialEncryptor**
  1. Crear `tests/Infrastructure/Security/CredentialEncryptorTest.php`
  2. Tests: `testEncryptAndDecryptRoundTrip`, `testDifferentKeysCannotDecrypt`, `testEmptyCredentials`, `testNullCredentials`
  3. Run → RED
  4. Commit: `test: add CredentialEncryptor tests`

- [ ] **Task 3.2: Implement — CredentialEncryptor service**
  1. Crear `src/Infrastructure/Security/CredentialEncryptor.php`
  2. Usar `sodium_crypto_secretbox` + nonce random
  3. Key derivation from `APP_SECRET` via `sodium_crypto_generichash`
  4. Interface: `encrypt(array $credentials): string`, `decrypt(string $encrypted): array`
  5. Run tests → GREEN
  6. Commit: `feat: add CredentialEncryptor with sodium encryption`

- [ ] **Task 3.3: Implement — Doctrine type or lifecycle hook**
  1. Opción A: Doctrine custom type `encrypted_json`
  2. Opción B: Doctrine lifecycle listener que encripta/decripta
  3. Elegir la que requiera menos cambios en CustomerIntegration
  4. Tests de integración
  5. Commit: `feat: integrate credential encryption with Doctrine`

- [ ] **Task 3.4: Migration — Encrypt existing data**
  1. Crear migration que encripte credenciales existentes
  2. Test: fixture load → verify encrypted → verify can decrypt
  3. Commit: `feat: add migration to encrypt existing credentials`

- [ ] **Task 3.5: Verificación de Fase 3**
  1. Full test suite → GREEN
  2. `php bin/console doctrine:migrations:migrate -n` → OK
  3. Commit: `chore: verify Phase 3 completion — credential encryption`

---

## Fase 4: SOLID — GpsProvider ISP/LSP Fix

**Objetivo:** Split GpsDeviceProviderInterface en interfaces cohesivas.
**Independiente de fases 1-3.**

- [ ] **Task 4.0: Auditar uso de GpsDeviceProviderInterface**
  1. Leer `src/Tracking/GpsDeviceProviderInterface.php` — listar todos los métodos
  2. Leer `WebhookGpsProvider.php` — confirmar stubs
  3. Leer `TraccarProvider.php` (o como se llame) — confirmar uso completo
  4. `grep -r "GpsDeviceProviderInterface" backend/src/` — listar todos los consumidores
  5. Por cada consumidor: qué métodos llama realmente
  6. Commit: (no commit, solo research)

- [ ] **Task 4.1: Test — New interfaces contract tests**
  1. Crear tests para `GpsPositionProviderInterface` (getPositions, isAvailable)
  2. Crear tests para `GpsDeviceManagerInterface` (login, getSessionCookie, getDevices, createDevice)
  3. Run → RED
  4. Commit: `test: add split GPS provider interface contract tests`

- [ ] **Task 4.2: Implement — Split interfaces**
  1. Crear `src/Tracking/GpsPositionProviderInterface.php` — getPositions(), isAvailable()
  2. Crear `src/Tracking/GpsDeviceManagerInterface.php` — login(), getSessionCookie(), getDevices(), createDevice()
  3. Actualizar TraccarProvider: `implements GpsPositionProviderInterface, GpsDeviceManagerInterface`
  4. Actualizar WebhookGpsProvider: `implements GpsPositionProviderInterface` (solo)
  5. Eliminar stubs de WebhookGpsProvider
  6. Actualizar consumidores para depender de la interface correcta
  7. Actualizar factories y proxies del Provider Framework
  8. Run tests → GREEN
  9. Deprecar o eliminar `GpsDeviceProviderInterface` original
  10. Commit: `refactor: split GpsDeviceProviderInterface into ISP-compliant interfaces`

- [ ] **Task 4.3: Verificación de Fase 4**
  1. Full test suite → GREEN
  2. Lint → OK
  3. Verificar que no queda ningún uso de la interface original
  4. Commit: `chore: verify Phase 4 completion — GPS provider ISP/LSP fix`

---

## Fase 5: Delivery DDD — Shipment/Delivery Entities

**Objetivo:** Segundo contexto crítico en DDD puro.
**Pre-requisito:** Fase 1 (patrón establecido), idealmente Fase 2 (experiencia adquirida)

### Repository Interfaces

- [ ] **Task 5.1: Test + Implement — ShipmentRepositoryInterface**
  1. Analizar uso de ShipmentRepository en servicios
  2. Crear `src/Domain/Shipment/Repository/ShipmentRepositoryInterface.php`
  3. Crear `src/Infrastructure/Shipment/Doctrine/DoctrineShipmentRepository.php`
  4. Contract tests + implementation tests
  5. Commit: `feat: add ShipmentRepositoryInterface and Doctrine implementation`

- [ ] **Task 5.2: Test + Implement — PodRepositoryInterface**
  1. Crear `src/Domain/Shipment/Repository/PodRepositoryInterface.php`
  2. Crear `src/Infrastructure/Shipment/Doctrine/DoctrinePodRepository.php`
  3. Tests
  4. Commit: `feat: add PodRepositoryInterface and Doctrine implementation`

### Migrate Services

- [ ] **Task 5.3: Migrar DeliveryService completamente a interfaces**
  1. Ahora que ShipmentRepositoryInterface existe, completar la migración parcial de Task 1.7
  2. Test unitario completo sin DB
  3. Commit: `refactor: complete DeliveryService migration to domain interfaces`

### Entity Migration

- [ ] **Task 5.4: Test + Implement — Shipment POPO**
  1. Domain unit tests
  2. Crear `src/Domain/Shipment/Model/Shipment.php` como POPO
  3. Crear `config/doctrine/Shipment.orm.xml`
  4. Migrar imports, eliminar `src/Entity/Shipment.php`
  5. Commit: `feat: migrate Shipment to domain POPO with XML mapping`

- [ ] **Task 5.5: Test + Implement — Parcel POPO**
  1. Domain unit tests
  2. Crear `src/Domain/Shipment/Model/Parcel.php`
  3. XML mapping
  4. Migrar imports
  5. Commit: `feat: migrate Parcel to domain POPO with XML mapping`

- [ ] **Task 5.6: Test + Implement — Pod POPO**
  1. Domain unit tests (proof of delivery data integrity)
  2. Crear `src/Domain/Shipment/Model/Pod.php`
  3. XML mapping
  4. Migrar imports
  5. Commit: `feat: migrate Pod to domain POPO with XML mapping`

- [ ] **Task 5.7: Test + Implement — ShipmentEvent POPO**
  1. Domain unit tests (append-only, immutable)
  2. Crear `src/Domain/Shipment/Model/ShipmentEvent.php`
  3. XML mapping
  4. Migrar imports
  5. Commit: `feat: migrate ShipmentEvent to domain POPO with XML mapping`

- [ ] **Task 5.8: Verificación de Fase 5**
  1. `php bin/console doctrine:schema:validate` → OK
  2. Full test suite → GREEN
  3. Verificar que no quedan imports de `App\Entity\Shipment|Parcel|Pod|ShipmentEvent`
  4. Commit: `chore: verify Phase 5 completion — Shipment/Delivery DDD migration`

---

## Fase 6: Infrastructure — Mercure + Provider Cleanup

**Objetivo:** Consistencia en abstracciones de infraestructura.
**Independiente de fases 1-5.**

- [ ] **Task 6.1: Audit — Mercure listener dependencies**
  1. `grep -r "HubInterface" backend/src/` — listar todos los usos directos
  2. `grep -r "RealtimePublisherInterface" backend/src/` — listar usos de abstracción
  3. Comparar: quién usa la abstracción, quién va directo
  4. Documentar hallazgos
  5. Commit: `docs: audit Mercure listener abstraction usage`

- [ ] **Task 6.2: Migrate — Listeners to RealtimePublisherInterface**
  1. Para cada listener que usa HubInterface directamente:
     - Cambiar dependencia a RealtimePublisherInterface
     - Adaptar llamadas si la API difiere
  2. Tests de integración
  3. Commit: `refactor: migrate Mercure listeners to RealtimePublisherInterface`

- [ ] **Task 6.3: Audit — Provider framework boilerplate**
  1. Contar factories, proxies, registries actuales
  2. Evaluar: ¿se acerca al trigger de >6 proxies?
  3. Documentar estado y recomendación
  4. Commit: `docs: audit provider framework boilerplate status`

- [ ] **Task 6.4: Verificación de Fase 6**
  1. Full test suite → GREEN
  2. `grep -r "HubInterface" backend/src/` → solo en MercurePublisher (infrastructure)
  3. Commit: `chore: verify Phase 6 completion — infrastructure cleanup`

---

## Fase 7: Business Features — Decisiones Pendientes

**Objetivo:** Cada decisión requiere su propio brainstorming + spec + plan. Esta fase es un placeholder que define qué se necesita decidir.

- [ ] **Task 7.1: Brainstorm — Selección de estrategia de optimización (Decisión 1)**
  1. Invocar Skill 2 para brainstorming
  2. Evaluar opciones: manual, recomendación, paralelo + comparación, combinación
  3. Escribir spec en `docs/superpowers/specs/`
  4. Escribir plan en `docs/superpowers/plans/`

- [ ] **Task 7.2: Brainstorm — Política de re-optimización (Decisión 2)**
  1. Invocar Skill 2
  2. Evaluar opciones: siempre manual, automática, reglas configurables, IA
  3. Spec + plan

- [ ] **Task 7.3: Brainstorm — Datos históricos para planificación (Decisión 3)**
  1. Invocar Skill 2
  2. Evaluar opciones: analytics only, auto-feedback, recomendaciones, híbrido
  3. Spec + plan

---

## Resumen

| Fase | Sub-fase | Tasks | Estimación | Dependencias |
|------|----------|-------|-----------|--------------|
| 1. Foundation | — | 8 | M | Ninguna |
| 2. Event Sourcing + DDD | 2A: Event Completeness | 7 | S-M | Fase 1 |
| | 2B: Events-First Pattern | 10 | L | 2A |
| | 2C: Projections | 6 | M-L | 2B |
| | 2D: POPO Migration | 8 | M | 2C |
| 3. Security | — | 5 | S | Ninguna |
| 4. SOLID GPS | — | 3 | S | Ninguna |
| 5. Delivery DDD | — | 8 | L | Fase 1 (idealmente 2) |
| 6. Infrastructure | — | 4 | S | Ninguna |
| 7. Business | — | 3 | XL (cada una) | Fases 1-6 |
| **Total** | | **62** | | |

**Ruta crítica:** Fase 1 → Fase 2 (2A→2B→2C→2D) → Fase 5 → Fase 7
**Parallelizable:** Fases 3, 4, 6 son independientes y pueden ejecutarse en cualquier momento.

**Nota sobre Fase 2:** Es la fase más grande y compleja. Las sub-fases son estrictamente secuenciales porque cada una construye sobre la anterior. Sin embargo, cada sub-fase es deployable independientemente (el sistema funciona correctamente después de cada una).
