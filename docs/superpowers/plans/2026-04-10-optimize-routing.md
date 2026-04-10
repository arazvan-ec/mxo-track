# Plan: Optimización Integral del Routing — Phase 1 (Fundación)

**Fecha:** 2026-04-10
**Spec:** `docs/superpowers/specs/2026-04-10-optimize-routing-design.md`
**Branch:** `claude/optimize-routing-NE2kv`
**Scope:** Phase 1 solo (1-A, 1-B, 1-C)

---

## Phase 1 (v0): Fundación

Objetivo: Fix re-optimización con constraints completos + calibración automática de service times + vehicle shifts en VROOM.

### [parallel] Wave 1: Value Objects + Service Time Calibration (1-A parcial + 1-B + 1-C parcial)

Estas tareas son independientes: una extiende value objects, otra extiende calibración, otra agrega tests.

#### Tarea 1a: Extender `OptimizableVehicle` con shift time windows

**Archivo:** `backend/src/RouteOptimization/OptimizableVehicle.php`
**Cambio:** Agregar campos `?int $shiftStartSeconds`, `?int $shiftEndSeconds` al constructor.

**TDD:**
1. Test: crear `OptimizableVehicle` con shift times → verificar campos accesibles
2. Test: shift times default a null
→ Implementar

**Test file:** `backend/tests/Unit/RouteOptimization/OptimizableVehicleTest.php`

#### Tarea 1b: Extender `ServiceTimeCalibrationService` con DriverFeedback

**Archivo:** `backend/src/Service/ServiceTimeCalibrationService.php`
**Cambio:** Nuevo método `getCalibratedServiceTimesWithFeedback(int $customerId, int $limit, int $minSamples): array` que:
1. Consulta `DriverFeedback.actualServiceTimeSeconds` agrupado por dirección (avg, si ≥minSamples)
2. Consulta el SQL window function existente
3. Merge: DriverFeedback tiene prioridad sobre SQL (más preciso, dato directo del driver)
4. Retorna mismo formato que `getCalibratedServiceTimes()`

**TDD:**
1. Test: sin feedback ni histórico → retorna array vacío
2. Test: con feedback para dirección X → retorna avg de feedback
3. Test: con feedback Y SQL para misma dirección → feedback gana
4. Test: con solo SQL para dirección Z → retorna avg SQL
→ Implementar

**Test file:** `backend/tests/Unit/Service/ServiceTimeCalibrationServiceTest.php`

#### Tarea 1c: Test para `VroomRouteOptimizer` vehicle shift time windows

**Archivo:** `backend/tests/Unit/RouteOptimization/VroomRouteOptimizerShiftTest.php`
**Cambio:** Test que verifica que cuando un `OptimizableVehicle` tiene `shiftStartSeconds`/`shiftEndSeconds`, el JSON enviado a VROOM incluye `time_window` en el vehículo.

**TDD:**
1. Test: vehicle con shiftStart=28800 (8am), shiftEnd=64800 (6pm) → VROOM JSON tiene `"time_window": [28800, 64800]`
2. Test: vehicle sin shift → VROOM JSON no tiene `time_window`
→ Implementar en `VroomRouteOptimizer.mapVehicles()`

---

### Wave 2: Fix RouteOptimizationService re-opt constraints (1-A core)

Depende de Wave 1 (usa los value objects extendidos).

#### Tarea 2a: Helper `buildJobFromStop(RouteStop): OptimizableJob`

**Archivo:** `backend/src/Service/RouteOptimizationService.php`
**Cambio:** Nuevo método privado que extrae constraints del `Shipment` asociado al `RouteStop`:
- `serviceTimeSeconds` del shipment (o default 300)
- `timeWindows` del shipment (preferredWindowStart/End)
- `requiredSkills` del shipment
- `priority` del shipment
- `weightKg`, `volumeM3`, `parcels` del shipment

**TDD:**
1. Test: RouteStop con Shipment que tiene TW 9:00-12:00 → job tiene timeWindows [[32400, 43200]]
2. Test: RouteStop con Shipment que tiene skills [refrigerated] → job tiene requiredSkills ["refrigerated"]
3. Test: RouteStop con Shipment que tiene priority HIGH → job tiene priority > 0
4. Test: RouteStop con Shipment sin TW → job tiene timeWindows []
5. Test: RouteStop sin Shipment → job con defaults (300s, no TW, no skills)
→ Implementar

**Test file:** `backend/tests/Unit/Service/RouteOptimizationServiceConstraintTest.php`

#### Tarea 2b: Helper `buildVehicleFromRoute(Route): OptimizableVehicle`

**Archivo:** `backend/src/Service/RouteOptimizationService.php`
**Cambio:** Nuevo método privado que extrae constraints del `Vehicle` asignado a la `Route`:
- `maxWeightKg`, `maxVolumeM3`, `maxParcels` del vehicle
- `skills` del vehicle
- `shiftStartSeconds`, `shiftEndSeconds` del DriverAvailability (si existe para el driver asignado y el día actual)

**TDD:**
1. Test: Route con Vehicle de 1000kg, skills [heavy_load] → vehicle con maxWeightKg=1000, skills=["heavy_load"]
2. Test: Route sin Vehicle → vehicle con defaults (null capacities, no skills)
→ Implementar

**Test file:** `backend/tests/Unit/Service/RouteOptimizationServiceConstraintTest.php` (mismo archivo)

#### Tarea 2c: Refactor `optimizeStopOrder()` para usar helpers

**Archivo:** `backend/src/Service/RouteOptimizationService.php`
**Cambio:** Reemplazar las líneas 81-104 (donde crea OptimizableVehicle sin constraints y OptimizableJob con 300s fijo) por llamadas a `buildVehicleFromRoute()` y `buildJobFromStop()`.

**TDD:**
1. Test: `optimizeStopOrder()` con route que tiene shipments con TW → optimizer recibe jobs con TW
2. Test: `optimizeStopOrder()` con vehicle con capacity → optimizer recibe vehicle con capacity
→ Implementar (refactor de código existente)

**Test file:** `backend/tests/Unit/Service/RouteOptimizationServiceConstraintTest.php`

#### Tarea 2d: Refactor `reoptimizePendingStops()` para usar helpers

**Archivo:** `backend/src/Service/RouteOptimizationService.php`
**Cambio:** Reemplazar las líneas 231-254 (misma creación sin constraints) por llamadas a los helpers. El vehicle usa posición actual del driver como start.

**TDD:**
1. Test: `reoptimizePendingStops()` con pending stops que tienen TW → optimizer recibe jobs con TW
2. Test: `reoptimizePendingStops()` con pending stops que tienen service time 600s → optimizer recibe 600s (no 300s)
→ Implementar

**Test file:** `backend/tests/Unit/Service/RouteOptimizationServiceConstraintTest.php`

---

### Wave 3: Auto-calibración en RouteBuilder + VroomRouteOptimizer shifts (1-B auto + 1-C impl)

Depende de Wave 1 (calibration service extendido) y Wave 2 (helpers implementados).

#### Tarea 3a: `RouteBuilder.buildRoutes()` auto-calibra service times

**Archivo:** `backend/src/Service/RouteBuilder.php`
**Cambio:** Si `$serviceTimeOverrides` es null, llamar `ServiceTimeCalibrationService.getCalibratedServiceTimesWithFeedback()` automáticamente. Nuevo constructor dependency: `ServiceTimeCalibrationService`.

**TDD:**
1. Test: `buildRoutes()` sin serviceTimeOverrides → calibration service se invoca con customer ID
2. Test: `buildRoutes()` con serviceTimeOverrides explícito → calibration service NO se invoca (override manual gana)
3. Test: calibration retorna {address → 450s} → optimizer recibe job con 450s para esa dirección
→ Implementar

**Test file:** `backend/tests/Unit/Service/RouteBuilderAutoCalibrationTest.php`

#### Tarea 3b: `VroomRouteOptimizer` mapea vehicle shift time windows

**Archivo:** `backend/src/RouteOptimization/VroomRouteOptimizer.php`
**Cambio:** En `mapVehicles()`, si `$vehicle->shiftStartSeconds` y `$vehicle->shiftEndSeconds` no son null, agregar `'time_window'` al array del vehículo VROOM.

**TDD:** (ya escritos en Tarea 1c)
→ Implementar la lógica en `mapVehicles()`

#### Tarea 3c: `RouteBuilder.mapVehiclesToOptimizable()` pasa shifts de DriverAvailability

**Archivo:** `backend/src/Service/RouteBuilder.php`
**Cambio:** `buildRoutes()` acepta nuevo parámetro `?array $driverAvailabilities = null` (map de vehicle index → DriverAvailability). En `mapVehiclesToOptimizable()`, si hay availability para el vehículo, convertir startTime/endTime a seconds y pasar como `shiftStartSeconds`/`shiftEndSeconds`.

**TDD:**
1. Test: vehicle con DriverAvailability 08:00-18:00 → OptimizableVehicle tiene shiftStartSeconds=28800, shiftEndSeconds=64800
2. Test: vehicle sin DriverAvailability → shiftStartSeconds=null
→ Implementar

**Test file:** `backend/tests/Unit/Service/RouteBuilderShiftTest.php`

---

### Wave 4: Wiring en RoutePlanningService + Injection updates (integración)

Depende de Wave 3.

#### Tarea 4a: `RoutePlanningService.buildRoutes()` carga DriverAvailability y pasa a RouteBuilder

**Archivo:** `backend/src/Application/Route/RoutePlanningService.php`
**Cambio:**
1. Nuevo constructor dependency: `DriverAvailabilityService`
2. En `buildRoutes()`, tras resolver vehicles, cargar DriverAvailability para cada vehicle's assigned driver (si existe) para el día de la ruta
3. Pasar al RouteBuilder como `$driverAvailabilities`

**TDD:**
1. Test: buildRoutes con vehicles que tienen drivers con availability → RouteBuilder recibe availabilities
2. Test: buildRoutes sin availability data → RouteBuilder recibe null (sin restricción)
→ Implementar

**Test file:** `backend/tests/Unit/Application/Route/RoutePlanningServiceShiftTest.php`

#### Tarea 4b: DI wiring — services.yaml + constructor updates

**Archivos:**
- `backend/config/services.yaml` (si necesita config explícita)
- `backend/src/Service/RouteBuilder.php` (add ServiceTimeCalibrationService to constructor)
- `backend/src/Service/RouteOptimizationService.php` (verify existing DI works)

**Verificar:**
- Autowiring resuelve `ServiceTimeCalibrationService` en `RouteBuilder`
- Autowiring resuelve `DriverAvailabilityService` en `RoutePlanningService`
- No hay conflictos de DI

**TDD:** Functional — run existing tests to verify DI doesn't break.

#### Tarea 4c: Update existing tests — fix constructor signature changes

**Archivos:** Todos los tests que instancian `RouteBuilder` manualmente:
- `backend/tests/Unit/Service/RouteBuilderServiceTimeOverrideTest.php`
- Cualquier otro que use `new RouteBuilder(...)`

**Cambio:** Agregar mock de `ServiceTimeCalibrationService` al constructor.

---

### Wave 5: Verificación global

#### Tarea 5a: Run full test suite

```bash
cd backend && php vendor/bin/phpunit
```

Verificar 0 fallos nuevos.

#### Tarea 5b: PHP lint

```bash
cd backend && make lint
```

#### Tarea 5c: Commit + push

Commit por wave completada (ya hecho incrementalmente), push final.

---

## Resumen de archivos afectados

| Archivo | Wave | Tipo de cambio |
|---------|------|---------------|
| `src/RouteOptimization/OptimizableVehicle.php` | 1 | Extend (2 campos) |
| `src/Service/ServiceTimeCalibrationService.php` | 1 | Extend (nuevo método) |
| `src/RouteOptimization/VroomRouteOptimizer.php` | 1+3 | Extend (shift TW mapping) |
| `src/Service/RouteOptimizationService.php` | 2 | Refactor (2 helpers + 2 method rewrites) |
| `src/Service/RouteBuilder.php` | 3 | Extend (auto-calibration + shifts) |
| `src/Application/Route/RoutePlanningService.php` | 4 | Extend (load availability) |
| Tests (6-7 nuevos archivos) | 1-4 | New |

## Dependencias entre waves

```
Wave 1 (value objects + calibration + test stubs) — independent tasks
    ↓
Wave 2 (fix RouteOptimizationService) — needs extended VOs
    ↓
Wave 3 (RouteBuilder auto-cal + VROOM shifts) — needs calibration + VOs
    ↓
Wave 4 (RoutePlanningService wiring) — needs RouteBuilder changes
    ↓
Wave 5 (verification) — needs all code complete
```

## Phase 2 (Mature): Placeholder

Phase 2 (Inteligencia) se planificará en sesión separada tras completar Phase 1.
Incluirá: address risk → optimización, feedback loop automático, optimizer selection API.
