# Feature 1.4: Dashboard Operador Mejorado — Design Spec

## Goal

Enhance the operator live dashboard with additional KPIs, top drivers leaderboard, and auto-refresh via Mercure SSE, making it the primary operational control screen.

## Current State

- `operator/dashboard_live.html.twig`: 4 KPI cards (active routes, deliveries today, exceptions, completion rate), Leaflet map, active routes table
- KPIs computed inline in controller (not testable, not refreshable)
- Mercure SSE updates vehicle positions only; KPIs are static after page load
- `SlaMetricsService` already has success rate, driver ranking (unused on operator dashboard)
- `AdminMetricsService` has simple counts

## Design

### 1. `OperatorKpiService`

Extract KPI computation from controller into a testable service. Uses DQL QueryBuilder (Doctrine ORM) for type-safe queries against `Route`, `RouteStop`, and `VehicleLastPosition` entities with `RouteStatus` and `RouteStopStatus` enums. `getTopDrivers()` uses native DBAL for PostgreSQL-specific `FILTER (WHERE ...)` syntax.

```php
final class OperatorKpiService {
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function collectKpis(): array {
        return [
            'activeRoutes' => ...,       // DQL: Route entity
            'deliveriesToday' => ...,     // DQL: RouteStop entity
            'exceptionsToday' => ...,     // DQL: RouteStop + Route join
            'completionRate' => ...,      // DQL: RouteStop grouped by route
            'successRate7d' => ...,       // DQL: RouteStop + Route join
            'vehiclesWithPosition' => ..., // DQL: VehicleLastPosition entity
            'topDrivers' => [...],         // DBAL: PostgreSQL FILTER (WHERE ...)
        ];
    }
}
```

KPIs:
- **activeRoutes**: Count of routes with status ACTIVE or PLANNED
- **deliveriesToday**: Stops delivered since midnight
- **exceptionsToday**: Exception stops in active/planned/done routes
- **completionRate**: delivered/total across active routes (%)
- **successRate7d**: Delivered / (Delivered + Exceptions) over last 7 days (%)
- **vehiclesWithPosition**: Count of vehicles that have a VehicleLastPosition record
- **topDrivers**: Top 3 drivers by deliveries in last 7 days (name, deliveries, success_rate)

### 2. JSON KPI Endpoint

`GET /operator/dashboard/kpis` → JSON response with all KPIs for AJAX refresh.

### 3. Template Enhancements

- Add 2 more KPI cards: success rate (7d), vehicles active
- Add top 3 drivers mini-leaderboard section below KPI cards
- Wire Mercure route progress events to auto-refresh KPIs via AJAX

### 4. SSE Auto-Refresh

When route progress events arrive via `/operator/fleet` topic:
- Debounce 3 seconds
- Fetch `/operator/dashboard/kpis` JSON
- Update all KPI values reactively via Alpine.js

## Out of Scope

- Changing the map (Feature 1.5)
- SLA trend charts (already in admin dashboard)
- Modifying admin or customer dashboards
