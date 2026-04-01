# Execution Log — 2026-04-01 — Fix Route Map Display

**Type:** bug fix
**Branch:** `claude/fix-route-map-display-zwtz3`

## Problem

Routes in the operator dashboard (`/app/admin/operator-dashboard`) showed in the
bottom sheet list but did NOT render on the map (no polylines, no stop markers).
Only vehicle markers were visible.

## Root Cause

The `RouteMapProjection` relied exclusively on `RouteSnapshot` stop states for
coordinates and polylines. When a route's snapshot lacked lat/lng in its stop
states (e.g., route created without optimization, or snapshot captured before
geocoding), the projection returned stops without coordinates. The frontend
filtered these out (`s.lat && s.lng`), resulting in zero markers on the map.

Additionally, `FleetStop.recipient` in the frontend didn't match the backend's
`recipientName` field, so recipient names never displayed in the stop list.

## Fix

### Backend
1. **PolylineEncoder** — New utility (inverse of PolylineDecoder) that encodes
   `[lat, lng]` pairs into Google Encoded Polyline format.
2. **RouteMapProjection fallback** — When snapshot lacks stop coordinates, falls
   back to reading `RouteStop` entities directly. When no OSRM polyline exists,
   generates a straight-line polyline connecting stops.
3. **RouteStopRepositoryInterface.findByRoutes()** — New batch query for efficient
   fallback loading across multiple routes.
4. **Removed duplicate FleetMapDataController** in Infrastructure/MapView/.

### Frontend
5. Fixed `FleetStop.recipient` → `recipientName` type mismatch across
   OperatorDashboardPage and FleetMapPage.

## Verification

- PolylineEncoder: 4 unit tests (roundtrip, empty, single point, known values)
- RouteViewServiceTest: 7 tests pass with updated constructor
- TypeScript: clean compilation, no type errors
- All pre-existing test failures unrelated to changes

## Lessons

- When projecting domain data through snapshots, always have a fallback to live
  entities — snapshots can be stale or incomplete.
- Frontend type definitions must exactly match backend serialization field names.
