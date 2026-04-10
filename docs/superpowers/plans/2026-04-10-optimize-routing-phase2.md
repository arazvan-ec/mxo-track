# Plan: Optimización Integral del Routing — Phase 2 (Inteligencia)

**Fecha:** 2026-04-10
**Spec:** `docs/superpowers/specs/2026-04-10-optimize-routing-design.md`
**Branch:** `claude/optimize-routing-NE2kv`
**Scope:** Phase 2 (2-A, 2-B, 2-C)

---

## Phase 2 (v0): Inteligencia

Objetivo: Address risk → optimización, feedback loops automáticos, optimizer selection API.

### [parallel] Wave 1: Address Risk + Coordinate Override Infrastructure (2-A + 2-B parcial)

#### Tarea 1a: `AddressRiskService.updateFromRouteStops()`

**Archivo:** `backend/src/Service/AddressRiskService.php`
**Cambio:** Nuevo método que recibe un array de RouteStops completados y actualiza/crea AddressRisk entries:
- Cuenta delivered vs exception por dirección
- Calcula nueva `exceptionRate`
- Persiste via `AddressRiskRepository`

**TDD:**
1. Test: 3 stops delivered en dirección X → AddressRisk con rate=0, totalDeliveries=3
2. Test: 2 delivered + 1 exception en dirección Y → AddressRisk con rate=0.33
3. Test: actualización incremental — ya existía con 5 deliveries, agrega 2 más
→ Implementar

**Test file:** `backend/tests/Unit/Service/AddressRiskServiceUpdateTest.php`

#### Tarea 1b: `CoordinateCorrectionService`

**Archivo nuevo:** `backend/src/Service/CoordinateCorrectionService.php`
**Cambio:** Servicio que consulta DriverFeedback para una dirección y determina si hay corrección consistente:
- Query DriverFeedback por address (via stop → address join)
- Si ≥3 feedbacks con correctedLat/Lng y desviación <50m entre sí → retorna coordenadas corregidas
- Si no → retorna null

**Constructor:** `DriverFeedbackRepository`

**TDD:**
1. Test: 3 feedbacks con coords similares (desviación <50m) → retorna avg coords
2. Test: 2 feedbacks → retorna null (insuficiente)
3. Test: 3 feedbacks pero desviación >50m → retorna null (inconsistente)
4. Test: feedbacks sin correctedLat → retorna null
→ Implementar

**Test file:** `backend/tests/Unit/Service/CoordinateCorrectionServiceTest.php`

#### Tarea 1c: `RoutePerformanceMetricRepository.getMetricsByOptimizer()`

**Archivo:** `backend/src/Repository/RoutePerformanceMetricRepository.php`
**Verificar:** El método `getMetricsByOptimizer()` ya existe (línea ~140). Si retorna stats agrupadas por optimizerUsed (avg distance, avg duration, count, avg success rate), no necesita cambios. Si falta algo, extender.

**TDD:**
1. Test: verificar retorna array agrupado por optimizer con avg metrics
→ Verificar o implementar

**Test file:** `backend/tests/Unit/Repository/RoutePerformanceMetricRepositoryTest.php`

---

### Wave 2: RouteBuilder Address Intelligence (2-A core)

Depende de Wave 1 (AddressRiskService, CoordinateCorrectionService).

#### Tarea 2a: RouteBuilder usa AddressRisk para buffer de service time

**Archivo:** `backend/src/Service/RouteBuilder.php`
**Cambio:** En `mapShipmentsToOptimizable()`, después de extraer address (línea ~221):
1. Nuevo constructor dependency: `AddressRiskService`
2. Si `AddressRiskService.checkAddress()` retorna `is_risky = true` → service time += 120s (buffer)
3. Log step via OptimizationLogger

**TDD:**
1. Test: shipment en dirección high-risk → job service time tiene +120s buffer
2. Test: shipment en dirección normal → sin buffer
→ Implementar

**Test file:** `backend/tests/Unit/Service/RouteBuilderAddressRiskTest.php`

#### Tarea 2b: RouteBuilder usa coordenadas corregidas de DriverFeedback

**Archivo:** `backend/src/Service/RouteBuilder.php`
**Cambio:** En `mapShipmentsToOptimizable()`:
1. Nuevo constructor dependency: `CoordinateCorrectionService`
2. Para cada shipment, consultar si hay corrección de coordenadas para su address
3. Si hay → usar coordenadas corregidas en OptimizableJob en lugar de las del shipment
4. Log step via OptimizationLogger

**TDD:**
1. Test: dirección con corrección consistente → OptimizableJob usa coords corregidas
2. Test: dirección sin corrección → OptimizableJob usa coords del shipment
→ Implementar

**Test file:** `backend/tests/Unit/Service/RouteBuilderCoordinateCorrectionTest.php`

---

### Wave 3: Feedback Loop Automático (2-B)

Depende de Wave 1 (AddressRiskService.updateFromRouteStops).

#### Tarea 3a: `PostRouteUpdateSubscriber`

**Archivo nuevo:** `backend/src/EventSubscriber/PostRouteUpdateSubscriber.php`
**Cambio:** Escucha `RouteCompleted` event → carga la Route con stops → llama `AddressRiskService.updateFromRouteStops()` con los stops completados.

**TDD:**
1. Test: RouteCompleted con 5 stops (3 delivered, 2 exception) → AddressRiskService.updateFromRouteStops() invocado con los 5 stops
2. Test: RouteCompleted con route no encontrada → no explota, log warning
→ Implementar

**Test file:** `backend/tests/Unit/EventSubscriber/PostRouteUpdateSubscriberTest.php`

#### Tarea 3b: Wire `OptimizationStrategyComparison.recordOutcome()`

**Archivo:** `backend/src/EventSubscriber/PostRouteUpdateSubscriber.php` (mismo subscriber)
**Cambio:** Además de actualizar AddressRisk, buscar si hay `OptimizationStrategyComparison` vinculada a la ruta completada → llamar `recordOutcome()` con métricas reales:
```php
['actual_distance_km' => ..., 'actual_duration_min' => ..., 'delivery_rate' => ..., 'exceptions' => ...]
```

**TDD:**
1. Test: ruta con OptimizationStrategyComparison vinculada → recordOutcome() llamado
2. Test: ruta sin comparación → no error, skip
→ Implementar

**Test file:** `backend/tests/Unit/EventSubscriber/PostRouteUpdateSubscriberTest.php` (mismo archivo)

---

### Wave 4: Optimizer Selection API (2-C)

Independiente de Waves 2-3 pero se ejecuta después por secuencia de plan.

#### Tarea 4a: `OptimizerRegistryController`

**Archivo nuevo:** `backend/src/Controller/Api/OptimizerRegistryController.php`
**Cambio:** Endpoint `GET /api/admin/route-planner/optimizers` que:
1. Lista optimizadores disponibles via `ProviderFactoryRegistry.getAvailableProviders()` filtrado por tipo 'optimizer'
2. Para cada uno, consulta `RoutePerformanceMetricRepository.getMetricsByOptimizer()` para stats históricas
3. Retorna JSON: `[{name, type, stats: {avg_distance_km, avg_duration_min, route_count, avg_success_rate}}]`

**TDD:**
1. Test: endpoint retorna lista de optimizadores con stats
2. Test: sin métricas históricas → stats son null
→ Implementar

**Test file:** `backend/tests/Unit/Controller/Api/OptimizerRegistryControllerTest.php`

---

### Wave 5: Update existing tests + Verification

#### Tarea 5a: Fix constructor signatures en tests existentes

Los tests de RouteBuilder que instancian manualmente necesitan los nuevos mocks (AddressRiskService, CoordinateCorrectionService).

**Archivos:**
- `backend/tests/Unit/Service/RouteBuilderServiceTimeOverrideTest.php`
- `backend/tests/Unit/Service/RouteBuilderAutoCalibrationTest.php`
- `backend/tests/Unit/Service/RouteBuilderShiftTest.php`

#### Tarea 5b: Run full test suite + lint

```bash
cd backend && php vendor/bin/phpunit
make lint
```

#### Tarea 5c: Commit + push

---

## Resumen de archivos afectados

| Archivo | Wave | Tipo |
|---------|------|------|
| `src/Service/AddressRiskService.php` | 1 | Extend (+método update) |
| `src/Service/CoordinateCorrectionService.php` | 1 | **Nuevo** |
| `src/Repository/RoutePerformanceMetricRepository.php` | 1 | Verificar/extend |
| `src/Service/RouteBuilder.php` | 2 | Extend (+2 deps, address risk buffer, coord override) |
| `src/EventSubscriber/PostRouteUpdateSubscriber.php` | 3 | **Nuevo** |
| `src/Controller/Api/OptimizerRegistryController.php` | 4 | **Nuevo** |
| Tests (6-7 nuevos archivos) | 1-5 | New |

## Dependencias entre waves

```
Wave 1 (services + repos) — 3 tareas paralelas
    ↓
Wave 2 (RouteBuilder address intel) — needs AddressRisk + CoordCorrection
    ↓ (parallel con Wave 3)
Wave 3 (feedback loop subscriber) — needs AddressRisk update
Wave 4 (optimizer API) — independent, after Wave 1
    ↓
Wave 5 (verification)
```
