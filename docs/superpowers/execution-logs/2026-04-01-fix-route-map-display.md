# Execution Log — 2026-04-01 — Fix Route Map Display

**Type:** bug fix
**Branch:** `claude/fix-route-map-display-Xt8uD`

## Root Cause

The `OperatorDashboardPage` component only rendered `VehicleLayer` inside `MapCanvas`.
When a user clicked on a route in the Active Routes list, the map panned/zoomed to the
stop bounds (via `fitBounds`) but never rendered the route polyline or stop markers.

## Fix

Added `RoutePolylineLayer` and `StopMarkersLayer` as conditional children of `MapCanvas`,
rendered when `expandedRouteId` matches a route. Follows the same pattern used in
`RouteDetailPage`.

## Files Changed

- `frontend/src/pages/admin/OperatorDashboardPage.tsx` — Added imports for `RoutePolylineLayer`
  and `StopMarkersLayer`, added `selectedRoute` memo, rendered both layers conditionally.

## Verification

- TypeScript: no errors (`tsc --noEmit`)
- PHP lint: clean
- Backend tests: 6 errors + 5 failures (pre-existing on main, unrelated to this change)

## Lessons

- When unifying map views, ensure all interactive behaviors (polyline display, stop markers)
  are carried over — not just the base map and vehicle layer.
