# Plan — Unify Menu Implementation

**Spec:** `specs/2026-04-07-unify-menu-implementation-design.md`
**Branch:** `claude/unify-menu-implementation-Yo8np`

## Phase 1 (v0): Working implementation

### Task 1 — Crear AppLayout with Context
- Crear `frontend/src/components/layout/AppLayout.tsx`
- Implements: AppLayoutProvider context, useAppLayout hook, AppLayout component
- TopBar + NavigationSidebar + Outlet, navOpen state managed internally
- Context exposes `setExtraControls` for pages that need TopBar extras
- Test: TypeScript compiles
- Commit after

### Task 2 — Modificar router with layout route
- Modificar `frontend/src/router.tsx`: wrap `/app` children with `<AppLayout />` element
- Test: TypeScript compiles
- Commit after

### [parallel] Task 3a-3k — Remove shell boilerplate from 11 pages

Each page: remove `navOpen` state, `NavigationSidebar` import/JSX, `TopBar` import/JSX, outer `<div className="flex flex-col h-screen w-full">` wrapper (AppLayout provides it). Keep only page content.

- **3a:** FleetMapPage.tsx
- **3b:** ExceptionMapPage.tsx
- **3c:** RouteDetailPage.tsx
- **3d:** TestRoutingPage.tsx
- **3e:** OperatorDashboardPage.tsx
- **3f:** RoutePlannerPage.tsx
- **3g:** WidgetGalleryPage.tsx
- **3h:** PageLayoutEditorPage.tsx
- **3i:** RouteAnalysisPage.tsx
- **3j:** CustomerRouteDetailPage.tsx
- **3k:** DriverRoutePage.tsx

Commit after all pages done (single commit, mechanical change).

### Task 4 — Delete DualMenuShell
- Delete `frontend/src/components/layout/DualMenuShell.tsx`
- Verify no imports remain
- Commit after

### Task 5 — Verify
- `npx tsc --noEmit` passes
- Build succeeds (`npm run build`)

## Phase 2 (Mature): N/A
This is a pure refactor — v0 IS the mature version.
