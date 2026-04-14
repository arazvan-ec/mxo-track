# Plan: Expandable Routes Card

**Spec:** `docs/superpowers/specs/2026-04-14-expand-routes-card-design.md`

## Phase 1 (v0): Working expandable card

### Wave 1: Hook + Expandable Card Component

**Task 1: Create `useActiveRoutes` hook**
- File: `frontend/src/api/hooks/useActiveRoutes.ts`
- Fetches `/api/admin/routes?status=ACTIVE&limit=5` using react-query
- `enabled` flag so it only fetches when card is expanded
- Produces: hook returning `{ data, isLoading }`

**Task 2: Replace routes KpiCard with ExpandableRouteCard in DashboardKpisWidget**
- File: `frontend/src/widgets/DashboardKpisWidget.tsx`
- New `ExpandableRouteCard` component: shows KPI number + expand/collapse toggle
- When expanded: calls `useActiveRoutes`, shows compact route list below
- Each route row: name, driver, stops progress (delivered/total as bar)
- Animated expand/collapse transition matching existing `theme-card` style
- Other 3 KpiCards unchanged

### Verification
- `cd frontend && npm run build` must pass
- Visual: card expands on click, shows routes, collapses back
