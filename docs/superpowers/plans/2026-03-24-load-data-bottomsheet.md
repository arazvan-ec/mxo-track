# Plan — Load Data in BottomSheet

**Date:** 2026-03-24
**Spec:** `docs/superpowers/specs/2026-03-24-load-data-bottomsheet-design.md`
**File:** `frontend/src/pages/admin/TestRoutingPage.tsx`

## Goal

Move the loading/error states from full-screen renders into the BottomSheet component so the map is visible immediately.

## Tasks

- [ ] Task 1: Modify TestRoutingPage to render map+BottomSheet always
  - Remove early returns for `isLoading`, `error`, and `!data` (lines 80-103)
  - Change initial `sheetState` from `'collapsed'` to `'half'`
  - Compute dynamic title: `isLoading` → "Optimizing routes...", `error` → "Optimization Error", else → "Test Routing Results"
  - Make `initialCenter` conditional: `data?.origin ? { lat: data.origin.lat, lng: data.origin.lng } : undefined` (MapCanvas defaults to Madrid)
  - Move `origin`/`routesData` destructuring inside conditional (only when `data` exists)
  - Wrap map layers (polylines, markers) in `{data && ...}` guards
  - Wrap legend and fit-all button in `{data && ...}` guards
  - Replace BottomSheet children with: loading → spinner, error → error msg, data → WidgetRenderer
  - `sheetHeightPx` computation must handle no-data case (use sheetState directly)

- [ ] Task 2: Commit and push

## Files Affected

| File | Change |
|------|--------|
| `frontend/src/pages/admin/TestRoutingPage.tsx` | Main changes — restructure render logic |

## Verification

- `npm run build` passes (or equivalent frontend build)
- Visual: page loads showing map + BottomSheet with spinner, then transitions to results
