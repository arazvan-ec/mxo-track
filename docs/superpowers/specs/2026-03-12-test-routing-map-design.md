# Test Routing Map — Design Spec

**Date:** 2026-03-12
**Status:** Approved

## Goal

Add a visual map page to the test routing endpoint that shows before/after route optimization with real road polylines from OSRM, including metrics panel.

## Decisions

- **Polyline source**: OSRM (`overview=full`) for both original and optimized routes
- **View**: Dedicated Twig page at `/admin/test-routing/map` (ROLE_ADMIN)
- **Comparison**: Both routes on the same map — original (red dashed) vs optimized (blue solid)
- **Frontend**: Leaflet 1.9.4 (already in project via CDN), polyline decoding via leaflet plugin or manual decode

## Architecture

### Backend Changes

#### 1. Extend MultiWaypointRouteResult with geometry

Add optional `geometry` field (encoded polyline string from OSRM):

```php
final readonly class MultiWaypointRouteResult
{
    public function __construct(
        public float $totalDistanceKm,
        public float $totalDurationSeconds,
        public array $legs,
        public ?string $geometry = null,  // NEW: OSRM encoded polyline
    ) {}
}
```

#### 2. Add geometry support to OsrmRoutingEngine::routeWithWaypoints()

Change `overview=false` to `overview=full` and extract `routes[0].geometry` from OSRM response.

Note: only change `routeWithWaypoints()`, not `route()` (point-to-point doesn't need polyline).

#### 3. New controller action: TestRoutingController::map()

```
GET /admin/test-routing/map
```

Flow:
1. Create test data (same as `run()`)
2. Get OSRM waypoint route for original stop order → polyline "before"
3. Optimize with VROOM
4. Apply optimized order
5. Get OSRM waypoint route for optimized stop order → polyline "after"
6. Estimate timing
7. Render Twig template with all data
8. Cleanup test data from DB

Data passed to template:
- `stopsBefore`: array of {seq, recipient, address, lat, lng}
- `stopsAfter`: array of {seq, recipient, address, lat, lng}
- `polylineBefore`: encoded polyline string
- `polylineAfter`: encoded polyline string
- `origin`: {lat, lng, address}
- `metrics`: {distanceBefore, distanceAfter, savedPercent, durationMinutes, timing}

### Frontend (Twig Template)

#### Template: `admin/test-routing/map.html.twig`

Layout following existing patterns (analysis.html.twig):
- Full-width map (min-height 600px)
- Metrics panel below map
- Legend showing line colors

Map elements:
- **Origin marker**: Green circle with "O" label
- **Stop markers (before)**: Red numbered circles (original order), smaller/faded
- **Stop markers (after)**: Blue numbered circles (optimized order)
- **Polyline before**: Red dashed line
- **Polyline after**: Blue solid line
- **Fit bounds**: Auto-fit to show all markers

Metrics panel:
- Distance before / after / saved %
- Estimated driving time
- Estimated total time (driving + delivery)
- Number of stops

#### Polyline Decoding

OSRM returns Google Encoded Polyline format. Options:
- Inline decode function (~20 lines JS) — **preferred**, no extra dependency
- The `@mapbox/polyline` library via CDN

## Files to Create/Modify

| File | Action |
|------|--------|
| `backend/src/Routing/MultiWaypointRouteResult.php` | Add `?string $geometry` param |
| `backend/src/Routing/OsrmRoutingEngine.php` | Change `overview=false` to `overview=full`, extract geometry |
| `backend/src/Controller/Admin/TestRoutingController.php` | Add `map()` action |
| `backend/templates/admin/test-routing/map.html.twig` | New template with Leaflet map |

## Non-Goals

- No real-time updates (this is a one-shot test)
- No persistence (cleanup after render)
- No Alpine.js reactivity needed (static map)
