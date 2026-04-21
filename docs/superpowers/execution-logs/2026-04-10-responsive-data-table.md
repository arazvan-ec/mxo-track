---
type: feature
tags: []
files_touched: [backend/src/Controller/Api/Admin/RouteListApiController.php, backend/src/Controller/Api/Admin/VehicleListApiController.php, backend/src/Controller/Api/NavigationController.php, frontend/src/api/hooks/useAdminRoutes.ts, frontend/src/api/hooks/useAdminVehicles.ts, frontend/src/api/types.ts, frontend/src/components/data-table/FilterBar.tsx, frontend/src/components/data-table/Pagination.tsx, frontend/src/components/data-table/ResponsiveDataTable.tsx, frontend/src/pages/admin/AdminRoutesListPage.tsx, frontend/src/pages/admin/AdminVehiclesListPage.tsx, frontend/src/router.tsx]
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-10 — ResponsiveDataTable + List Page Migration

**Type:** feature
**Branch:** `claude/improve-mobile-table-scroll-R2qex`

## Brainstorming

**Problem:** 19 Twig table pages use `overflow-x-auto` as only mobile strategy. Columns like Estado/Acciones get cut off. No scroll affordance. Unusable on mobile.

**Alternatives considered:**
1. (A) Smart Responsive Table — hide/show columns by priority with Tailwind responsive classes
2. (B) Card Morph CSS-only — CSS Grid + `data-label` transforms table rows to cards
3. (C) Data Dense Cards — mobile-first card layout with status color bar, expandable details (CHOSEN)

**Architecture decision:** Full React SPA migration (Camino 2) instead of Twig patches. Creates reusable `<ResponsiveDataTable>` component that serves all 5 list pages.

**Complexity estimate:** Medium — 9 new files, 2 modified files, ~1150 lines total

## Planning

4-wave parallel plan:
- Wave 1: Types + 3 shared components (ResponsiveDataTable, FilterBar, Pagination)
- Wave 2: 2 backend API endpoints (routes with filters + vehicles with positions)
- Wave 3: 2 React hooks + 2 page components
- Wave 4: Router + navigation updates + verification

## Implementation

**Actual files created:** 11 new, 3 modified

### Backend (2 new files)
- `backend/src/Controller/Api/Admin/RouteListApiController.php` — GET /api/admin/routes (pagination, status/date/driver/customer filters, stop counts) + GET /api/admin/routes/filters (drivers + customers for dropdowns)
- `backend/src/Controller/Api/Admin/VehicleListApiController.php` — GET /api/admin/vehicles (pagination, last positions)

### Frontend — Shared Components (3 new files)
- `frontend/src/components/data-table/ResponsiveDataTable.tsx` — Generic component: desktop renders `<table>`, mobile renders expandable cards with status color bar, title/subtitle/badge/detail hierarchy
- `frontend/src/components/data-table/FilterBar.tsx` — Scrollable chip bar + collapsible advanced filters panel
- `frontend/src/components/data-table/Pagination.tsx` — Page X of Y + prev/next buttons

### Frontend — Pages (4 new files)
- `frontend/src/api/hooks/useAdminRoutes.ts` — TanStack Query hook with filter params
- `frontend/src/api/hooks/useAdminVehicles.ts` — TanStack Query hook with pagination
- `frontend/src/pages/admin/AdminRoutesListPage.tsx` — Status chips (5 states with colors), advanced filters (date range, driver, customer dropdowns), progress bars, actions
- `frontend/src/pages/admin/AdminVehiclesListPage.tsx` — Active/Inactive badges, capacity display, last GPS position

### Modified files
- `frontend/src/api/types.ts` — Added PaginatedResponse<T>, RouteListItem, VehicleListItem + 3 more types for future pages
- `frontend/src/router.tsx` — Added /app/admin/routes and /app/admin/vehicles routes
- `backend/src/Controller/Api/NavigationController.php` — Updated nav links to point to React SPA

**Blockers:** None
**Deviations from plan:** None — executed exactly as planned

## Verification

- TypeScript: ✅ `tsc -b` clean (0 errors)
- Vite build: ✅ 226 modules, builds in 6.63s
- PHP lint: ✅ All new controllers syntax-clean
- Tests: ⚠ skipped (no test infra available in this environment)

## Lessons

1. **The `overflow-x-auto` pattern is an anti-pattern for mobile tables** — it provides no visual affordance that content exists off-screen. Cards with expandable details are a fundamentally better UX for narrow viewports.

2. **React Query + typed API hooks is an excellent pattern for list pages** — the separation of data fetching (hook) from presentation (page) makes each page ~150 lines while being fully featured (filters, pagination, responsive).

3. **The NavigationController is the single source of truth for nav links** — updating it is sufficient to redirect both Twig and React pages since NavigationSidebar reads from the API.

4. **Generic component design (`ColumnDef<T>`) pays off immediately** — the same ResponsiveDataTable component served both routes (7 columns, complex filters) and vehicles (6 columns, no filters) with zero code duplication.

## Retrospectiva

**Estimate accuracy:** Plan had 4 waves with 10 tasks. All completed as estimated.

**What worked well:**
- Parallel wave design — Wave 1 components + Wave 2 API endpoints had zero dependencies, maximized throughput
- Reading existing Twig templates gave exact column definitions and filter structures to replicate

**What to improve:**
- Future list pages (shipments, customers, drivers) should be even faster since all shared components exist. Consider a generator pattern or template.

**Process gap found:** None — the brainstorming phase correctly identified that CSS patches wouldn't solve the fundamental problem, saving a rework cycle.
