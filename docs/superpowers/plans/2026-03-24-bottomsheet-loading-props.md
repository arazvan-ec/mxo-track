# Plan — BottomSheet Loading Props

**Date:** 2026-03-24
**Spec:** `docs/superpowers/specs/2026-03-24-bottomsheet-loading-props-design.md`

## Goal

Add `isLoading`, `error`, and `loadingText` props to BottomSheet component. Refactor all 8 consumer pages to use these props instead of full-screen loading spinners.

## Tasks

- [ ] Task 1: Add loading/error props to BottomSheet component
  - File: `frontend/src/components/bottom-sheet/BottomSheet.tsx`
  - Add optional props: `isLoading?: boolean`, `error?: Error | null`, `loadingText?: string`
  - Render loading spinner when `isLoading` (with `loadingText` or default "Loading...")
  - Render error message when `error`
  - Otherwise render `children`
  - Commit

- [ ] Task 2: Refactor TestRoutingPage
  - File: `frontend/src/pages/admin/TestRoutingPage.tsx`
  - Remove inline loading/error JSX from BottomSheet children (lines 203-217)
  - Pass `isLoading={isLoading}`, `error={error}`, `loadingText="Running route optimization..."`
  - Keep dynamic title logic
  - Commit

- [ ] Task 3: Refactor DriverRoutePage
  - File: `frontend/src/pages/driver/DriverRoutePage.tsx`
  - Remove full-screen early returns for isLoading/error
  - Pass `isLoading`, `error` to BottomSheet
  - Guard map layers with `data &&` / optional chaining
  - Commit

- [ ] Task 4: Refactor RouteDetailPage
  - File: `frontend/src/pages/admin/RouteDetailPage.tsx`
  - Same pattern as Task 3
  - Commit

- [ ] Task 5: Refactor OperatorDashboardPage
  - File: `frontend/src/pages/admin/OperatorDashboardPage.tsx`
  - Same pattern as Task 3
  - Commit

- [ ] Task 6: Refactor RouteAnalysisPage
  - File: `frontend/src/pages/admin/RouteAnalysisPage.tsx`
  - Same pattern as Task 3
  - Commit

- [ ] Task 7: Refactor FleetMapPage
  - File: `frontend/src/pages/admin/FleetMapPage.tsx`
  - Same pattern as Task 3
  - Commit

- [ ] Task 8: Refactor CustomerRouteDetailPage
  - File: `frontend/src/pages/customer/CustomerRouteDetailPage.tsx`
  - Same pattern as Task 3
  - Commit

- [ ] Task 9: Refactor ExceptionMapPage
  - File: `frontend/src/pages/admin/ExceptionMapPage.tsx`
  - Replace existing inline loading with props
  - Commit

- [ ] Task 10: Build verification
  - Run `npm run build`
  - Verify no errors

## Files Affected

| File | Change |
|------|--------|
| `frontend/src/components/bottom-sheet/BottomSheet.tsx` | Add loading/error props |
| `frontend/src/pages/admin/TestRoutingPage.tsx` | Use props instead of inline |
| `frontend/src/pages/driver/DriverRoutePage.tsx` | Remove full-screen, use props |
| `frontend/src/pages/admin/RouteDetailPage.tsx` | Remove full-screen, use props |
| `frontend/src/pages/admin/OperatorDashboardPage.tsx` | Remove full-screen, use props |
| `frontend/src/pages/admin/RouteAnalysisPage.tsx` | Remove full-screen, use props |
| `frontend/src/pages/admin/FleetMapPage.tsx` | Remove full-screen, use props |
| `frontend/src/pages/customer/CustomerRouteDetailPage.tsx` | Remove full-screen, use props |
| `frontend/src/pages/admin/ExceptionMapPage.tsx` | Replace inline loading with props |
