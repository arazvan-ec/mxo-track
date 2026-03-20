# Plan: Política de Re-optimización — Fase 1 (Expandir Triggers)

**Spec:** `docs/superpowers/specs/2026-03-20-reoptimization-policy-design.md`
**Goal:** Añadir triggers de re-optimización automática para skip y retraso, siguiendo el patrón del ExceptionReoptimizationSubscriber existente.

---

## Arquitectura

Nuevos event subscribers que escuchan domain events y disparan `reoptimizePendingStops()` cuando se cumplen las condiciones. Mismo patrón que `ExceptionReoptimizationSubscriber`.

```
StopSkipped event  →  SkipReoptimizationSubscriber  →  reoptimizePendingStops()
StopDelivered event  →  DelayReoptimizationSubscriber  →  (calcular retraso) → reoptimizePendingStops()
```

## Files to Modify/Create

| File | Action |
|------|--------|
| `backend/src/EventSubscriber/SkipReoptimizationSubscriber.php` | **Create** — trigger on StopSkipped |
| `backend/src/EventSubscriber/DelayReoptimizationSubscriber.php` | **Create** — trigger on StopDelivered if delay > threshold |
| `backend/src/Domain/Route/Event/RouteReoptimized.php` | **Modify** — add `trigger` field (string) |
| `backend/src/EventSubscriber/ExceptionReoptimizationSubscriber.php` | **Modify** — pass `trigger: 'exception'` to RouteReoptimized |
| `tests/Unit/EventSubscriber/SkipReoptimizationSubscriberTest.php` | **Create** |
| `tests/Unit/EventSubscriber/DelayReoptimizationSubscriberTest.php` | **Create** |

## Existing Pattern to Follow

`ExceptionReoptimizationSubscriber`:
1. Listens to `StopExceptionReported`
2. Checks: `$route->isAutoReoptimize()` && route is ACTIVE
3. Gets driver position from `VehicleLastPosition`
4. Calls `RouteOptimizationService::reoptimizePendingStops()`
5. Applies optimized order
6. Dispatches `RouteReoptimized` event

## Tasks

### Task 1: Add trigger field to RouteReoptimized event

- [ ] **Test:** `RouteReoptimizedTest::testTriggerField()` — event stores trigger string
- [ ] **Implement:** Add `private string $trigger` to `RouteReoptimized` with getter. Accept in constructor with default `'manual'`.
- [ ] **Modify** `ExceptionReoptimizationSubscriber` to pass `trigger: 'exception'`
- [ ] **Modify** `RouteOptimizationApiController::reoptimizeRoute()` to pass `trigger: 'manual'`
- [ ] **Commit**

### Task 2: SkipReoptimizationSubscriber

- [ ] **Test:** `SkipReoptimizationSubscriberTest`:
  - `testReoptimizesOnSkipWhenAutoEnabled` — skip + autoReoptimize=true → reoptimizePendingStops called
  - `testDoesNotReoptimizeWhenAutoDisabled` — skip + autoReoptimize=false → not called
  - `testDoesNotReoptimizeWhenRouteNotActive` — skip on COMPLETED route → not called
- [ ] **Implement:** `SkipReoptimizationSubscriber` — listens to `StopSkipped`, same guards as ExceptionReoptimizationSubscriber, passes `trigger: 'skip'`
- [ ] **Commit**

### Task 3: DelayReoptimizationSubscriber

- [ ] **Test:** `DelayReoptimizationSubscriberTest`:
  - `testReoptimizesWhenDelayExceedsThreshold` — accumulated delay > 30 min → reoptimize
  - `testDoesNotReoptimizeWhenDelayBelowThreshold` — delay < 30 min → skip
  - `testDoesNotReoptimizeWhenAutoDisabled` — delay > threshold but autoReoptimize=false → skip
  - `testCooldown` — re-optimized < 10 min ago → skip (prevent rapid-fire)
- [ ] **Implement:** `DelayReoptimizationSubscriber`:
  - Listens to `StopDelivered`
  - Calculates accumulated delay: compares actual delivery timestamps vs estimated
  - Threshold: 30 min (hardcoded constant, configurable in Phase 2)
  - Cooldown: checks `RouteEvent` for last REOPTIMIZED event timestamp, skips if < 10 min ago
  - Passes `trigger: 'delay'`
- [ ] **Commit**

### Task 4: Verification

- [ ] Run full test suite: `php vendor/bin/phpunit`
- [ ] Run lint: `make lint`
- [ ] Verify existing `ExceptionReoptimizationSubscriber` still works (no regressions)
- [ ] **Commit** any fixes

## Estimation

- **Complexity:** S-M (small-medium)
- **Tasks:** 4
- **Files affected:** ~8
