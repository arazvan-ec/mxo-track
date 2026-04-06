# Plan — Map Stop Interaction Gaps

**Date:** 2026-04-06
**Branch:** `claude/center-map-on-stop-hnC0V`
**Scope:** 3 pages with missing stop interaction on map

## Phase 1 (v0): Wire stop click → flyTo on all map pages

### [parallel] Tarea 1 + Tarea 2

- **Tarea 1: RoutePlannerPage — add stop click handler**
  - File: `frontend/src/pages/admin/RoutePlannerPage.tsx`
  - Add `handlePreviewStopClick(sequence: number)` that searches across all `previewRoutes[].stops` for matching sequence, calls `mapRef.flyTo(lng, lat, 16, { bottom: bottomPadding })`
  - Connect to `StopMarkersLayer.onStopClick` in the step 3 map rendering (line ~350)
  - Verify: TypeScript clean

- **Tarea 2: TestRoutingPage — fix stop click to center map**
  - File: `frontend/src/pages/admin/TestRoutingPage.tsx`
  - Rewrite `handleStopClick` to accept `(routeIdx: number, sequence: number)`
  - Keep route highlighting (`setHighlightedRouteIdx`)
  - Add `mapRef.flyTo(stop.lng, stop.lat, 16, { bottom: bottomPadding })` for the specific stop
  - Update `StopMarkersLayer.onStopClick` closure to pass both `idx` and `stop.seq`
  - Verify: TypeScript clean

### Tarea 3: RouteAnalysisPage — add stop_list widget to layout

- File: new migration `backend/migrations/VersionXXX.php`
- Add `stop_list` to `route_analysis` page layout for `half` (position after existing widgets) and `full` states
- Verify: migration runs without error

### Tarea 4: Verify all

- TypeScript: `npx tsc --noEmit`
- Vite build: `npx vite build`
- Commit and push
