# Plan — Map Zoom on Route Selection

**Date:** 2026-03-30
**Spec:** `docs/superpowers/specs/2026-03-30-map-zoom-route-selection-design.md`
**Phase:** Single-phase — v0 is already production-quality because it's a 1-file change extracting a simple helper with no abstractions needed.

## File

- `frontend/src/pages/admin/TestRoutingPage.tsx`

## Tasks

### Phase 1: v0 (single-phase)

- [ ] **Task 1:** Extract `getSheetPadding()` helper function
  - Add function above `TestRoutingPage` component
  - Replace inline padding calc in `useEffect` (L38-40) with helper call

- [ ] **Task 2:** Fix `handleRouteSelect` — add padding on select, zoom-out on deselect
  - Select: pass `{ padding: getSheetPadding(sheetState) }` to fitBounds
  - Deselect: call `fitBounds(allPoints, { padding: getSheetPadding(sheetState) })`
  - Add `sheetState` and `allPoints` to useCallback deps

- [ ] **Task 3:** Fix "Fit all" button — add padding
  - Pass `{ padding: getSheetPadding(sheetState) }` to fitBounds onClick

- [ ] **Task 4:** Verify TypeScript build
  - Run `cd frontend && npx tsc --noEmit`
  - Run `cd frontend && npx vite build`
