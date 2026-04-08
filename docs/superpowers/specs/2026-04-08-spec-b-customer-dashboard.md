# Spec B — Customer Dashboard Migration (Twig → React)

**Date:** 2026-04-08
**Type:** Feature (migration + new page)
**Branch:** `claude/innovative-dashboard-design-ddekk`
**Approach:** Full React page with widget system, reusing existing backend API endpoints

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `CustomerDashboardController.php` | **Include** | JSON endpoints `/kpis` and `/optimization-kpis` already exist |
| `FleetOverviewService.php` | **Include** | Provides customer KPIs, no changes needed |
| `CustomerOptimizationKpiService.php` | **Include** | Provides optimization metrics, no changes needed |
| `dashboard.html.twig` (customer) | **Include** | Reference for feature parity, not deleted |
| `PageKey` enum (backend) | **Transform** | Add `CUSTOMER_DASHBOARD` case |
| `PageKey` type (frontend) | **Transform** | Add `'customer_dashboard'` |
| `router.tsx` | **Transform** | Add customer dashboard route |
| Widget registry | **Transform** | Add 2 new customer widgets |
| `AdminDashboardPage.tsx` | **Include** | Pattern reference for Bento layout |
| `CustomerRouteDetailPage.tsx` | **Include** | Pattern reference for customer page structure |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Twig template deletion | **Omit** | Keep as fallback until React version is stable |
| Mercure SSE integration | **Omit** | Phase 2 — get static version working first |
| Active routes section | **Include** | Part of v0 — fetched from existing route API |

## Design

### New Files

1. **`frontend/src/pages/customer/CustomerDashboardPage.tsx`** — Main page component
   - Greeting header (reusing pattern from AdminDashboardPage)
   - Bento grid layout with KPI cards + optimization metrics
   - Active routes section with progress bars
   - Quick links section
   - ThemeSwitcher already in TopBar (global)

2. **`frontend/src/api/hooks/useCustomerDashboard.ts`** — Data fetching hook
   - Fetches `/customer/dashboard/kpis` + `/customer/dashboard/optimization-kpis` via TanStack Query
   - 30s refetch interval (matching Twig behavior)

3. **`frontend/src/widgets/CustomerKpisWidget.tsx`** — 5 KPI cards (collapsible)
   - total_shipments, active_routes, pending_deliveries, completed_today, exceptions
   - AnimatedCounter for values, theme-card styling

4. **`frontend/src/widgets/CustomerOptimizationWidget.tsx`** — Optimization value cards (collapsible)
   - monthly_km_saved, total_km_saved, time_saved, success_rate, avg_savings
   - SparklineSVG potential, theme-card styling

### Backend Changes

5. **`backend/src/Enum/PageKey.php`** — Add `case CUSTOMER_DASHBOARD = 'customer_dashboard'`

### Modified Files

6. **`frontend/src/types/layout.ts`** — Add `'customer_dashboard'` to PageKey union
7. **`frontend/src/router.tsx`** — Add `{ path: 'customer/dashboard', element: <CustomerDashboardPage /> }`
8. **`frontend/src/widgets/registry.ts`** — Register 2 new widgets

### Data Model

**Customer KPIs** (from `/customer/dashboard/kpis`):
```typescript
interface CustomerKpis {
  total_shipments: number;
  active_routes: number;
  pending_deliveries: number;
  completed_today: number;
  exceptions: number;
}
```

**Customer Optimization KPIs** (from `/customer/dashboard/optimization-kpis`):
```typescript
interface CustomerOptimizationKpis {
  monthly_km_saved: number;
  total_km_saved: number;
  monthly_time_saved_minutes: number;
  total_time_saved_minutes: number;
  avg_delivery_success_rate: number | null;
  avg_savings_percent: number | null;
  routes_with_metrics: number;
}
```

**Estimated:** ~8 files new/modified, ~400 lines new code
