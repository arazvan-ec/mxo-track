# Plan: Selección de Estrategia de Optimización — Fase 1 (MVP)

**Spec:** `docs/superpowers/specs/2026-03-20-optimization-strategy-selection-design.md`
**Goal:** El admin puede elegir qué optimizador usar al planificar rutas en el Route Planner.

---

## Arquitectura

El Route Planner Step 2 gana un selector de optimizador. El backend acepta el parámetro opcional `optimizer_name` y lo pasa al `RoutePlanningService`.

```
Route Planner Step 2  →  POST /admin/route-planner/preview  →  RoutePlanningService
  [optimizer selector]       { optimizer_name: "vroom" }         .buildRoutes(optimizerName)
                                                                      ↓
                                                              ProviderFactoryRegistry
                                                              .getFactory(optimizerName)
```

## Files to Modify/Create

| File | Action |
|------|--------|
| `backend/src/Controller/Admin/RoutePlannerController.php` | Add `GET /optimizers` endpoint, accept `optimizer_name` in preview |
| `backend/src/Application/Route/BuildRoutesInput.php` | Add `?string $optimizerName` field |
| `backend/src/Application/Route/RoutePlanningService.php` | Accept and use `optimizerName` param |
| `backend/src/Provider/ProviderFactoryRegistry.php` | Add `getAvailableProviders(ServiceType)` method |
| `assets/react/pages/RoutePlannerPage.tsx` (or equivalent) | Add optimizer selector in Step 2 |
| `tests/Unit/Application/Route/RoutePlanningServiceTest.php` | Test optimizer selection |
| `tests/Unit/Provider/ProviderFactoryRegistryTest.php` | Test `getAvailableProviders()` |

## Tasks

### Task 1: Backend — List available optimizers

- [ ] **Test:** `ProviderFactoryRegistryTest::testGetAvailableProviders()` — returns array of `[name, label]` for `ServiceType::RouteOptimizer`
- [ ] **Implement:** `ProviderFactoryRegistry::getAvailableProviders(ServiceType $type): array` — iterates registered factories, filters by type, returns `[['name' => 'vroom', 'label' => 'VROOM'], ...]`
- [ ] **Commit**

### Task 2: Backend — New endpoint GET /admin/route-planner/optimizers

- [ ] **Test:** Functional test — `GET /admin/route-planner/optimizers` returns JSON array with at least `vroom` and `greedy`
- [ ] **Implement:** Add `optimizers()` action in `RoutePlannerController`, calls `ProviderFactoryRegistry::getAvailableProviders(ServiceType::RouteOptimizer)`
- [ ] **Commit**

### Task 3: Backend — Accept optimizer_name in preview

- [ ] **Test:** `RoutePlanningServiceTest::testBuildRoutesWithSpecificOptimizer()` — passing `optimizerName: 'greedy'` uses GreedyOptimizer
- [ ] **Test:** `RoutePlanningServiceTest::testBuildRoutesWithNullOptimizerUsesDefault()` — null uses tenant default
- [ ] **Implement:**
  - Add `?string $optimizerName = null` to `BuildRoutesInput`
  - `RoutePlanningService.buildRoutes()`: if `optimizerName` is set, resolve via `ProviderFactoryRegistry` instead of injected default
  - `RoutePlannerController::preview()`: read `optimizer_name` from request payload, pass to `BuildRoutesInput`
- [ ] **Commit**

### Task 4: Frontend — Optimizer selector in Step 2

- [ ] Fetch `/admin/route-planner/optimizers` on mount
- [ ] Add select dropdown in Step 2 config panel:
  - Default option: "Automático (recomendado)" (sends `null`)
  - One option per available optimizer
- [ ] Include `optimizer_name` in preview request payload
- [ ] **Commit**

### Task 5: Verification

- [ ] Run full test suite: `php vendor/bin/phpunit`
- [ ] Run lint: `make lint`
- [ ] Manual flow: open Route Planner → Step 2 → select optimizer → preview → verify different optimizer is used
- [ ] **Commit** any fixes

## Estimation

- **Complexity:** S (small)
- **Tasks:** 5
- **Files affected:** ~7
