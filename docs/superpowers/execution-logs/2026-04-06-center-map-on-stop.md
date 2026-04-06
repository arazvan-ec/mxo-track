# Execution Log — 2026-04-06 — Center Map on Stop Click

**Type:** feature (wiring fix)
**Branch:** `claude/center-map-on-stop-hnC0V`

## Brainstorming

- **Problem:** Clicking a stop in the RouteCardListWidget (bottom sheet) didn't center the map on that stop
- **Root cause:** `pageData` object didn't pass `onStopClick` callback to the widget system
- **Approach:** Create `handleWidgetStopClick(routePublicId, sequence)` matching the widget's expected signature, add to `pageData`
- **Alternatives:** None — pure wiring issue with single correct solution
- **Complexity:** Trivial (1 file, ~25 lines added)

## Planning

Single task: wire callback through pageData to RouteCardListWidget

## Implementation

- Added `handleWidgetStopClick` callback in `OperatorDashboardPage.tsx` with `(routePublicId, sequence)` signature
- Uses both `routePublicId` AND `sequence` to find stop (more precise than existing `handleStopClick` which only uses sequence)
- Calls `selectStop()` + `mapRef.flyTo()` with bottom sheet padding
- Added `onStopClick: handleWidgetStopClick` to `pageData` useMemo

## Verification

- TypeScript: clean (0 errors)
- Vite build: success (7.34s)
- PHPUnit: 602 tests, 11 pre-existing failures (DemoSetupCommand + PostRouteAnalysisHandler), 0 new failures

## Lessons

- The widget system passes `pageData` as opaque `unknown` through `WidgetRenderer` — callbacks must be included in `pageData` to reach widgets
- `handleStopClick` for map markers uses sequence-only lookup which could collide across routes; the widget version uses routePublicId+sequence (more correct)

---

## Phase 2 — Map Stop Interaction Gaps (same session)

### Scope
3 pages with missing/incomplete stop interaction on map markers.

### Changes

| Page | Problem | Fix |
|------|---------|-----|
| RoutePlannerPage | `StopMarkersLayer` without `onStopClick` | Added `handlePreviewStopClick` + wired to layer |
| TestRoutingPage | `handleStopClick` only highlighted route, no flyTo | Rewritten to accept `(routeIdx, sequence)`, added flyTo |
| RouteAnalysisPage | Frontend ready but widget layout missing `stop_list` | Migration adds `stop_list` to half/full states |

### Verification
- TypeScript: 0 errors
- Vite build: success (5.77s)
- PHP syntax: clean
- Migration: follows established pattern (explicit id, DO $$ block)

---

## Phase 3 — Workflow Improvements (same session)

### Problem
Retrospective phase skipped twice in same session. Wiring-only changes forced through
full brainstorm+plan with zero design value.

### Changes

| # | Improvement | File |
|---|------------|------|
| 1 | Retrospective promoted from SOFT to HARD in pre-push gate | `.claude/hooks/pre-push-gate.sh` |
| 2 | Deviation criteria for wiring-only changes (<30 lines, 0 decisions) | `CLAUDE.md` |
| 3 | Calibration data: wiring tasks (~15 lines, <5 min) | `docs/knowledge/superpowers-skills.md` |
| 4 | Calibration data: boilerplate migrations (~120 lines, <10 min) | `docs/knowledge/superpowers-skills.md` |

### Verification
- Bash syntax: clean
- TypeScript: 0 errors (no frontend changes)
