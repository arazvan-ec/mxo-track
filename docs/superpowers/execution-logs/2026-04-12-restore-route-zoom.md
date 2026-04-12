# Execution Log — 2026-04-12 — Restore Route Zoom-to-Fit

**Type:** bug fix
**Branch:** `claude/restore-route-zoom-mqAch`

## Problem
When accessing a route detail page (admin, customer, or driver), the map showed
a default zoomed-out view of Spain instead of fitting to the route's stop bounds.

## Root Cause
Three route detail pages created in commit `c85fd95` were missing the `useEffect` +
`fitBounds` call that other map pages (RouteAnalysisPage, FleetMapPage, RoutePlannerPage)
use to auto-zoom the map to show all route stops on load.

## Pattern-Wide Search
- `RouteAnalysisPage` — ✅ has fitBounds
- `FleetMapPage` — ✅ has fitBounds on route selection
- `RoutePlannerPage` — ✅ has fitBounds with setTimeout
- `TestRoutingPage` — ✅ has fitBounds with sheet padding
- `RouteDetailPage` — ❌ missing (fixed)
- `CustomerRouteDetailPage` — ❌ missing (fixed)
- `DriverRoutePage` — ❌ missing (fixed)

## Fix
Added `useEffect` + `fitBounds` with a one-time ref guard (`hasFittedRef`) to all
three pages. Uses `setTimeout(200ms)` to ensure the map ref is ready, matching the
pattern from `RoutePlannerPage`.

## Files Changed
- `frontend/src/pages/admin/RouteDetailPage.tsx`
- `frontend/src/pages/customer/CustomerRouteDetailPage.tsx`
- `frontend/src/pages/driver/DriverRoutePage.tsx`

## Verification
- Frontend build (`npm run build`): ✅ clean
- PHP lint (`make lint`): ✅ clean
- PHPUnit: 667 tests, 6 errors + 6 failures (all pre-existing, unrelated to this change)

## Retrospective
The pages were created without fitBounds because the commit that introduced them focused
on the bottom sheet + widget system wiring, not on map behavior. The fitBounds pattern
was not documented as a required step when creating new map pages. Consider adding this
to a "map page checklist" in the UI knowledge module.
