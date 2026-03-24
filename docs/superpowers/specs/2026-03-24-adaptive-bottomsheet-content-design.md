# Spec: Adaptive BottomSheet Content Zones

**Date:** 2026-03-24
**Type:** Enhancement
**Branch:** `claude/fix-map-centering-sheet-nbZRd`

---

## Problem

The BottomSheet renders ALL content regardless of available space. In collapsed state (15% viewport), panels like VehicleInfoPanel (~78px), RouteMetricsPanel (~176px), and Progress bar (~76px) consume all available space, leaving no room for delivery stops — the most actionable content.

On a typical mobile device (812px viewport), collapsed gives only ~57px of content space. Even on larger devices (1158px), it's ~109px — barely enough for 2 stop items.

## Solution: Responsive Content Zones (Approach A)

The BottomSheet exposes `contentHeight` to children. Each page uses this value to prioritize what to render, ensuring stops (the most useful content) are always visible.

### Key Changes

1. **Raise collapsed height: 15% → 20%** — gives meaningful extra space across all devices
2. **Expose `contentHeight` from `useBottomSheet`** — px available for content after handle
3. **New `RouteSummaryBar` component** — compact 24px single-line summary always visible
4. **`StopListPanel` gains `maxItems` prop** — limits visible stops in tight spaces
5. **4 pages adapt render** — conditionally show/hide panels based on contentHeight

### Space Budget Analysis

| Viewport | Collapsed (20%) content | Half (50%) content | Full (85%) content |
|----------|------------------------|--------------------|--------------------|
| 667px (iPhone SE) | 69px | 269px | 503px |
| 812px (iPhone X) | 98px | 342px | 626px |
| 932px (Android avg) | 122px | 402px | 728px |
| 1158px (large phone) | 167px | 515px | 920px |

### Content Priority Table

| Zone | Content | Min contentHeight | Est. height |
|------|---------|-------------------|-------------|
| Always | RouteSummaryBar | 0 (always) | ~24px |
| Always | StopListPanel (maxItems=2 when < 200px) | 0 (always) | ~112px (2 stops) |
| Medium | Progress bar (DriverRoutePage only) | ≥ 200px | ~76px |
| Medium | RouteMetricsPanel | ≥ 350px | ~176px |
| Large | VehicleInfoPanel | ≥ 450px | ~78px |

### RouteSummaryBar Design

Single-line compact summary (~24px): status badge + progress counter + distance + next ETA.

```
[🟢 IN_PROGRESS]  3/8 delivered  ·  12.4 km  ·  ETA 14:30
```

- `text-[11px]` for all text
- Horizontal flex with `gap-2`, items truncate if needed
- Props: `status`, `deliveredCount`, `totalCount`, `remainingDistance?`, `nextEta?`
- Used by all route-detail pages (admin, customer, driver)

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| SHEET_HEIGHTS.collapsed (0.15) | Transform → 0.20 | Core change: more space for content |
| SHEET_HEIGHTS.half (0.50) | Maintain | Adequate space |
| SHEET_HEIGHTS.full (0.85) | Maintain | Adequate space |
| Handle area (64px) | Maintain | Required for drag UX |
| BottomSheet.tsx structure | Maintain | No changes needed |
| useBottomSheet return values | Extend | Add contentHeight |
| StopListPanel props | Extend | Add maxItems |
| StopListPanel visual design | Maintain | No changes |
| RouteDetailPage BottomSheet children | Transform | Wrap in content zones |
| DriverRoutePage BottomSheet children | Transform | Wrap in content zones |
| CustomerRouteDetailPage BottomSheet children | Transform | Wrap in content zones |
| FleetMapPage BottomSheet children | Evaluate | FleetSidebar may benefit from maxItems pattern |
| OperatorDashboardPage | Maintain | Dashboard KPIs work differently |
| RoutePlannerPage | Maintain | Multi-step wizard, different pattern |
| TestRoutingPage | Maintain | Test controls, different pattern |
| ExceptionMapPage | Maintain | Exception list, evaluate later |
| RouteAnalysisPage | Maintain | Analysis panels, evaluate later |
| flyTo padding (recent fix) | Maintain | Already uses SHEET_HEIGHTS, will auto-adjust to 0.20 |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| FleetMapPage adaptive content | Omit for now | FleetSidebar has different structure (vehicle/route list), not stop-centric. Can be enhanced separately. |
| OperatorDashboardPage | Omit | KPI-focused, not stop-centric |
| RoutePlannerPage | Omit | Multi-step wizard with its own state management |
| TestRoutingPage | Omit | Developer tool, not user-facing |
| ExceptionMapPage | Omit | Exception-specific, different content pattern |
| RouteAnalysisPage | Omit | Analysis-specific panels |
| Animated transitions between zones | Omit | Adds complexity, sheet transition already handles visual smoothness |
| Dynamic height measurement (ResizeObserver) | Omit | Static estimates sufficient; ResizeObserver adds complexity for minimal gain |

## Files Affected

- `frontend/src/components/bottom-sheet/useBottomSheet.ts` — expose contentHeight, change collapsed to 0.20
- `frontend/src/components/panels/StopListPanel.tsx` — add maxItems prop
- `frontend/src/components/panels/RouteSummaryBar.tsx` — NEW component
- `frontend/src/pages/admin/RouteDetailPage.tsx` — adaptive content zones
- `frontend/src/pages/driver/DriverRoutePage.tsx` — adaptive content zones
- `frontend/src/pages/customer/CustomerRouteDetailPage.tsx` — adaptive content zones
