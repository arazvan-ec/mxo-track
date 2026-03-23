# Design Spec: Test Routing Bottom Sheet View

**Date:** 2026-03-23
**Status:** Approved
**Bounded context:** Pragmático (admin tooling / visualization)
**Branch:** `claude/improve-admin-routing-view-3cOqm`

---

## Summary

Replace the current sidebar-based TestRoutingPage layout with a Google Maps-style Bottom Sheet over a full-screen map. The Bottom Sheet has three drag states (collapsed/half/full) showing progressively more detail. Metrics use an innovative "Metric Pairs" pattern (3 logical groups of 2). Map and panel are fully synchronized: tap route card → map highlights/zooms; tap map marker → panel scrolls; state changes trigger fitBounds.

## Decisions from Brainstorming

| Question | Decision |
|----------|----------|
| Layout approach | Bottom Sheet over full-screen map (Google Maps style) |
| Bottom Sheet states | Collapsed (15%), Half (50%), Full (85%) |
| Map interaction | Interactive synchronized + auto-fit on state change |
| Metrics display | 6 metrics in 3 "Metric Pairs" with hero + delta badge |
| Original stop table | Eliminated — route cards only |
| Technology | React + TypeScript |
| Scope | MVP Complete — single phase |

---

## Existing Functionality Inventory

### TestRoutingPage.tsx (current)

| Element | Description |
|---------|-------------|
| `DualMenuShell` layout | Left sidebar (w-96) + full map |
| Loading spinner | Centered, dark bg, with text |
| Error display | Centered red text |
| Header | Title "Test Routing: VROOM + OSRM" + subtitle |
| MetricCard grid | 6 cards in 2-col grid (distance before/after, savings%, duration, routes, stops) |
| StopTable (original order) | Table with seq/recipient/address, red header, scrollable |
| RouteCard per route | Color dot + name + vehicle + stop count + Focus button + 4 metric cells + side-by-side stop comparison |
| MiniStopList | Compact stop list in RouteCard (before/after columns) |
| MapCanvas + layers | Origin-centered, zoom 12, polyline before (dashed red) + polylines after (colored) + stop markers |
| Fit all button | Top-right, fits all points |
| Legend | Bottom-left, shows original + route names with colors |
| RouteCard onFocus | Clicks Focus → map fitBounds to that route's stops |

### API (useTestRoutingData)

| Field | Available |
|-------|-----------|
| `metrics.distanceBeforeKm` | Yes |
| `metrics.distanceAfterKm` | Yes |
| `metrics.savedPercent` | Yes (distance only) |
| `metrics.totalDurationMinutes` | Yes (optimized total) |
| `metrics.stopCount` | Yes |
| `metrics.routeCount` | Yes |
| `metrics.durationBeforeMinutes` | **NO — needs backend addition** |
| `metrics.timeSavedPercent` | **NO — needs backend addition** |

### Reusable components

| Component | Reuse? |
|-----------|--------|
| `MapCanvas` | Yes — unchanged |
| `RoutePolylineLayer` | Yes — unchanged |
| `StopMarkersLayer` | Yes — unchanged |
| `ROUTE_COLORS` | Yes — unchanged |
| `DualMenuShell` | No — replaced by new MapWithBottomSheet layout |
| `TopBar` | Yes — kept above the map |
| `NavigationSidebar` | Yes — overlay mode unchanged |

---

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `DualMenuShell` layout | **Transform** | Replace with full-map + bottom sheet layout |
| Loading spinner | **Include** | Keep, adapt to full-screen |
| Error display | **Include** | Keep, adapt to full-screen |
| Header | **Transform** | Moves into Bottom Sheet handle bar |
| MetricCard grid (6 cards) | **Transform** | Replace with Metric Pairs pattern |
| StopTable (original order) | **Omit** | User decision — route cards show before/after comparison |
| RouteCard | **Transform** | Adapt for Bottom Sheet, add highlight interaction |
| MiniStopList | **Include** | Keep within RouteCard |
| MapCanvas + layers | **Include** | Same, but full-screen and auto-fitBounds on state change |
| Fit all button | **Include** | Keep, reposition if needed |
| Legend | **Include** | Keep, reposition above Bottom Sheet |
| RouteCard Focus button | **Transform** | Replace with tap-to-highlight + auto-zoom |
| `TopBar` | **Include** | Keep above map |

---

## Architecture

### New Component Tree

```
TestRoutingPage
├── TopBar (existing, via new shell)
├── NavigationSidebar (existing, overlay)
├── MapCanvas (full-screen, existing)
│   ├── RoutePolylineLayer (original, dashed red)
│   ├── RoutePolylineLayer[] (optimized, colored)
│   ├── StopMarkersLayer[] (per route)
│   ├── FitAllButton (existing)
│   └── RouteLegend (existing)
└── BottomSheet (NEW)
    ├── BottomSheetHandle (drag handle + title)
    ├── MetricPairs (NEW — collapsed view)
    ├── MetricPairsExpanded (NEW — half view, adds absolute savings)
    └── RouteCardList (NEW — full view)
        └── RouteCard (adapted, with highlight interaction)
```

### New Files

```
frontend/src/components/bottom-sheet/
├── BottomSheet.tsx           # Core Bottom Sheet with drag + 3 states
├── BottomSheetHandle.tsx     # Drag handle bar + title
└── useBottomSheet.ts         # Hook: state management, drag gesture, height calc

frontend/src/components/metrics/
└── MetricPairs.tsx           # 3-pair metric display (compact + expanded modes)

frontend/src/pages/admin/
└── TestRoutingPage.tsx       # Rewritten: full-map + bottom sheet
```

### State Management

```typescript
type BottomSheetState = 'collapsed' | 'half' | 'full';

// Shared state in TestRoutingPage:
const [sheetState, setSheetState] = useState<BottomSheetState>('collapsed');
const [highlightedRouteIdx, setHighlightedRouteIdx] = useState<number | null>(null);
const [highlightedStopKey, setHighlightedStopKey] = useState<string | null>(null);
```

### Map ↔ Bottom Sheet Synchronization

1. **Route highlight (panel → map):** Tap RouteCard → `setHighlightedRouteIdx(idx)` → RoutePolylineLayer gets `highlighted` prop (wider stroke, brighter color) + map fitBounds to route stops
2. **Marker tap (map → panel):** StopMarkersLayer onClick → `setHighlightedStopKey(key)` → BottomSheet auto-opens to half + scrolls to RouteCard containing that stop
3. **State change (sheet → map):** `useEffect` on `sheetState` → recalculate visible map area → `mapRef.current?.fitBounds()` with adjusted padding (bottom padding = sheet height)

### Bottom Sheet Heights

```typescript
const SHEET_HEIGHTS = {
  collapsed: 0.15,  // 15% viewport
  half: 0.50,       // 50% viewport
  full: 0.85,       // 85% viewport (TopBar stays visible)
} as const;
```

Drag gesture: Touch/mouse drag on handle. Snap to nearest state when released (velocity-aware: fast swipe overshoots to next state).

---

## Metric Pairs Design

### Layout (collapsed — single row)

```
┌─────────────────────────────────────────────────────┐
│  ≡  Test Routing Results                            │
│                                                     │
│  ┌───────────┐  ┌────────────────┐  ┌────────────┐ │
│  │ 2 rutas   │  │ 45.3 km        │  │ 1h 23m     │ │
│  │ 10 paradas │  │ ▼ 28.6% saved  │  │ ▼ 15.2%    │ │
│  └───────────┘  └────────────────┘  └────────────┘ │
└─────────────────────────────────────────────────────┘
```

### Layout (half — expanded with absolute values)

```
┌───────────────────────────────────────────────────────┐
│  ≡  Test Routing Results                              │
│                                                       │
│  ┌───────────┐  ┌──────────────────┐  ┌────────────┐ │
│  │ 2 rutas   │  │ 45.3 km          │  │ 1h 23m     │ │
│  │ 10 paradas │  │ ▼ 28.6% · -18 km │  │ ▼ 15.2%   │ │
│  └───────────┘  └──────────────────┘  └────────────┘ │
│                                                       │
│  ┌─ Route 1 ─────────────────────────────────────┐   │
│  │ ...                                            │   │
│  └────────────────────────────────────────────────┘   │
```

### Color Schema

| Pair | Hero color | Delta color |
|------|-----------|-------------|
| Scope (routes/stops) | `text-slate-200` | `text-slate-400` |
| Distance (km/saved%) | `text-blue-400` | `text-emerald-400` |
| Time (duration/saved%) | `text-purple-400` | `text-emerald-400` |

---

## Backend API Change

Add two fields to `metrics` in `TestRoutingMapDataController`:

```php
'metrics' => [
    // existing...
    'durationBeforeMinutes' => $durationBeforeMinutes,  // NEW
    'timeSavedPercent' => $timeSavedPercent,             // NEW
],
```

**Computation:** Before optimization, compute the total drive time for the single unoptimized route using OSRM's duration result (`$routeBefore->totalDurationMinutes`). The `timeSavedPercent = (before - after) / before * 100`.

Update `TestRoutingMetrics` TypeScript interface and `useTestRoutingData` accordingly.

---

## Interaction Details

### Drag Behavior

- **Touch/Mouse**: grab handle, drag up/down
- **Snap points**: release → animate to nearest state (with velocity: fast swipe overshoots)
- **Tap handle**: collapsed → half, half → full, full → collapsed (cycle)
- **Click outside** (on map): full → half, half stays

### Route Highlighting

- **Tap RouteCard**: highlighted route gets `opacity: 1, lineWidth: 5`; others get `opacity: 0.3, lineWidth: 2`
- **Tap same RouteCard again**: deselect, all routes back to normal
- **Tap map marker**: opens sheet to half (if collapsed), highlights parent route card, scrolls into view

### FitBounds on State Change

When `sheetState` changes, recalculate map padding:
```typescript
const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
mapRef.current?.fitBounds(allPoints, {
  padding: { top: 80, right: 80, bottom: bottomPadding + 20, left: 80 }
});
```

---

## CSS Transitions

```css
/* Bottom Sheet slide */
.bottom-sheet {
  transition: transform 300ms cubic-bezier(0.32, 0.72, 0, 1); /* iOS-like spring */
}

/* Route polyline highlight */
.route-highlight {
  transition: opacity 200ms ease, stroke-width 200ms ease;
}

/* Metric Pairs expand */
.metric-pairs {
  transition: all 250ms ease-out;
}
```

---

## Edge Cases

1. **No routes returned** — Show empty state message in Bottom Sheet
2. **Single route** — Metric pairs still work, "Routes: 1"
3. **OSRM unavailable** — Polylines missing, show warning badge in metrics
4. **Very small screen** — Collapsed state shows only handle + 1-line summary (metric pairs stack)
5. **Time before unavailable** — If OSRM doesn't return duration for unoptimized route, show "N/A" for time pair
