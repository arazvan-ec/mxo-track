---
type: bugfix
tags: []
files_touched: []
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

# Execution Log — 2026-03-24 — Fix Map Centering with BottomSheet

**Type:** bugfix
**Branch:** `claude/fix-map-centering-sheet-nbZRd`

---

### Phase: Root Cause Investigation
- **Symptom:** Tapping a delivery stop in the BottomSheet (half state) centers the map point behind the sheet, not in the visible area.
- **Root cause:** `MapCanvas.flyTo()` calls MapLibre's `flyTo({ center })` without a `padding` parameter. The map centers the point at the geometric center of the full canvas, ignoring that the BottomSheet covers up to 50% of the viewport from the bottom.
- **Past decisions consulted:** [2026-03-23] Bottom Sheet pattern — confirmed BottomSheet has 3 states (collapsed 15%, half 50%, full 85%) and that `fitBounds` already supports asymmetric padding but `flyTo` does not.

### Phase: Pattern-Wide Search
- **Pattern abstracted:** Any `flyTo()` call on a page that has a BottomSheet
- **Instances found:** 6 calls across 4 pages:
  - `RouteDetailPage.tsx:46` — stop click
  - `CustomerRouteDetailPage.tsx:34` — stop click
  - `DriverRoutePage.tsx:43` — vehicle auto-track
  - `DriverRoutePage.tsx:52` — stop click
  - `FleetMapPage.tsx:48` — stop click
  - `FleetMapPage.tsx:68` — vehicle click
- **All defective:** Yes, all 6 calls lacked bottom padding

### Phase: Implementation
- **Actual time:** ~15 minutes
- **Blockers hit:** none
- **Plan deviations:** none
- **Files changed (7):**
  - `useBottomSheet.ts` — exported `SHEET_HEIGHTS` constant
  - `MapCanvas.tsx` — added `padding` param to `flyTo` interface and implementation
  - `FleetMap.tsx` — forwarded `padding` param in wrapper
  - `RouteDetailPage.tsx` — compute and pass bottom padding
  - `CustomerRouteDetailPage.tsx` — compute and pass bottom padding
  - `DriverRoutePage.tsx` — compute and pass bottom padding (both flyTo calls)
  - `FleetMapPage.tsx` — compute and pass bottom padding (both flyTo calls)

### Phase: Verification
- **TypeScript build:** 0 errors (tsc --noEmit)
- **Vite build:** success (6.83s)
- **PHP tests:** 600 tests, 11 pre-existing failures (unrelated — smoke tests for Twig pages), 0 new failures
- **Lint:** clean

### Phase: Retrospective
- **What worked:** Pattern-wide search caught all 6 instances across 4 pages upfront, avoiding future bug reports for the same issue.
- **Lesson:** When adding UI overlay components (sheets, modals, panels) that cover map area, always propagate their dimensions to map centering/fitting operations at the component API level.
