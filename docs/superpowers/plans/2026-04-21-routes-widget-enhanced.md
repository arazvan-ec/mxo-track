---
type: plan
feature: routes-widget-enhanced
date: 2026-04-21
branch: claude/enhance-routes-widget-8UzuC
spec: docs/superpowers/specs/2026-04-21-routes-widget-enhanced-design.md
---

# Plan — Routes Widget Enhanced

## Estimate

- **Files:** 4 (2 backend, 2 frontend)
- **Lines:** ~280 net additions (~120 backend incl. test, ~160 frontend)
- **Waves:** 3
- **TDD:** applied to backend controller (test-first)

## Phase 1 (v0) — Working implementation

### [parallel] Wave 1 — Contract layer (independent files)

Both tasks establish the new data contract; they touch **disjoint file sets** so
they run concurrently. Wave 2 consumes outputs from both.

- **1a — Backend: extend DTO + subqueries** · → files: `backend/src/Controller/Api/Admin/RouteListApiController.php`, `backend/tests/Unit/Controller/Admin/RouteListApiControllerTest.php`
  - **RED:** write `RouteListApiControllerTest::testListIncludesOptimizationMetrics` — asserts new fields present and correctly typed on a fixture route. Run → fails.
  - **RED:** write `testNextStopReflectsFirstPendingBySequence` — fixture with 3 stops (2 PENDING + 1 DELIVERED, out-of-sequence); assert `nextStop.sequence` = lowest PENDING sequence. Run → fails.
  - **RED:** write `testDeliveryHistogramBinsOnlyTodayAndByHour` — fixture with deliveries at 10:15, 10:45, 14:00 today and one yesterday; assert bins[10]=2, bins[14]=1, bins[others]=0. Run → fails.
  - **RED:** write `testNullFieldsPropagateAsNull` — route without distance/duration/weight/parcels; assert these fields are `null` (not `0`) in response. Run → fails.
  - **RED:** write `testNextStopNullWhenNoPendingStops` — fully delivered route; assert `nextStop` = `null`. Run → fails.
  - **GREEN:** extend DTO at `RouteListApiController.php:94-108` with 4 scalar fields from `Route` getters.
  - **GREEN:** add next-pending-stop subquery (pattern: mirror `stopCounts` at lines 74-92; grouped by `route_id`, `MIN(sequence)` where `status = PENDING`, JOIN back for address/recipient/window).
  - **GREEN:** add today's-deliveries subquery; aggregate into 24-int array in PHP.
  - **GREEN:** extend `$items[]` projection with all new fields.
  - Verify all 5 tests pass. Commit.
  - → produces: extended JSON response shape + passing test coverage.

- **1b — Frontend: extend TypeScript interface** · → files: `frontend/src/api/types.ts`
  - Add `RouteNextStop` interface (sequence, address, recipientName, windowStart, windowEnd — all strings or nullable strings).
  - Extend `RouteListItem` with: `totalDistanceKm`, `estimatedDurationMinutes`, `totalWeightKg`, `totalParcels` (all `number | null`), `nextStop: RouteNextStop | null`, `deliveryHistogram: number[] | null`.
  - Run `cd frontend && npx tsc --noEmit` to confirm type-only change compiles. Commit.
  - → produces: TypeScript contract matching Wave 1a's JSON output.

### Wave 2 — Render (depends on Wave 1a outputs + Wave 1b outputs)

- **2 — Frontend: update `ExpandableRouteCard` render** · → files: `frontend/src/widgets/DashboardKpisWidget.tsx`
  - **RED:** no unit tests for this component today; verification is the build + manual browser check. (Not adding Vitest infra in this scope.)
  - **GREEN — formatters:** add local helpers `formatKm`, `formatDuration`, `formatWeight`, `formatParcels`, `formatTimeWindow` at top of file (above `ExpandableRouteCard`). Each returns `null` if input is null; never a fallback string.
  - **GREEN — layout:** replace the single driver/vehicle row (lines 143-151) with a stacked block:
    - Row 1: name (unchanged)
    - Row 2: `driver · vehicle · customerName` (add customerName when present)
    - Row 3 (conditional): metric line `km · min · t · bultos` — skip entirely if all four null
    - Row 4-5 (conditional): next stop block — address (+ recipient if present) and window line — skip if `nextStop` null
    - Row 6 (conditional): `<SparklineSVG data={route.deliveryHistogram} color={accentColor} width={120} height={16} />` — skip if `deliveryHistogram` null or all zeros
  - **GREEN — card height:** raise `max-h-[600px]` on the expandable container (lines 116-118) to `max-h-[900px]` (empirical: 5 routes × ~150px content + padding).
  - Run `cd frontend && npm run build` (EXACT deploy command — `tsc -b && vite build`). Must pass.
  - Manual smoke test: open dashboard in browser, expand Rutas activas card, verify each conditional block renders/hides correctly with real data (at least 1 route with metrics, 1 without).
  - Commit.
  - → produces: user-visible richer widget.

### [parallel] Wave 3 — Verification (read-only commands, disjoint trees)

Each task runs verification only — no file writes. Commands operate on disjoint
trees (`backend/` vs `frontend/`) so they run concurrently without conflict.

- **3a — Backend full lint + test suite**
  - `cd backend && make lint` → passes.
  - `cd backend && php vendor/bin/phpunit tests/Unit/Controller/Admin/RouteListApiControllerTest.php` → all new tests green.
  - `cd backend && php vendor/bin/phpunit` → no regressions in pre-existing suite.

- **3b — Frontend build (EXACT deploy command)**
  - `cd frontend && npm run build` → `tsc -b` + `vite build` both succeed with zero errors.

## Phase 2 (Mature) — Not required

The v0 design is already the target architecture:
- No premature abstractions to remove (formatters are local because single consumer).
- No backend coupling to refactor (DDD boundary preserved — domain entities untouched).
- Test coverage is complete for the controller's new behavior.

**If a second widget needs the same formatters:** extract at that point, not now.
**If a third dashboard KPI needs expandable-lazy-load behavior:** then extract a reusable `ExpandableKpiCard`. One similar occurrence today (this work) is not a pattern yet.

## Acceptance checklist

- [ ] Backend DTO returns all 6 new fields (scalar + `nextStop` + `deliveryHistogram`).
- [ ] `RouteListApiControllerTest` passes with 5 new test cases.
- [ ] `make lint` + full `phpunit` green (no regressions).
- [ ] `npm run build` green (`tsc -b && vite build`).
- [ ] Visual check in browser: all conditional blocks render/hide correctly.
- [ ] No new dependencies added (frontend or backend).
- [ ] Widget `max-h` raised to accommodate new content.

## Capture plan

After finalize:
- Execution log: `docs/superpowers/execution-logs/2026-04-21-routes-widget-enhanced.md`
  - Estimate vs. actual (lines, files, waves, time)
  - Any deviations from this plan
  - Tag candidates: `dashboard`, `widget`, `route`, `dto-projection`, `kpi-card`
- Decision log entry in `docs/decisions/log.md`: only if Approach A's
  multi-subquery pattern becomes a reusable decision (unclear at this point).
- Pattern check: if this is the 3rd+ time we extend a list DTO with sub-aggregations
  (stopCounts precedent), consider graduating to a knowledge module note.
