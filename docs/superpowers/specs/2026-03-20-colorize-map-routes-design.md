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
- Use `symbol-placement: 'line'` with `symbol-spacing: 100` (100px between arrows — balances visibility without clutter)
- Arrow color matches route color
- `text-halo-color: rgba(0,0,0,0.7)` with 1px halo for contrast against light map backgrounds
- Arrows enabled by default on solid polylines, disabled on dashed lines (`showArrows` prop defaults to `!dashed`)

### 3. Edge cases

- If `routeColor` is `undefined` or not provided, stop markers fall back to status color (existing behavior preserved)
- Single-route views pass `route.color` directly; if a route has no color, the fallback chain handles it gracefully

### 4. Pages affected

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

## Approach Chosen: A — Prop Threading Simple

Aprobado por el usuario. Razón: mínima complejidad, cambios localizados, sin abstracciones nuevas.

## Alternatives Discarded

- **Approach B — React Context:** Over-engineering para 7 call sites. En multi-route views necesitaría un provider por ruta, añadiendo complejidad sin beneficio real.
- **Approach C — Color en StopData:** Mezcla presentación con datos. StopData es interfaz de datos, no de UI.
- Color all markers (including delivered) with route color — loses status visibility
- Use different marker shapes per route — more complex, less intuitive
