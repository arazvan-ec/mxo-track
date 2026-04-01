# Widget System Migration — Full Implementation

**Date:** 2026-04-01
**Type:** Feature (major enhancement)
**Branch:** `claude/add-map-stops-sEXEI`

## Problem

Only 1 of 8 map pages (TestRoutingPage) uses the widget system. The remaining 7 pages have hardcoded BottomSheet content. 6 of 10 widget types are placeholders. This prevents admin-configurable layouts and creates inconsistency.

## Design

### Widget Data Contract

Each widget receives `data: unknown` and extracts what it needs. Pages pass a `pageData` object containing all relevant data. Widgets are defensive — return null if required fields are missing.

### 6 New Widget Implementations

| Widget | Wraps | Data Shape |
|--------|-------|------------|
| `kpi_pills` | `KpiPills` component | `{ kpi: FleetKpi }` |
| `stop_list` | `StopListPanel` component | `{ stops, selectedSequence, onStopClick, showEta, maxItems }` |
| `vehicle_info` | `VehicleInfoPanel` component | `{ vehicleInfo: VehicleInfo }` |
| `driver_info` | New (progress bar + driver) | `{ driverName, deliveredCount, totalCount, currentStop }` |
| `shipment_details` | New (shipment card) | `{ shipment: { publicId, address, status, recipient } }` |
| `delivery_timeline` | New (event timeline) | `{ events: Array<{ time, label, status }> }` |

### 4 Existing Widget Generalizations

Existing widgets are TestRouting-specific. Generalize to accept multiple data shapes:
- `metric_pairs`: Accept `{ metrics }` with any numeric key/value pairs
- `route_card_list`: Accept `{ routes: FleetRoute[] }` OR `{ routesData: TestRoutingRoute[] }`
- `map_legend`: Accept `{ routes }` with color info
- `route_comparison`: Accept generic before/after metrics

### 7 Page Migrations

Each page: `usePageLayout(pageKey)` → build `pageData` → `<WidgetRenderer />`

**Keep outside widget system:** EntityActionPanel (selection-driven, not layout-configurable)

### RoutePlannerPage Exception

Multi-step wizard pattern doesn't fit widget layout. Keep hardcoded but wrap each step's content as widgets for future flexibility.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| TestRoutingPage widget integration | Include | Already working, reference pattern |
| FleetSidebar component | Transform | Content becomes route_card_list + kpi_pills widgets |
| StopListPanel | Include | Wrapped as stop_list widget |
| VehicleInfoPanel | Include | Wrapped as vehicle_info widget |
| KpiPills | Include | Wrapped as kpi_pills widget |
| RouteSummaryBar | Include | Used directly in pages, not as widget (always visible) |
| EntityActionPanel | Include as-is | Not layout-configurable, kept outside widget system |
| RouteMetricsPanel | Transform | Becomes metric_pairs widget data source |
