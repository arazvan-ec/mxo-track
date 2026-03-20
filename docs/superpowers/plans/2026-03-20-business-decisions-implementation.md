# Plan: Implementación de las 3 Decisiones de Negocio (Phase 1 MVP)

**Fecha:** 2026-03-20
**Branch:** A definir al implementar
**Specs:** `docs/superpowers/specs/2026-03-20-{optimization-strategy-selection,reoptimization-policy,historical-data-learning}-design.md`
**Plans detallados:** `docs/superpowers/plans/2026-03-20-{optimization-strategy-selection,reoptimization-policy,historical-data-learning}.md`

---

## Resumen Ejecutivo

3 features independientes que enriquecen el Route Planner y el runtime de rutas:

| # | Feature | Complexity | Tasks | Archivos |
|---|---------|-----------|-------|----------|
| 1 | **Optimizer Selector** — admin elige optimizador en Step 2 | S | 5 | ~7 |
| 2 | **Re-opt Triggers** — auto-reoptimizar tras skip/delay | S-M | 4 | ~8 |
| 3 | **Service Time Calibration** — tiempos históricos mejoran estimaciones | M | 5 | ~8 |

**Total:** 14 tasks, ~23 archivos, 0 dependencias entre features.

---

## Orden de Ejecución

Las 3 features son **independientes** — pueden implementarse en paralelo o en cualquier orden.

**Orden recomendado (secuencial):** 1 → 2 → 3

Razón: de menor a mayor complejidad. Feature 1 es backend+frontend puro. Feature 2 es solo backend (event subscribers). Feature 3 tiene un riesgo de datos que hay que validar primero.

**Alternativa (paralelo):** Lanzar Features 1+2 como subagentes paralelos, luego Feature 3 en main thread (requiere investigar RouteComparison).

---

## Feature 1: Optimizer Selector (S)

**Goal:** Admin elige optimizador (VROOM, Greedy, Automático) en Route Planner Step 2.

### Tasks

| # | Descripción | TDD | Archivos |
|---|------------|-----|----------|
| 1.1 | `ProviderFactoryRegistry::getAvailableProviders()` | Test → Impl | `ProviderFactoryRegistry.php`, test |
| 1.2 | `GET /admin/route-planner/optimizers` endpoint | Test → Impl | `RoutePlannerController.php`, test |
| 1.3 | Aceptar `optimizer_name` en preview | Test → Impl | `BuildRoutesInput.php`, `RoutePlanningService.php`, `RoutePlannerController.php`, tests |
| 1.4 | Frontend: selector dropdown en Step 2 | Impl | `RoutePlannerPage.tsx` |
| 1.5 | Verificación: test suite + lint | — | — |

**Clave:** `ProviderFactoryRegistry` ya tiene factories registradas con `#[AutoconfigureTag]`. Solo necesita un método que las liste por `ServiceType`.

---

## Feature 2: Re-optimization Triggers (S-M)

**Goal:** Auto-reoptimizar paradas PENDING tras skip o retraso excesivo.

### Tasks

| # | Descripción | TDD | Archivos |
|---|------------|-----|----------|
| 2.1 | Añadir campo `trigger` a `RouteReoptimized` event | Test → Impl | `RouteReoptimized.php`, `ExceptionReoptimizationSubscriber.php`, `RouteOptimizationApiController.php`, test |
| 2.2 | `SkipReoptimizationSubscriber` | Test → Impl | nuevo subscriber + test (3 test cases) |
| 2.3 | `DelayReoptimizationSubscriber` | Test → Impl | nuevo subscriber + test (4 test cases, incluye cooldown) |
| 2.4 | Verificación: test suite + lint | — | — |

**Patrón:** Copiar estructura exacta de `ExceptionReoptimizationSubscriber` — mismos guards (`isAutoReoptimize()`, route ACTIVE), mismas dependencias (`RouteOptimizationService`, `VehicleLastPosition`).

**Riesgo (delay subscriber):** Necesita calcular retraso acumulado. `RouteStop.deliveredAt` existe, pero no hay `estimatedArrivalAt` por parada. Opciones:
- Usar `RouteSnapshot.originalStopOrder` + `durationMinutes` del snapshot para estimar ETAs
- Calcular delta entre `STOP_DELIVERED` events consecutivos vs tiempos OSRM
- **Approach simple (MVP):** Comparar `route.startedAt + snapshot.durationMinutes` vs `now()` — si la ruta lleva más del doble del tiempo estimado y quedan >30% paradas PENDING → reoptimizar

---

## Feature 3: Service Time Calibration (M)

**Goal:** Sugerir service times calibrados basándose en entregas históricas.

### Tasks

| # | Descripción | TDD | Archivos |
|---|------------|-----|----------|
| 3.1 | `ServiceTimeCalibrationService` | Test → Impl | nuevo servicio + test (4 test cases) |
| 3.2 | `GET /admin/route-planner/calibrations` endpoint | Test → Impl | `RoutePlannerController.php`, test |
| 3.3 | Aceptar `calibrated_service_times` en preview | Test → Impl | `BuildRoutesInput.php`, `RoutePlanningService.php`, test |
| 3.4 | Frontend: badges "Calibrado" + toggle en Step 2 | Impl | `RoutePlannerPage.tsx` |
| 3.5 | Verificación: test suite + lint | — | — |

**Riesgo confirmado:** `RouteComparisonService` devuelve métricas a nivel de ruta, NO per-stop service times. `RouteStop` tiene `deliveredAt` pero no `arrivedAt`.

**Mitigación:** Calcular service time real desde `RouteEvent` timestamps:
1. Obtener timestamp de `STOP_DELIVERED` event para la parada actual
2. Obtener timestamp del `STOP_DELIVERED` event de la parada anterior (o `STARTED` si es primera parada)
3. Restar transit time estimado (OSRM distance / avg speed) = approximate actual service time
4. Alternativa más simple: usar `deliveredAt[n] - deliveredAt[n-1] - transitTimeEstimate` como proxy

**Decisión:** Implementar approach simple en Task 3.1 — si la precisión es insuficiente, escalar en Phase 2 con `arrivedAt` tracking (nuevo campo en RouteStop, poblado por GPS geofencing).

---

## Archivos Compartidos (modificados por >1 feature)

| Archivo | Features |
|---------|----------|
| `RoutePlannerController.php` | 1 (optimizers endpoint + optimizer_name), 3 (calibrations endpoint + calibrated_service_times) |
| `BuildRoutesInput.php` | 1 (optimizerName), 3 (calibratedServiceTimes) |
| `RoutePlanningService.php` | 1 (use optimizer), 3 (pass service times) |
| `RoutePlannerPage.tsx` | 1 (optimizer dropdown), 3 (calibration badges + toggle) |

**Implicación:** Si se ejecutan en paralelo, merge conflicts en estos 4 archivos. Si secuencial, Feature 3 trabaja sobre el código de Feature 1.

---

## Verificación Final

Después de las 3 features:
- [ ] `php vendor/bin/phpunit` — 0 failures
- [ ] `make lint` — 0 errors
- [ ] `make manifest` — actualizar manifest
- [ ] Manual test: Route Planner flow completo con optimizer selector + calibrated times
- [ ] Execution log en `docs/superpowers/execution-logs/`
- [ ] Decision log entries en `docs/decisions/log.md`
- [ ] Retrospectiva
