# Plan — Dashboard Widget Minimize

**Date:** 2026-04-07
**Spec:** `docs/superpowers/specs/2026-04-07-dashboard-widget-minimize-design.md`
**Branch:** `claude/dashboard-widget-minimize-6IwdA`

## Phase 1 (v0): Working Dashboard with Minimizable Widgets

### [parallel] Wave 1: Backend Enums + API Endpoint (3 tasks)

**Task 1a: Add new WidgetType enum cases**
- File: `backend/src/Enum/WidgetType.php`
- Add: `SYSTEM_HEALTH`, `INFRASTRUCTURE_METRICS`, `DASHBOARD_KPIS`, `MINI_REPORTS`, `ACTIVITY_FEED`
- File: `frontend/src/types/layout.ts` — add matching string literals to `WidgetType`
- → produces: enum values available for registration

**Task 1b: Add PageKey enum case**
- File: `backend/src/Enum/PageKey.php` — add `ADMIN_DASHBOARD = 'admin_dashboard'`
- File: `frontend/src/types/layout.ts` — add `'admin_dashboard'` to `PageKey`
- → produces: page key for layout resolution

**Task 1c: New API endpoint for dashboard reports**
- File: `backend/src/Controller/Api/DashboardReportsController.php`
- Route: `GET /api/admin/dashboard-reports` (ROLE_ADMIN)
- Returns: `{daily_deliveries: [...], top_drivers: [...]}`
- Uses: `ReportingService::getDailyDeliveries(7)` + `::getTopDrivers(5, 7)`
- TDD: write test first → implement → verify green
- → produces: API data source for MiniReportsWidget

### Wave 2: Doctrine Migration (needs Wave 1 enums)

**Task 2: Seed migration for widget definitions + page layout**
- File: new migration `backend/migrations/Version*.php`
- Inserts: 5 `widget_definition` rows (one per new WidgetType)
- Inserts: 1 `page_layout` row (admin_dashboard, global)
- Inserts: 5 `page_layout_widget` rows (all widgets at `half` state, positions 1-5)
- → produces: database records for page layout resolution

### [parallel] Wave 3: Frontend Widgets + CollapsibleWidget (6 tasks)

**Task 3a: CollapsibleWidget component**
- File: `frontend/src/components/widgets/CollapsibleWidget.tsx`
- Props: `title`, `icon?`, `storageKey`, `defaultExpanded?`, `children`
- Behavior: click header toggles collapse, persist to localStorage, smooth animation
- → produces: reusable collapsible wrapper

**Task 3b: SystemHealthWidget**
- File: `frontend/src/widgets/SystemHealthWidget.tsx`
- Renders: 6 service status cards in a 2x3 grid (DB, Redis, Traccar, Mercure, OSRM, VROOM)
- Data: `health` + `live` from dashboard data
- → produces: health status widget

**Task 3c: InfrastructureMetricsWidget**
- File: `frontend/src/widgets/InfrastructureMetricsWidget.tsx`
- Renders: 3 metric cards (positions table, DB size, last ingestion)
- Data: `live.positions`, `live.disk`, `live.last_ingestion`
- → produces: infrastructure widget

**Task 3d: DashboardKpisWidget**
- File: `frontend/src/widgets/DashboardKpisWidget.tsx`
- Renders: 4 KPI cards (active routes, pending stops, CSV imports, positions/hour)
- Data: `metrics` from dashboard data
- → produces: KPI widget

**Task 3e: MiniReportsWidget**
- File: `frontend/src/widgets/MiniReportsWidget.tsx`
- Renders: simple bar chart (7-day deliveries) + top 5 drivers list
- Data: from `/api/admin/dashboard-reports`
- Chart: CSS-only bars (no Chart.js dependency)
- → produces: reports widget

**Task 3f: ActivityFeedWidget**
- File: `frontend/src/widgets/ActivityFeedWidget.tsx`
- Renders: live SSE position feed (vehicle name, coords, speed, time)
- Data: Mercure SSE subscription to vehicle position topics
- Uses: existing `/api/mercure-token` + `/api/vehicles` endpoints
- → produces: live activity widget

### Wave 4: Registry + Renderer Enhancement (needs Wave 3)

**Task 4: Update widget registry and WidgetRenderer**
- File: `frontend/src/widgets/registry.ts` — add 5 new entries with `collapsible: true`, titles
- File: `frontend/src/widgets/types.ts` — add `collapsible?`, `title?` to `WidgetRegistryEntry`
- File: `frontend/src/components/bottom-sheet/WidgetRenderer.tsx` — wrap collapsible widgets in CollapsibleWidget
- → produces: widgets renderable via widget system with collapse support

### Wave 5: Dashboard Page + Twig Integration (needs Wave 4)

**Task 5a: useDashboardData hook**
- File: `frontend/src/api/hooks/useDashboardData.ts`
- Fetches `/admin/health` every 30 seconds (react-query with refetchInterval)
- Returns: `{ health, live, metrics, isLoading, error }`
- → produces: data hook for dashboard widgets

**Task 5b: AdminDashboardPage React component**
- File: `frontend/src/pages/admin/AdminDashboardPage.tsx`
- Uses: `usePageLayout('admin_dashboard')`, `useDashboardData()`
- Renders: page header + WidgetRenderer (using new `mode='page'` for vertical layout)
- Includes: reports banner as static element
- → produces: working React dashboard page

**Task 5c: Dashboard widget entry point for Twig**
- File: `frontend/src/dashboard-widget.tsx` — standalone entry point (same pattern as sidebar-widget.tsx)
- File: `frontend/dashboard-widget.html` — Vite HTML entry
- File: `frontend/vite.config.ts` — add to rollupOptions.input
- → produces: `dashboard-widget.js` that mounts React in Twig

**Task 5d: Update Twig template**
- File: `backend/templates/admin/dashboard.html.twig` — replace content with React mount div
- Remove: all Alpine.js code, inline health cards, KPIs, reports, activity feed
- Keep: base.html.twig extension, breadcrumbs, page header
- Add: `<div id="mxo-dashboard-root"></div>` + `<script src="dashboard-widget.js">`
- → produces: Twig shell hosting React dashboard

### Wave 6: Route + Verification (needs Wave 5)

**Task 6: Router + final wiring**
- File: `frontend/src/router.tsx` — add admin/dashboard route (for SPA navigation)
- Verify: TypeScript clean, all widgets render, collapse/expand works, localStorage persists
- → produces: complete feature

## Phase 2 (Mature): Deferred

- Drag-and-drop widget reordering on dashboard
- Per-user widget visibility preferences (backend-persisted)
- Additional dashboard widgets (shipment status breakdown, exception alerts)
