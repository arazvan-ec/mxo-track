# Plan: Widget System Full Migration

**Spec:** `docs/superpowers/specs/2026-04-01-widget-migration-design.md`

## Phase 1: Implement 6 Missing Widgets

### [parallel] Tarea 1a-1f — New Widget Implementations

- **1a:** `KpiPillsWidget` — wrap KpiPills component
- **1b:** `StopListWidget` — wrap StopListPanel component  
- **1c:** `VehicleInfoWidget` — wrap VehicleInfoPanel component
- **1d:** `DriverInfoWidget` — progress bar + driver name + current stop
- **1e:** `ShipmentDetailsWidget` — shipment info card
- **1f:** `DeliveryTimelineWidget` — event timeline

### Tarea 2 — Update registry.ts with all 6 new widgets

## Phase 2: Generalize Existing Widgets

### [parallel] Tarea 3a-3d — Adapt existing widgets for multi-page data

- **3a:** `MetricPairsWidget` — accept generic metrics (route detail, analysis)
- **3b:** `RouteCardListWidget` — accept FleetRoute[] alongside TestRoutingRoute[]
- **3c:** `MapLegendWidget` — accept generic route array with colors
- **3d:** `RouteComparisonWidget` — accept generic before/after data

## Phase 3: Migrate Pages

### [parallel] Tarea 4a-4g — Page migrations

- **4a:** FleetMapPage → widget system
- **4b:** OperatorDashboardPage → widget system
- **4c:** RouteDetailPage → widget system
- **4d:** RouteAnalysisPage → widget system
- **4e:** RoutePlannerPage → widget system (step panels as widgets)
- **4f:** DriverRoutePage → widget system
- **4g:** CustomerRouteDetailPage → widget system

## Phase 4: Verify

### Tarea 5 — TypeScript check + build verification
