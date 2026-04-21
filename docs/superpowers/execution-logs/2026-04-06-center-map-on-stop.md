---
type: bugfix
tags: [map, stop]
files_touched: []
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
- Later unified both handlers into single `handleStopClick(routePublicId, sequence)`, map layer passes routePublicId via closure

## Verification

- TypeScript: clean (0 errors)
- Vite build: success (7.34s)
- PHPUnit: 602 tests, 11 pre-existing failures (DemoSetupCommand + PostRouteAnalysisHandler), 0 new failures

## Lessons

- The widget system passes `pageData` as opaque `unknown` through `WidgetRenderer` — callbacks must be included in `pageData` to reach widgets
- `handleStopClick` for map markers uses sequence-only lookup which could collide across routes; the widget version uses routePublicId+sequence (more correct)
