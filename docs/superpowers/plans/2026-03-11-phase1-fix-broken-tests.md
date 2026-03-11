# Plan Fase 1: Arreglar Tests Rotos

**Goal:** Llevar los 92 tests existentes a verde (0 errores, 0 fallos)
**Arquitectura:** Symfony 7.4 LTS, PHPUnit 11, Doctrine ORM 3.x
**Estado actual:** 25 errores + 7 fallos = 32 tests rotos de 92

---

## Tareas

### Task 1: Fix ShipmentCsvImporterTest (7 tests)

**Problema:** `ImportRunTracker` es `final`, PHPUnit no puede crear mock. Además falta `CsvQualityAnalyzer` en constructor.

**Archivo:** `backend/tests/Unit/ShipmentCsvImporterTest.php`

- [ ] 1.1 Leer `src/Service/ImportRunTracker.php` — verificar que es `final`
- [ ] 1.2 Crear interfaz `ImportRunTrackerInterface` con los métodos públicos de `ImportRunTracker`
- [ ] 1.3 Hacer que `ImportRunTracker` implemente la nueva interfaz
- [ ] 1.4 Actualizar `ShipmentCsvImporter` para depender de la interfaz en vez de la clase concreta
- [ ] 1.5 Actualizar services.yaml si necesario (alias de interfaz)
- [ ] 1.6 Añadir mock de `CsvQualityAnalyzer` al setUp del test
- [ ] 1.7 Actualizar las expectativas del mock de `track()` para incluir el 4º parámetro (quality score)
- [ ] 1.8 Ejecutar `php vendor/bin/phpunit tests/Unit/ShipmentCsvImporterTest.php` — verificar 7/7 verdes
- [ ] 1.9 Commit: "fix: update ShipmentCsvImporterTest for new constructor signature"

### Task 2: Fix TraccarIngestionServiceTest (6 tests)

**Problema:** Constructor requiere 3 args, test pasa 2. Falta `EventDispatcherInterface`.

**Archivo:** `backend/tests/Unit/TraccarIngestionServiceTest.php`

- [ ] 2.1 Leer constructor actual de `TraccarIngestionService` — confirmar 3er parámetro
- [ ] 2.2 Añadir `$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class)` en setUp
- [ ] 2.3 Pasar `$this->eventDispatcher` como 3er argumento al constructor
- [ ] 2.4 Ejecutar `php vendor/bin/phpunit tests/Unit/TraccarIngestionServiceTest.php` — verificar 6/6 verdes
- [ ] 2.5 Commit: "fix: add EventDispatcher mock to TraccarIngestionServiceTest"

### Task 3: Fix TopicResolverTest (5 fallos)

**Problema:** Se añadió topic `/users/{id}/notifications` pero los tests no se actualizaron.

**Archivo:** `backend/tests/Unit/TopicResolverTest.php`

- [ ] 3.1 Leer `src/Security/TopicResolver.php` — listar todos los topics por rol
- [ ] 3.2 Actualizar `assertCount()` en `customerGetsVehicleAndCustomerTopics` (3→4)
- [ ] 3.3 Actualizar `assertCount()` en `customerWithNoVehiclesGetsOnlyCustomerTopics` (2→3)
- [ ] 3.4 Actualizar `assertCount()` en `driverGetsVehicleTopics` (2→3)
- [ ] 3.5 Actualizar `assertCount()` en `driverWithNoVehiclesGetsEmptyTopics` (0→1, o verificar)
- [ ] 3.6 Actualizar `assertCount()` en `driverWithDuplicateVehicleIdsDeduplicates` (1→2)
- [ ] 3.7 Añadir assertion para verificar que el topic `/users/{id}/notifications` está presente
- [ ] 3.8 Ejecutar `php vendor/bin/phpunit tests/Unit/TopicResolverTest.php` — verificar 9/9 verdes
- [ ] 3.9 Commit: "fix: update TopicResolverTest for notification topic"

### Task 4: Fix MercureJwtFactoryTest (2 fallos)

**Problema:** Misma causa — topic counts desalineados por `/users/{id}/notifications`.

**Archivo:** `backend/tests/Unit/MercureJwtFactoryTest.php`

- [ ] 4.1 Leer test — identificar assertions de conteo
- [ ] 4.2 Actualizar `driverUserGetsVehicleTopicsOnly` assertion count
- [ ] 4.3 Actualizar `driverWithNoVehiclesGetsEmptyTopics` assertion count
- [ ] 4.4 Ejecutar `php vendor/bin/phpunit tests/Unit/MercureJwtFactoryTest.php` — verificar 8/8 verdes
- [ ] 4.5 Commit: "fix: update MercureJwtFactoryTest for notification topic"

### Task 5: Fix DriverApiTest (11 errores)

**Problema:** El controller refactorizó a usar `DeliveryService` y `RouteLifecycleService` en vez de servicios individuales. Los tests llaman con signatures obsoletas.

**Archivo:** `backend/tests/Functional/DriverApiTest.php`

- [ ] 5.1 Leer `src/Controller/DriverApiController.php` — documentar signatures actuales de `deliver()`, `exception()`, `start()`, `finish()`
- [ ] 5.2 Crear mock de `DeliveryService` con los métodos necesarios
- [ ] 5.3 Crear mock de `RouteLifecycleService` con los métodos necesarios
- [ ] 5.4 Reescribir tests de `deliver` para usar `DeliveryService` mock
- [ ] 5.5 Reescribir tests de `exception` para usar `DeliveryService` mock
- [ ] 5.6 Reescribir tests de `start`/`finish` para usar `RouteLifecycleService` mock
- [ ] 5.7 Actualizar test de 404 y validación JSON
- [ ] 5.8 Ejecutar `php vendor/bin/phpunit tests/Functional/DriverApiTest.php` — verificar 17/17 verdes
- [ ] 5.9 Commit: "fix: rewrite DriverApiTest for refactored controller signatures"

### Task 6: Fix CustomerTenantFilterTest (1 error)

**Archivo:** `backend/tests/Functional/CustomerTenantFilterTest.php`

- [ ] 6.1 Leer test y error exacto
- [ ] 6.2 Verificar imports de Doctrine son compatibles con ORM 3.x
- [ ] 6.3 Aplicar fix
- [ ] 6.4 Ejecutar `php vendor/bin/phpunit tests/Functional/CustomerTenantFilterTest.php` — verificar verde
- [ ] 6.5 Commit: "fix: update CustomerTenantFilterTest for Doctrine ORM 3.x"

### Task 7: Verificación Final

- [ ] 7.1 Ejecutar `php vendor/bin/phpunit` completo
- [ ] 7.2 Verificar: 92 tests, 0 errors, 0 failures
- [ ] 7.3 Ejecutar `make lint` — verificar 0 errores
- [ ] 7.4 Commit final si hay ajustes pendientes
