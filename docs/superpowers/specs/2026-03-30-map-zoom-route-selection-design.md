# Design Spec — Map Zoom on Route Selection

**Date:** 2026-03-30
**Type:** enhancement
**Bounded context:** Pragmático (UI/Frontend — React SPA)
**Complexity:** S

---

## Problem

In `TestRoutingPage`, clicking a route card calls `fitBounds` on the selected route's stops but without BottomSheet-aware padding. The map centers the route behind the sheet. Additionally, deselecting a route (clicking the same card again) doesn't zoom out to show all routes. The "Fit all" button also lacks BottomSheet padding.

## Approach

**Approach B: Extract `getSheetPadding()` helper** — a function that computes padding based on current `sheetState`, reused across all fitBounds call sites.

## Design

### Helper function

```ts
function getSheetPadding(sheetState: BottomSheetState): { top: number; right: number; bottom: number; left: number } {
  const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
  return { top: 80, right: 80, bottom: bottomPadding + 20, left: 80 };
}
```

### Changes to `handleRouteSelect`

- **Select route (`newIdx !== null`):** `fitBounds(routePoints, { padding: getSheetPadding(sheetState) })`
- **Deselect route (`newIdx === null`):** `fitBounds(allPoints, { padding: getSheetPadding(sheetState) })`

### Changes to `useEffect` (sheet state change)

- Replace inline padding calculation with `getSheetPadding(sheetState)`

### Changes to "Fit all" button

- Pass `{ padding: getSheetPadding(sheetState) }` to `fitBounds`

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `fitBounds` on sheetState change (L36-42) | Transform | Use helper instead of inline calc |
| `handleRouteSelect` fitBounds (L49-55) | Transform | Add padding via helper |
| Deselect behavior (click same route) | Transform | Add fitBounds to allPoints on deselect |
| "Fit all" button (L163-170) | Transform | Add padding via helper |
| Polyline opacity/width on highlight | Include | No changes needed |
| Stop marker click handler | Include | No changes needed |

## Omission Decisions

No omissions — all inventory items addressed.

## Files Affected

- `frontend/src/pages/admin/TestRoutingPage.tsx` (only file)
