# Colorize Map Routes Design

## Goal

Make multi-route maps visually distinguishable by:
1. Coloring stop markers to match their route color (for PENDING stops)
2. Adding directional arrows along route polylines to show travel direction
3. Applying consistently across all map views that display routes

## Bounded Context

**Pragmatic** — This is purely frontend/UI, no domain logic involved.

## Current State

- Routes already use different colors from `ROUTE_COLORS` palette for polylines
- Stop markers always use `STOP_STATUS_COLORS` (blue for PENDING) regardless of which route they belong to
- No directional indicators on route lines
- When multiple routes are shown, it's hard to tell which stops belong to which route

## Design

### 1. Route-colored stop markers

- Add optional `routeColor` prop to `StopMarker` and `StopMarkersLayer`
- For PENDING stops: use `routeColor` if provided, otherwise fall back to status color
- For DELIVERED/EXCEPTION/SKIPPED stops: always use status color (green/red/gray) — status is more important than route membership once delivered

### 2. Directional arrows on polylines

- Add a MapLibre `symbol` layer on top of each route polyline with arrow icons (`▶`) placed along the line
- Use `symbol-placement: 'line'` with spacing to show direction of travel
- Arrow color matches route color

### 3. Pages affected

- `TestRoutingPage` — multi-route, pass route color to stop markers
- `RoutePlannerPage` — multi-route preview, pass route color to stop markers
- `FleetMap` — single active route, pass route color
- `RouteDetailPage` — single route, pass `route.color`
- `CustomerRouteDetailPage` — single route, pass `route.color`
- `DriverRoutePage` — single route, pass `route.color`
- `RouteAnalysisPage` — single route, pass blue color

## Trade-offs

- **Simple prop threading** vs **React context for route color**: Prop threading is simpler, no over-engineering for 6 call sites
- **Arrow via symbol layer** vs **custom line pattern**: Symbol layer is MapLibre-native and performant

## Alternatives Discarded

- Color all markers (including delivered) with route color — loses status visibility
- Use different marker shapes per route — more complex, less intuitive
