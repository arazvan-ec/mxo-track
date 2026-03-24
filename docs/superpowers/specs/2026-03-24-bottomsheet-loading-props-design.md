# Design Spec — BottomSheet Loading Props

**Date:** 2026-03-24
**Type:** Enhancement
**Branch:** `claude/load-data-bottomsheet-0krK1`
**Bounded Context:** Pragmatic (UI/Frontend)

## Problema

Loading/error states are handled as full-screen early returns in most BottomSheet consumers, blocking the entire view (map + BottomSheet) while data loads. This should be a built-in BottomSheet capability so all consumers get it for free.

## Approach A: Add optional loading/error props to BottomSheet (selected)

**Ventaja:** Backward-compatible, zero breaking changes, every consumer opts in by passing props.
**Trade-off:** Minimal — adds 3 optional props to BottomSheet.

### Alternativas descartadas
- **Opcion B (Wrapper component):** Two components to maintain, unnecessary indirection.
- **Opcion C (Render prop):** Over-engineered, breaks existing consumers.

### BottomSheet new props
- `isLoading?: boolean` — shows spinner in content area
- `error?: Error | null` — shows error message in content area
- `loadingText?: string` — customizable loading message (default: "Loading...")

### Per-page changes

| Page | Has full-screen loading? | Action |
|------|--------------------------|--------|
| TestRoutingPage | Inline in BottomSheet (prev commit) | Pass `isLoading`, `error`, `loadingText` props; remove inline loading/error JSX |
| DriverRoutePage | Yes (lines 89-103) | Remove early returns, pass props |
| RouteDetailPage | Yes (lines 52-74) | Remove early returns, pass props |
| OperatorDashboardPage | Yes (lines 56-70) | Remove early returns, pass props |
| RouteAnalysisPage | Yes (lines 60-76) | Remove early returns, pass props |
| FleetMapPage | Yes (lines 112-126) | Remove early returns, pass props |
| CustomerRouteDetailPage | Yes (lines 66-88) | Remove early returns, pass props |
| ExceptionMapPage | Already inline | Pass props, remove manual inline loading |
| RoutePlannerPage | No loading states (wizard) | No change needed |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| BottomSheet props (state, onStateChange, title, children) | Include | Keep all existing props |
| BottomSheet content rendering | Transform | Add loading/error before children |
| TestRoutingPage inline loading (lines 203-217) | Transform | Replace with props |
| DriverRoutePage full-screen spinner (lines 89-103) | Transform | Move to BottomSheet props |
| RouteDetailPage full-screen spinner (lines 52-74) | Transform | Move to BottomSheet props |
| OperatorDashboardPage full-screen spinner (lines 56-70) | Transform | Move to BottomSheet props |
| RouteAnalysisPage full-screen spinner (lines 60-76) | Transform | Move to BottomSheet props |
| FleetMapPage full-screen spinner (lines 112-126) | Transform | Move to BottomSheet props |
| CustomerRouteDetailPage full-screen spinner (lines 66-88) | Transform | Move to BottomSheet props |
| ExceptionMapPage inline loading (lines 116-123) | Transform | Replace with props |
| RoutePlannerPage (no loading) | Include | No change |

## Omission Decisions

No omissions — all inventory items addressed.
