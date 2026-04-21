---
type: feature
tags: []
files_touched: [docs/superpowers/plans/2026-03-20-business-decisions-implementation.md]
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-03-20 — Business Decisions Implementation (3 Features)

**Type:** feature
**Branch:** `claude/check-session-plan-status-JvIMd`

---

### Phase: Brainstorming
- **Alternatives evaluated:** Per the existing specs in `docs/superpowers/specs/`
- **Chosen approach:** Implement 3 MVP features sequentially: Optimizer Selector → Re-opt Triggers → Service Time Calibration
- **Past decisions consulted:** Event-first pattern, Provider Framework, repository interfaces in Domain layer
- **Complexity estimate:** S + S-M + M = M overall
- **Confidence:** high

### Phase: Planning
- **Task count:** 14 (5 + 4 + 5)
- **Files affected:** ~23
- **Plan:** `docs/superpowers/plans/2026-03-20-business-decisions-implementation.md`

### Phase: Implementation

**Feature 1: Optimizer Selector (5 tasks)**
- Added `ProviderFactoryRegistry::createByName()` and `getAvailableProviders()`
- Added `GET /admin/route-planner/optimizers` endpoint
- `BuildRoutesInput.optimizerName` → `RoutePlanningService` → `RouteBuilder` with `optimizerOverride`
- Frontend: optimizer dropdown in Step 2, `usePlannerOptimizers` hook
- 10 tests, all pass

**Feature 2: Re-optimization Triggers (4 tasks)**
- Added `trigger` field to `RouteReoptimized` event (default: 'manual')
- `SkipReoptimizationSubscriber`: listens to `StopSkipped`, same guard pattern as Exception subscriber
- `DelayReoptimizationSubscriber`: listens to `StopDelivered`, checks accumulated delay vs estimated duration, has cooldown
- Added `findLastByTypeForRoute()` to `RouteEventRepositoryInterface` + Doctrine implementation
- 9 tests, all pass

**Feature 3: Service Time Calibration (5 tasks)**
- `ServiceTimeCalibrationService`: SQL with CTE + window function (LAG) to compute per-address average service times from completed routes
- `GET /admin/route-planner/calibrations` endpoint
- `BuildRoutesInput.serviceTimeOverrides` → `RoutePlanningService` → `RouteBuilder.mapShipmentsToOptimizable()` with address-based override
- Frontend: `usePlannerCalibrations` hook, calibration toggle + address list in Step 2
- 7 tests, all pass

**Blockers:** None
**Deviations from plan:** Used DBAL Connection instead of RouteRepository for calibration service (simpler SQL with window functions). No Shipment entity changes needed.

### Phase: Verification
- **Unit tests:** 21/21 pass (all new tests for 3 features)
- **Full suite:** 544 tests, 6 pre-existing errors + 2 pre-existing failures (DemoSetupCommand, PostRouteAnalysisHandler, GitLogReader — unrelated)
- **TypeScript:** compiles cleanly (`tsc --noEmit`)
- **PHP lint:** 0 errors on all modified files

### Phase: Retrospective
- **Estimate accuracy:** Good — S+SM+M completed in one session as expected
- **What worked:** Sequential implementation allowed reusing patterns across features. TDD caught naming issues early (ShipmentPriority::Normal vs NORMAL, RouteStatus::COMPLETED vs DONE)
- **What didn't:** Nothing significant
- **Lessons:** SQL window functions (LAG/LEAD) are powerful for per-stop time delta calculations; better than loading entities into memory for aggregation
- **Tags:** route-optimization, event-subscribers, service-time, calibration
