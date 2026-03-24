# Plan: Adaptive BottomSheet Content Zones

**Spec:** `docs/superpowers/specs/2026-03-24-adaptive-bottomsheet-content-design.md`
**Branch:** `claude/fix-map-centering-sheet-nbZRd`
**Goal:** Make BottomSheet content adapt to available space, prioritizing stops and a compact summary.

---

## Architecture

```
useBottomSheet.ts  ─── exposes contentHeight ───┐
                                                 │
RouteSummaryBar.tsx (NEW) ◄──────────────────────┤  (consumed by pages)
StopListPanel.tsx (maxItems) ◄───────────────────┤
                                                 │
RouteDetailPage.tsx ◄────────────────────────────┤
DriverRoutePage.tsx ◄────────────────────────────┤
CustomerRouteDetailPage.tsx ◄────────────────────┘
```

## Tasks — Designed for Parallel Execution

### Group A: Foundation (independent, parallelizable)

These 3 tasks have ZERO dependencies on each other. Each modifies a different file.

#### Task 1: useBottomSheet — expose contentHeight + raise collapsed
**File:** `frontend/src/components/bottom-sheet/useBottomSheet.ts`
**Changes:**
- Change `SHEET_HEIGHTS.collapsed` from `0.15` to `0.20`
- Add `contentHeight` to `UseBottomSheetReturn` interface: `contentHeight: number`
- Compute: `const contentHeight = Math.max(0, heightPx - 64);`
- Return `contentHeight` in the hook return object

**Acceptance Criteria:**
- [ ] `SHEET_HEIGHTS.collapsed` is `0.20`
- [ ] `contentHeight` is exported and equals `heightPx - 64` (min 0)
- [ ] No other behavior changes
- [ ] TypeScript compiles

#### Task 2: StopListPanel — add maxItems prop
**File:** `frontend/src/components/panels/StopListPanel.tsx`
**Changes:**
- Add `maxItems?: number` to Props interface
- After filtering `nonOriginStops`, apply: `const visibleStops = maxItems ? nonOriginStops.slice(0, maxItems) : nonOriginStops;`
- If truncated, show a subtle indicator: `+N more` at the bottom
- Use `visibleStops` in the map render

**Acceptance Criteria:**
- [ ] `maxItems` prop is optional, undefined = show all (backwards compatible)
- [ ] When set, only `maxItems` stops are shown
- [ ] When truncated, `+N more` text shown (text-[10px] text-slate-500 text-center py-1)
- [ ] TypeScript compiles

#### Task 3: RouteSummaryBar — new component
**File:** `frontend/src/components/panels/RouteSummaryBar.tsx` (NEW)
**Implementation:**

```tsx
interface RouteSummaryBarProps {
  status: string;
  deliveredCount: number;
  totalCount: number;
  remainingDistance?: string;
  nextEta?: string;
}

export function RouteSummaryBar({ status, deliveredCount, totalCount, remainingDistance, nextEta }: RouteSummaryBarProps) {
  return (
    <div className="flex items-center gap-2 text-[11px] overflow-hidden">
      <span className="font-medium uppercase text-slate-400 flex-shrink-0">
        {status}
      </span>
      <span className="text-slate-300 flex-shrink-0">
        {deliveredCount}/{totalCount}
      </span>
      {remainingDistance && (
        <>
          <span className="text-slate-600">·</span>
          <span className="text-slate-400 truncate">{remainingDistance}</span>
        </>
      )}
      {nextEta && (
        <>
          <span className="text-slate-600">·</span>
          <span className="text-blue-400 truncate">ETA {nextEta}</span>
        </>
      )}
    </div>
  );
}
```

**Acceptance Criteria:**
- [ ] Component renders in a single line (~24px height)
- [ ] All props except status/deliveredCount/totalCount are optional
- [ ] Truncates gracefully when horizontal space is tight
- [ ] TypeScript compiles

---

### Group B: Page Integration (depends on Group A, but each page is independent/parallelizable)

These 3 tasks depend on Group A being done, but are independent of each other.

#### Task 4: RouteDetailPage — adaptive content zones
**File:** `frontend/src/pages/admin/RouteDetailPage.tsx`
**Changes:**
- Import `contentHeight` from useBottomSheet (destructure from the hook or from BottomSheet state — it's already available via the hook)
- Import `RouteSummaryBar`
- Reorganize BottomSheet children:
  - Always: `<RouteSummaryBar>` with route status, delivered/total counts, distance, next ETA
  - Always: `<StopListPanel maxItems={contentHeight < 200 ? 2 : undefined}>`
  - `contentHeight >= 350`: show `<RouteMetricsPanel>`
  - `contentHeight >= 450`: show `<VehicleInfoPanel>`
- Remove the standalone status badge (RouteSummaryBar replaces it)

**Data extraction for RouteSummaryBar:**
- `status`: `route.status`
- `deliveredCount`: `route.stops.filter(s => s.status === 'DELIVERED').length`
- `totalCount`: `route.stops.filter(s => !s.isOrigin).length`
- `remainingDistance`: from metrics if available
- `nextEta`: first PENDING stop's etaTime

**Acceptance Criteria:**
- [ ] In collapsed: only RouteSummaryBar + 2 stops visible
- [ ] In half: RouteSummaryBar + RouteMetricsPanel + all stops
- [ ] In full: everything including VehicleInfoPanel
- [ ] Clicking stops still works (flyTo with padding)
- [ ] TypeScript compiles

#### Task 5: DriverRoutePage — adaptive content zones
**File:** `frontend/src/pages/driver/DriverRoutePage.tsx`
**Changes:**
- Get `contentHeight` from useBottomSheet
- Import `RouteSummaryBar`
- Reorganize BottomSheet children:
  - Always: `<RouteSummaryBar>` with status, progress, next ETA
  - Always: `<StopListPanel maxItems={contentHeight < 200 ? 2 : undefined}>`
  - `contentHeight >= 200`: show Progress bar section
- Remove standalone status + live indicator (RouteSummaryBar replaces it)

**Acceptance Criteria:**
- [ ] In collapsed: RouteSummaryBar + 2 stops
- [ ] In half/full: RouteSummaryBar + Progress bar + all stops
- [ ] Live indicator (SSE dot) preserved somewhere visible
- [ ] TypeScript compiles

#### Task 6: CustomerRouteDetailPage — adaptive content zones
**File:** `frontend/src/pages/customer/CustomerRouteDetailPage.tsx`
**Changes:**
- Get `contentHeight` from useBottomSheet
- Import `RouteSummaryBar`
- Reorganize BottomSheet children:
  - Always: `<RouteSummaryBar>` with status, progress
  - Always: `<StopListPanel maxItems={contentHeight < 200 ? 2 : undefined}>`
  - `contentHeight >= 350`: show any metrics/vehicle info present

**Acceptance Criteria:**
- [ ] In collapsed: RouteSummaryBar + 2 stops
- [ ] In half/full: all content visible
- [ ] TypeScript compiles

---

### Group C: Verification (depends on all above)

#### Task 7: TypeScript verification + commit
- Run `cd frontend && npx tsc --noEmit` — must pass
- Run `cd frontend && npx vite build` — must succeed
- Fix any type errors
- Final commit and push

**Acceptance Criteria:**
- [ ] `tsc --noEmit` passes
- [ ] `vite build` succeeds
- [ ] All changes committed and pushed

---

## Parallelization Map

```
Phase 1 (parallel):  Task 1  |  Task 2  |  Task 3
                        ↓         ↓         ↓
Phase 2 (parallel):  Task 4  |  Task 5  |  Task 6
                        ↓         ↓         ↓
Phase 3 (sequential): Task 7 (verify all)
```

## File Structure

```
frontend/src/
├── components/
│   ├── bottom-sheet/
│   │   └── useBottomSheet.ts          ← MODIFY (Task 1)
│   └── panels/
│       ├── StopListPanel.tsx          ← MODIFY (Task 2)
│       └── RouteSummaryBar.tsx        ← NEW (Task 3)
└── pages/
    ├── admin/
    │   └── RouteDetailPage.tsx        ← MODIFY (Task 4)
    ├── customer/
    │   └── CustomerRouteDetailPage.tsx ← MODIFY (Task 6)
    └── driver/
        └── DriverRoutePage.tsx        ← MODIFY (Task 5)
```
