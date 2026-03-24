# Design Spec — Load Data in BottomSheet

**Date:** 2026-03-24
**Type:** Enhancement
**Branch:** `claude/load-data-bottomsheet-0krK1`
**Bounded Context:** Pragmatic (UI/Frontend)

## Problema

When loading `/app/admin/test-routing`, a full-screen spinner blocks the entire view (map + BottomSheet) while the API `/api/map/test-routing` responds. The user wants the map visible immediately with loading happening inside the BottomSheet.

## Approach A: Move loading/error states into BottomSheet (selected)

**Ventaja:** Minimal change, solves the exact problem. Map renders immediately.
**Desventaja:** None significant — default MapCanvas center (Madrid) is fine for demo.

### Trade-off vs Alternativas

- **Alternativa B (Skeleton widgets):** More polished but over-engineering for a demo page.
- **Alternativa C (Split API):** Requires backend changes, much more complex.
- **Opcion A is the simplest path** with the best UX improvement ratio.

### Changes

1. Remove early returns for `isLoading` and `error` states in `TestRoutingPage.tsx`
2. Render map immediately with MapCanvas default center (Madrid: 40.416, -3.703)
3. Show loading spinner inside BottomSheet content area
4. Show error inside BottomSheet content area
5. BottomSheet starts in `half` state so loading spinner is visible
6. Stays in `half` when data arrives so user sees results immediately
7. Dynamic title: loading → "Optimizing routes...", error → "Optimization Error", data → "Test Routing Results"
8. Map layers, legend, and fit-all button conditionally render only when `data` exists

## Existing Functionality Inventory

| Element | File:Lines | Decision | Justification |
|---------|-----------|----------|---------------|
| Full-screen loading spinner | TestRoutingPage.tsx:80-90 | Transform | Move into BottomSheet children |
| Full-screen error display | TestRoutingPage.tsx:92-101 | Transform | Move into BottomSheet children |
| Early return `if (!data)` | TestRoutingPage.tsx:103 | Omit | No longer needed; conditionals handle null data |
| Map with `origin` center | TestRoutingPage.tsx:122 | Transform | Use default center when no data, origin when available |
| BottomSheet with widgets | TestRoutingPage.tsx:211-221 | Transform | Show loading/error/widgets based on state |
| BottomSheet title | TestRoutingPage.tsx:214 | Transform | Dynamic based on loading state |
| Map legend overlay | TestRoutingPage.tsx:189-208 | Include | Conditionally render when data exists |
| Fit all button | TestRoutingPage.tsx:180-186 | Include | Conditionally render when data exists |
| Route polyline layers | TestRoutingPage.tsx:126-156 | Include | Already conditional on data |
| Stop markers layers | TestRoutingPage.tsx:158-176 | Include | Already conditional on data |
| `sheetState` initial 'collapsed' | TestRoutingPage.tsx:24 | Transform | Start 'half' so loading is visible |

## Omission Decisions

No omissions — all inventory items addressed.
