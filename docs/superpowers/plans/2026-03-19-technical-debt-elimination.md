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

## Fase 2: Core DDD — Route Planning Entities → Domain POPOs

**Objetivo:** Migrar Route, RouteStop, RouteEvent a POPOs puros sin ORM attributes. Mapping externo via Doctrine XML.

**Pre-requisito:** Fase 1 completada (servicios ya dependen de interfaces)

**Archivos a crear:**
- `src/Domain/Route/Model/Route.php` (POPO)
- `src/Domain/Route/Model/RouteStop.php` (POPO)
- `src/Domain/Route/Model/RouteEvent.php` (POPO)
- `config/doctrine/Route.orm.xml`
- `config/doctrine/RouteStop.orm.xml`
- `config/doctrine/RouteEvent.orm.xml`

**Archivos a eliminar (post-migración):**
- `src/Entity/Route.php` → reemplazado por `src/Domain/Route/Model/Route.php`
- `src/Entity/RouteStop.php` → reemplazado por `src/Domain/Route/Model/RouteStop.php`
- `src/Entity/RouteEvent.php` → reemplazado por `src/Domain/Route/Model/RouteEvent.php`

### Preparación

- [ ] **Task 2.0: Mapear todas las referencias a entidades Route**
  1. `grep -r "use App\\Entity\\Route;" backend/src/ backend/tests/` — listar TODOS los imports
  2. `grep -r "use App\\Entity\\RouteStop;" backend/src/ backend/tests/`
  3. `grep -r "use App\\Entity\\RouteEvent;" backend/src/ backend/tests/`
  4. Documentar count de archivos afectados por entidad
  5. Verificar relaciones ManyToOne/OneToMany con otras entidades (RouteSnapshot, Shipment, Vehicle, User)
  6. Commit: `docs: map all Route entity references for DDD migration`

### Route Entity Migration

- [ ] **Task 2.1: Test — Route POPO unit tests**
  1. Crear `tests/Domain/Route/Model/RouteTest.php`
  2. Tests de lógica de dominio pura (sin DB):
     - `testRouteStartsInPlannedStatus`
     - `testStartTransitionsToActive`
     - `testCannotStartNonPlannedRoute`
     - `testCompleteTransitionsToDone`
  3. Run → RED (clase no existe en nueva ubicación)
  4. Commit: `test: add Route POPO domain model tests`

- [ ] **Task 2.2: Implement — Route POPO**
  1. Copiar `src/Entity/Route.php` → `src/Domain/Route/Model/Route.php`
  2. Cambiar namespace a `App\Domain\Route\Model`
  3. Eliminar TODOS los `#[ORM\...]` attributes
  4. Eliminar `#[ApiResource]` si existe
  5. Mantener lógica de dominio (state transitions, business methods)
  6. Mantener propiedades y relaciones como tipado PHP puro
  7. Run unit tests → GREEN
  8. Commit: `feat: create Route POPO domain model`

- [ ] **Task 2.3: Implement — Route Doctrine XML mapping**
  1. Crear `config/doctrine/Route.orm.xml`
  2. Mapear todas las columnas, relaciones (OneToMany RouteStop, OneToMany RouteEvent, ManyToOne Vehicle, ManyToOne User, OneToOne RouteSnapshot)
  3. Configurar `doctrine.yaml` para leer XML mappings desde `config/doctrine/`
  4. Run: `php bin/console doctrine:schema:validate` → verify mapping matches DB
  5. Commit: `feat: add Doctrine XML mapping for Route`

- [ ] **Task 2.4: Migrate — Update all Route imports**
  1. Replace `use App\Entity\Route;` → `use App\Domain\Route\Model\Route;` en todos los archivos
  2. Actualizar DoctrineRouteRepository para usar nuevo namespace
  3. Actualizar factories, listeners, controllers
  4. Run full test suite → GREEN
  5. Eliminar `src/Entity/Route.php`
  6. Commit: `refactor: migrate all code to Route domain model`

### RouteStop Entity Migration

- [ ] **Task 2.5: Test + Implement — RouteStop POPO**
  1. Tests de dominio: `testStopStartsInPendingStatus`, `testMarkDelivered`, `testMarkException`
  2. Crear `src/Domain/Route/Model/RouteStop.php` como POPO
  3. Crear `config/doctrine/RouteStop.orm.xml`
  4. Migrar imports
  5. Run tests → GREEN
  6. Eliminar `src/Entity/RouteStop.php`
  7. Commit: `feat: migrate RouteStop to domain POPO with XML mapping`

### RouteEvent Entity Migration

- [ ] **Task 2.6: Test + Implement — RouteEvent POPO**
  1. Tests de dominio: `testEventIsImmutable`, `testEventHasCorrectType`, `testCannotModifyAfterCreation`
  2. Crear `src/Domain/Route/Model/RouteEvent.php` como POPO (append-only, no setters)
  3. Crear `config/doctrine/RouteEvent.orm.xml`
  4. Migrar imports
  5. Run tests → GREEN
  6. Eliminar `src/Entity/RouteEvent.php`
  7. Commit: `feat: migrate RouteEvent to domain POPO with XML mapping`

- [ ] **Task 2.7: Verificación de Fase 2**
  1. `php bin/console doctrine:schema:validate` → OK
  2. `php bin/console doctrine:migrations:migrate -n` → OK (no debería necesitar migración si mappings son correctos)
  3. Full test suite → GREEN
  4. Lint → OK
  5. Verificar que no queda ningún `use App\Entity\Route` en el código
  6. Commit: `chore: verify Phase 2 completion — Route Planning DDD migration`

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

| Fase | Tasks | Estimación | Dependencias |
|------|-------|-----------|--------------|
| 1. Foundation | 8 | M | Ninguna |
| 2. Route DDD | 7 | L | Fase 1 |
| 3. Security | 5 | S | Ninguna |
| 4. SOLID GPS | 3 | S | Ninguna |
| 5. Delivery DDD | 8 | L | Fase 1 (idealmente 2) |
| 6. Infrastructure | 4 | S | Ninguna |
| 7. Business | 3 | XL (cada una) | Fases 1-6 |
| **Total** | **38** | | |

**Ruta crítica:** Fase 1 → Fase 2 → Fase 5 → Fase 7
**Parallelizable:** Fases 3, 4, 6 son independientes y pueden ejecutarse en cualquier momento.
