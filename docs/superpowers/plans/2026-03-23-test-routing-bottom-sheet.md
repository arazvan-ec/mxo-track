# Implementation Plan: Test Routing Bottom Sheet View

**Date:** 2026-03-23
**Spec:** `docs/superpowers/specs/2026-03-23-test-routing-bottom-sheet-design.md`
**Branch:** `claude/improve-admin-routing-view-3cOqm`
**Complexity:** L (new component pattern + page rewrite + backend API change)

---

## Goal

Replace TestRoutingPage's sidebar layout with a Google Maps-style Bottom Sheet over full-screen map, with Metric Pairs display and map↔panel synchronization.

## Architecture

- **Backend:** Add `durationBeforeMinutes` + `timeSavedPercent` to test routing API
- **Frontend:** New BottomSheet component, MetricPairs component, rewrite TestRoutingPage
- **Reuse:** MapCanvas, RoutePolylineLayer (enhanced), StopMarkersLayer, TopBar, NavigationSidebar

## Files Affected

### Backend (modify)
- `backend/src/Controller/Api/TestRoutingMapDataController.php` — add time metrics

### Frontend (new)
- `frontend/src/components/bottom-sheet/BottomSheet.tsx` — core component
- `frontend/src/components/bottom-sheet/useBottomSheet.ts` — drag/state hook
- `frontend/src/components/metrics/MetricPairs.tsx` — metric pairs display

### Frontend (modify)
- `frontend/src/api/hooks/useTestRoutingData.ts` — add new metric fields
- `frontend/src/components/maps/layers/RoutePolylineLayer.tsx` — add opacity/width props
- `frontend/src/pages/admin/TestRoutingPage.tsx` — complete rewrite

---

## Tasks

### Task 1: Backend — Add time metrics to API

**File:** `backend/src/Controller/Api/TestRoutingMapDataController.php`

Add computation of `durationBeforeMinutes` from the OSRM result for the unoptimized route:

```php
$durationBeforeMinutes = (int) round($routeBefore->totalDurationSeconds / 60);
$timeSavedPercent = $durationBeforeMinutes > 0
    ? round(($durationBeforeMinutes - $totalDurationMinutes) / $durationBeforeMinutes * 100, 1)
    : 0;
```

Add to the `metrics` array:
```php
'durationBeforeMinutes' => $durationBeforeMinutes,
'timeSavedPercent' => $timeSavedPercent,
```

**Verify:** `php -l` on file, no syntax errors.

- [ ] Complete

---

### Task 2: Frontend — Update TypeScript types

**File:** `frontend/src/api/hooks/useTestRoutingData.ts`

Add to `TestRoutingMetrics` interface:
```typescript
durationBeforeMinutes: number;
timeSavedPercent: number;
```

**Verify:** `npx tsc --noEmit` passes.

- [ ] Complete

---

### Task 3: Frontend — Add highlight props to RoutePolylineLayer

**File:** `frontend/src/components/maps/layers/RoutePolylineLayer.tsx`

Add optional props:
```typescript
interface Props {
  // ...existing
  opacity?: number;    // default: dashed ? 0.6 : 0.85
  lineWidth?: number;  // default: dashed ? 3 : 4
}
```

Use these in the `paint` object, falling back to current defaults.

**Verify:** Existing pages using this component still work (no breaking change — all new props optional).

- [ ] Complete

---

### Task 4: Frontend — Create useBottomSheet hook

**File:** `frontend/src/components/bottom-sheet/useBottomSheet.ts`

Hook providing:
```typescript
interface UseBottomSheetReturn {
  state: 'collapsed' | 'half' | 'full';
  setState: (s: BottomSheetState) => void;
  heightPx: number;           // computed from viewport
  heightPercent: number;       // 0.15 | 0.50 | 0.85
  handleProps: {               // spread on drag handle element
    onPointerDown: (e: React.PointerEvent) => void;
  };
  sheetStyle: React.CSSProperties;  // transform + transition
}
```

**Implementation:**
- Track drag Y position with `pointermove`/`pointerup` listeners (via `useEffect` cleanup)
- On release: calculate closest snap point, snap with CSS transition
- Velocity detection: fast swipe (>500px/s) overshoots to next state
- Tap on handle: cycle collapsed → half → full → collapsed
- Heights: `{ collapsed: 0.15, half: 0.50, full: 0.85 }`
- CSS transition: `transform 300ms cubic-bezier(0.32, 0.72, 0, 1)`

**Verify:** Hook compiles with `npx tsc --noEmit`.

- [ ] Complete

---

### Task 5: Frontend — Create BottomSheet component

**File:** `frontend/src/components/bottom-sheet/BottomSheet.tsx`

```typescript
interface BottomSheetProps {
  state: BottomSheetState;
  onStateChange: (s: BottomSheetState) => void;
  title: string;
  children: ReactNode;
}
```

**Structure:**
```
<div className="fixed bottom-0 left-0 right-0 z-40 bg-slate-900 rounded-t-2xl border-t border-slate-700">
  <div /* handle */ className="py-3 px-4 cursor-grab">
    <div /* handle bar */ className="w-10 h-1 bg-slate-600 rounded-full mx-auto mb-2" />
    <h2>{title}</h2>
  </div>
  <div /* scrollable content */ className="overflow-y-auto">
    {children}
  </div>
</div>
```

Uses `useBottomSheet` internally for drag behavior. Applies `sheetStyle` for positioning.

**Verify:** Component compiles.

- [ ] Complete

---

### Task 6: Frontend — Create MetricPairs component

**File:** `frontend/src/components/metrics/MetricPairs.tsx`

```typescript
interface MetricPairsProps {
  metrics: TestRoutingMetrics;
  expanded?: boolean;  // half+ state shows extra detail
}
```

**Renders 3 pairs in a row:**

1. **Scope pair:** `{routeCount} rutas` / `{stopCount} paradas` — slate colors
2. **Distance pair:** `{distanceAfterKm} km` / `▼ {savedPercent}%` — blue/emerald
3. **Time pair:** `{formatDuration(totalDurationMinutes)}` / `▼ {timeSavedPercent}%` — purple/emerald

When `expanded=true`, delta shows absolute too: `▼ 28.6% · -18.1 km`

**Helper:** `formatDuration(minutes)` → "1h 23m" or "45m"

**Verify:** Component compiles.

- [ ] Complete

---

### Task 7: Frontend — Rewrite TestRoutingPage

**File:** `frontend/src/pages/admin/TestRoutingPage.tsx`

Complete rewrite. Remove: DualMenuShell, MetricCard, StopTable. Keep: RouteCard (adapted), MiniStopList.

**New structure:**
```typescript
export function TestRoutingPage() {
  const { data, isLoading, error } = useTestRoutingData();
  const mapRef = useRef<MapCanvasHandle>(null);
  const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
  const [highlightedRouteIdx, setHighlightedRouteIdx] = useState<number | null>(null);

  // Loading/error states (full screen, same as before)

  // FitBounds on sheet state change
  useEffect(() => {
    const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
    mapRef.current?.fitBounds(allPoints, {
      padding: { top: 80, right: 80, bottom: bottomPadding + 20, left: 80 }
    });
  }, [sheetState]);

  return (
    <div className="flex flex-col h-screen">
      <TopBar compact onMenuClick={...} />
      <div className="flex-1 relative overflow-hidden">
        <MapCanvas ref={mapRef} ...>
          {/* Polylines with highlight props */}
          {/* Stop markers with onStopClick */}
        </MapCanvas>

        {/* Legend — positioned above bottom sheet */}
        <div className="absolute left-4" style={{ bottom: sheetHeightPx + 16 }}>
          ...legend...
        </div>

        {/* Fit all button */}
        <button className="absolute top-4 right-4">Fit all</button>

        {/* Bottom Sheet */}
        <BottomSheet state={sheetState} onStateChange={setSheetState} title="Test Routing Results">
          <MetricPairs metrics={data.metrics} expanded={sheetState !== 'collapsed'} />
          {sheetState !== 'collapsed' && (
            <div className="p-4 space-y-3">
              {routesData.map((route, idx) => (
                <RouteCard
                  route={route}
                  color={ROUTE_COLORS[idx]}
                  highlighted={highlightedRouteIdx === idx}
                  onSelect={() => handleRouteSelect(idx)}
                />
              ))}
            </div>
          )}
        </BottomSheet>
      </div>
    </div>
  );
}
```

**Route highlighting logic:**
```typescript
function handleRouteSelect(idx: number) {
  const newIdx = highlightedRouteIdx === idx ? null : idx;
  setHighlightedRouteIdx(newIdx);
  if (newIdx !== null) {
    const points = routesData[newIdx].stopsAfter.map(s => ({ lat: s.lat, lng: s.lng }));
    mapRef.current?.fitBounds(points);
  }
}
```

**Polyline rendering with highlight:**
```typescript
{routesData.map((route, idx) => (
  <RoutePolylineLayer
    key={route.name}
    id={`opt-${idx}`}
    polyline={route.polylineAfter}
    color={ROUTE_COLORS[idx]}
    opacity={highlightedRouteIdx === null ? 0.85 : highlightedRouteIdx === idx ? 1 : 0.3}
    lineWidth={highlightedRouteIdx === idx ? 6 : 4}
  />
))}
```

**Stop marker click → sheet opens:**
```typescript
function handleStopClick(routeIdx: number, sequence: number) {
  setHighlightedRouteIdx(routeIdx);
  if (sheetState === 'collapsed') setSheetState('half');
}
```

**RouteCard adaptation:**
- Add `highlighted?: boolean` and `onSelect?: () => void` props
- `highlighted` → ring border glow effect
- Remove `onFocus` / Focus button → replaced by `onSelect` tap on whole card header

**Verify:** `npx tsc --noEmit`, visual check in browser.

- [ ] Complete

---

### Task 8: Verify and clean up

- Run `npx tsc --noEmit` — no type errors
- Run `npx eslint src/` — no lint errors
- Visual test in browser: all 3 sheet states, drag gesture, metric pairs, route highlight, map sync
- Remove any dead code (old MetricCard if not used elsewhere)

- [ ] Complete

---

## Execution Order

```
Task 1 (backend API) ──┐
Task 2 (TS types)  ────┤──→ Task 6 (MetricPairs) ──┐
Task 3 (polyline)  ────┤                             ├──→ Task 7 (Page rewrite) → Task 8 (verify)
Task 4 (hook)      ────┤──→ Task 5 (BottomSheet) ──┘
```

Tasks 1-4 are independent and can be done in parallel. Tasks 5-6 depend on 4 and 2 respectively. Task 7 depends on all. Task 8 is final verification.
