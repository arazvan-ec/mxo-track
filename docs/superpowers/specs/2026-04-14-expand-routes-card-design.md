# Spec: Expandable Routes Card on Admin Dashboard

**Date:** 2026-04-14
**Approach:** B — Lazy-load from existing `/api/admin/routes` endpoint

## Problem
The "Rutas activas" KPI card shows only a number. The user wants to expand it to see route details (name, driver, vehicle, stops progress).

## Design

### Frontend-only change
The existing `/api/admin/routes?status=ACTIVE&limit=5` endpoint already returns everything needed (`RouteListItem`: publicId, name, driverName, vehicleName, status, deliveredStops, totalStops).

### Behavior
- The "Rutas activas" `KpiCard` becomes tappable/clickable
- On click, it expands below the number to show a compact list of active routes
- First expansion triggers a fetch to `/api/admin/routes?status=ACTIVE&limit=5`
- Shows loading spinner while fetching, then the route list
- Each route row: name, driver/vehicle, stops progress bar (delivered/total)
- Click again collapses back to just the number
- Other 3 KPI cards remain unchanged

### Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `KpiCard` component | Include — base for non-expandable cards | 3 of 4 cards keep this |
| `DashboardKpisWidget` | Transform — routes card becomes expandable | Only the routes card changes |
| `/api/admin/routes` endpoint | Include — data source | Already has status filter + limit |
| `RouteListItem` TypeScript type | Include — already defined in types.ts | No new types needed |
| `CollapsibleWidget` | Omit — wraps entire sections, not individual cards | Wrong granularity |
| `AdminMetricsService` | Omit — no backend changes | Enfoque B |

### Omission Decisions
| Element | Decision | Justification |
|---------|----------|---------------|
| Backend changes | Omit | Existing endpoint suffices |
| New widget type | Omit | This is an enhancement to `dashboard_kpis`, not a new widget |
| Pagination | Omit | 5 routes is enough for a dashboard preview |
