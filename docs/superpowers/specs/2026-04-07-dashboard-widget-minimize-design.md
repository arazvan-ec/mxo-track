# Spec — Dashboard Widget Minimize

**Date:** 2026-04-07
**Type:** Feature
**Branch:** `claude/dashboard-widget-minimize-6IwdA`

## Problem

The admin dashboard (`/admin`) has 5 content sections (system health, infrastructure,
KPIs, mini reports, activity feed) that always display fully expanded. On mobile
especially, this creates a long scroll. Users need the ability to minimize/collapse
individual sections to focus on what matters to them.

## Chosen Approach: B — Migrate to React Widget System

Integrate the dashboard into the existing React configurable widget system, creating
new dashboard-specific widget types. Each widget renders as a collapsible card with
a header that can be clicked to minimize/expand. State persists in `localStorage`.

### Alternativa A (descartada): Alpine.js puro
- Ventaja: ~80 lineas de cambio, 1 archivo
- Desventaja: No integra con el widget system React existente, no configurable

### Alternativa C (descartada): Alpine.js + API backend de preferencias
- Ventaja: Persiste entre sesiones/dispositivos
- Desventaja: Over-engineering, requiere migracion de BD para preferencias de UI

### Trade-off del Approach B
- Opcion mas costosa en LOC pero unifica todo el frontend bajo un solo sistema
- El dashboard es la pagina mas visitada — vale la pena invertir en calidad

## Design

### New Widget Types (5)

| Widget Type | Data Source | Content |
|---|---|---|
| `system_health` | `/admin/health` → `health` + `live` | 6 service cards (DB, Redis, Traccar, Mercure, OSRM, VROOM) |
| `infrastructure_metrics` | `/admin/health` → `live.positions`, `live.disk`, `live.last_ingestion` | 3 metric cards (positions, DB size, last ingestion) |
| `dashboard_kpis` | `/admin/health` → `metrics` | 4 KPI cards (routes, stops, imports, positions/hour) |
| `mini_reports` | New `/api/admin/dashboard-reports` endpoint | Chart (7-day deliveries) + top 5 drivers |
| `activity_feed` | Mercure SSE (same as current) | Live position feed |

### New PageKey

Add `admin_dashboard` to `PageKey` enum (backend + frontend).

### WidgetRenderer Enhancement — Minimize Support

The current `WidgetRenderer` renders widgets without any chrome. For the dashboard
(and potentially future pages), we need a **collapsible wrapper**:

```tsx
interface CollapsibleWidgetProps {
  title: string;
  icon?: ReactNode;
  defaultExpanded?: boolean;
  storageKey: string;  // localStorage key for persist
  children: ReactNode;
}
```

Each widget in the registry gains optional metadata: `title`, `icon`, `collapsible`.
The `WidgetRenderer` reads this metadata and wraps collapsible widgets in the
`CollapsibleWidget` component.

### Dashboard Page (React)

New `AdminDashboardPage` component mounted in Twig via widget entry point:
- Uses `usePageLayout('admin_dashboard')`
- Fetches data from `/admin/health` (existing endpoint, returns all needed data)
- Renders widgets in a standard page layout (NOT BottomSheet — no map)
- Auto-refreshes every 30 seconds (same as current Alpine.js behavior)
- SSE for activity feed (same Mercure connection as current)

### Twig Integration

**Chosen: Option A** — Keep `/admin` as the Twig host and mount the React dashboard
widget inside it (same pattern as `sidebar-widget.tsx`). This preserves the existing
URL, breadcrumbs, and base layout integration.

### localStorage Persistence

Key format: `mxo-dashboard-widget-{widgetType}-minimized`
Value: `"true"` or absent (expanded by default)

### API Changes

1. **New endpoint:** `GET /api/admin/dashboard-reports` — returns daily_deliveries + top_drivers
2. **Existing:** `/admin/health` already returns health + live + metrics (reuse as-is)
3. **Backend:** Add `ADMIN_DASHBOARD = 'admin_dashboard'` to `PageKey` enum
4. **Backend:** Add 5 new `WidgetType` enum cases
5. **Migration:** Seed `widget_definition` rows + `page_layout` + `page_layout_widget` rows

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `adminDashboard()` Alpine.js component | **Transform** | Logic migrates to React hooks; Alpine code removed |
| System health cards (Twig) | **Transform** | Becomes `SystemHealthWidget` React component |
| Infrastructure metrics (Twig) | **Transform** | Becomes `InfrastructureMetricsWidget` React component |
| KPI cards (Twig) | **Transform** | Becomes `DashboardKpisWidget` React component |
| Mini reports + Chart.js (Twig) | **Transform** | Becomes `MiniReportsWidget` React component |
| Activity feed (Twig + SSE) | **Transform** | Becomes `ActivityFeedWidget` React component |
| Health pills (Twig) | **Omit** | Redundant with system health cards |
| Reports banner (Twig) | **Include** | Keep as static element outside widget system |
| `/admin/health` endpoint | **Include** | Reuse as data source for widgets |
| `SystemHealthService` | **Include** | No changes needed |
| `AdminMetricsService` | **Include** | No changes needed |
| `ReportingService` | **Include** | Expose via new API endpoint |
| `WidgetType` enum | **Transform** | Add 5 new cases |
| `PageKey` enum | **Transform** | Add 1 new case |
| `WIDGET_REGISTRY` | **Transform** | Add 5 new entries |
| `WidgetRenderer` | **Transform** | Add collapsible wrapper support |
| `WidgetProps` interface | **Include** | Reuse as-is |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| Health pills compact view | Omit | Redundant — same info in system health cards |
| Chart.js CDN | Omit | Replace with simple CSS bar chart or recharts |
| Mercure token fetching | Include (different mechanism) | React hook for Mercure token |
