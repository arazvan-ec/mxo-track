# Plan: Datos Históricos para Planificación — Fase 1 (Service Time Calibration)

**Spec:** `docs/superpowers/specs/2026-03-20-historical-data-learning-design.md`
**Goal:** Al planificar rutas, el sistema sugiere service times calibrados basándose en datos históricos de entregas reales, mejorando la precisión de las estimaciones del optimizador.

---

## Arquitectura

```
Route Planner Step 1 (shipments loaded)
    │
    ├─→ GET /admin/route-planner/calibrations?shipment_ids=...
    │       ↓
    │   ServiceTimeCalibrationService
    │       ↓
    │   RouteComparison data (actual vs estimated per address)
    │       ↓
    │   { shipmentId → suggestedSeconds, confidence, sampleSize }
    │
    ▼
Route Planner Step 2
    [✓ Apply calibrated times (default: on)]
    │
    ▼
POST /admin/route-planner/preview
    { calibrated_service_times: { shipmentId: seconds, ... } }
    │
    ▼
RoutePlanningService → passes to optimizer as per-stop service times
```

## Files to Modify/Create

| File | Action |
|------|--------|
| `backend/src/Service/ServiceTimeCalibrationService.php` | **Create** — calculates calibrated service times from historical data |
| `backend/src/Controller/Admin/RoutePlannerController.php` | **Modify** — add `calibrations` endpoint, accept `calibrated_service_times` in preview |
| `backend/src/Application/Route/BuildRoutesInput.php` | **Modify** — add `?array $calibratedServiceTimes` field |
| `backend/src/Application/Route/RoutePlanningService.php` | **Modify** — pass calibrated times to optimizer |
| `assets/react/pages/RoutePlannerPage.tsx` | **Modify** — fetch calibrations, show toggle, include in preview |
| `tests/Unit/Service/ServiceTimeCalibrationServiceTest.php` | **Create** |

## Existing Data Source

`RouteComparison` (created by `RouteComparisonService` post-route) contains:
- Per-stop comparison: estimated vs actual arrival/departure times
- Address info linked to each stop
- `serviceTimeDelta` = actual service time - estimated service time

`RouteSnapshot` stores planned vs actual metrics at route level.

## Tasks

### Task 1: ServiceTimeCalibrationService

- [ ] **Test:** `ServiceTimeCalibrationServiceTest`:
  - `testReturnsCalibrationForKnownAddress` — address with 5+ historical deliveries → returns average service time
  - `testIgnoresAddressWithFewSamples` — address with <3 deliveries → returns null (not enough data)
  - `testOnlyCalibratesWhenDeltaSignificant` — if avg differs <20% from default (300s) → returns null
  - `testReturnsConfidenceScore` — confidence based on sample size and variance
- [ ] **Implement:** `ServiceTimeCalibrationService`:
  - Method: `getCalibratedServiceTimes(array $shipmentIds): array`
  - For each shipment, lookup address in RouteComparison history
  - Calculate: average actual service time from last 30 days
  - Filter: need ≥3 samples, delta ≥20% from default 300s
  - Return: `[shipmentId => ['seconds' => int, 'confidence' => float, 'sampleSize' => int]]`
- [ ] **Commit**

### Task 2: Backend — Calibrations endpoint

- [ ] **Test:** Functional test — `GET /admin/route-planner/calibrations?shipment_ids[]=abc&shipment_ids[]=def` returns JSON with calibrations
- [ ] **Implement:** Add `calibrations()` action in `RoutePlannerController`
  - Accepts `shipment_ids` query param
  - Calls `ServiceTimeCalibrationService::getCalibratedServiceTimes()`
  - Returns JSON: `{ calibrations: { shipmentId: { seconds, confidence, sampleSize } } }`
- [ ] **Commit**

### Task 3: Backend — Accept calibrated_service_times in preview

- [ ] **Test:** `RoutePlanningServiceTest::testBuildRoutesWithCalibratedServiceTimes()` — calibrated times override default service_time in VROOM input
- [ ] **Implement:**
  - Add `?array $calibratedServiceTimes = null` to `BuildRoutesInput`
  - `RoutePlannerController::preview()`: read `calibrated_service_times` from payload
  - `RoutePlanningService`: when building VROOM jobs, if calibrated time exists for a shipment, use it instead of default
- [ ] **Commit**

### Task 4: Frontend — Calibration integration

- [ ] After shipments load in Step 1, fetch `/admin/route-planner/calibrations` with selected shipment IDs
- [ ] In Step 1 shipment list, show badge "Calibrado" on shipments with historical data (with tooltip: "Tiempo de servicio ajustado: X min basado en N entregas")
- [ ] In Step 2, add toggle: "Aplicar tiempos calibrados" (default: on)
- [ ] When toggle is on, include `calibrated_service_times` in preview request
- [ ] **Commit**

### Task 5: Verification

- [ ] Run full test suite: `php vendor/bin/phpunit`
- [ ] Run lint: `make lint`
- [ ] Manual flow: plan routes for addresses with historical data → verify calibrated times appear → verify optimizer uses them
- [ ] **Commit** any fixes

## Estimation

- **Complexity:** M (medium)
- **Tasks:** 5
- **Files affected:** ~8
- **Key risk:** RouteComparison data structure may not have per-stop service time detail — verify before implementing Task 1
