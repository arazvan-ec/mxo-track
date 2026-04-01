# Execution Log — 2026-04-01 — Shipment Details on Maps

**Type:** feature (architecture + enhancement)
**Branch:** `claude/shipment-details-on-maps-s9JkL`
**Spec:** `docs/superpowers/specs/2026-04-01-shipment-details-on-maps-design.md`
**Plan:** `docs/superpowers/plans/2026-04-01-shipment-details-on-maps.md`

## Brainstorming

- **Problem:** 6 map pages with ad-hoc click handling, no shipment navigation, OperatorDashboard missing stops entirely
- **Alternatives:** (A) useMapSelection hook + EntityActionPanel + mini-popup, (B) Enriched popups per marker, (C) MapPage base component
- **Chosen:** A + mini-popup — composable, scalable, uniform interactions across all views
- **User input:** Wanted all 3: uniform interactions + composable features + context menu. Chose click-select + panel trigger (not long-press)

## Planning

- 12 tasks: 2 backend, 10 frontend
- Affected files: 16 modified, 5 new
- Phase 1 only (v0) — Phase 2 deferred (context menu, Mercure panel updates)

## Implementation

- **Backend:** Added `shipmentPublicId` to `StopMapView`, `StopViewData`, `RouteViewData`, `RouteSnapshotManager.buildStopStates()`
- **Frontend core:** `useMapSelection` hook, `StopPopup`, `EntityActionPanel` (with `StopActionPanel` + `VehicleActionPanel`)
- **Migrated pages:** OperatorDashboardPage, FleetMapPage, RouteDetailPage, CustomerRouteDetailPage, DriverRoutePage, TestRoutingPage
- `StopMarkersLayer` extended with `renderPopup` prop
- `StopMarker` extended with `popupContent` (same pattern as `VehicleMarker`)
- `FleetMap` extended with `renderStopPopup` prop
- **OperatorDashboard specifically:** Added route polylines + stop markers + vehicle popups (was vehicles-only before)
- No blockers or deviations from plan

## Verification

- TypeScript: `npx tsc --noEmit` — zero errors
- PHP lint: zero errors on all changed files
- PHPUnit: 12/12 view tests pass (including 2 new shipmentPublicId tests)
- Pre-existing failures: 5 smoke tests (HTTP 500 — need database), 6 errors — all pre-existing, 0 new

## Retrospective

- The composable layer architecture (StopMarkersLayer, VehicleLayer) was already well-designed — just needed popup support added
- `RouteStop.getShipment()` existed but was never exposed in the view pipeline — classic "data exists but isn't surfaced" gap
- StopMarker popup follows VehicleMarker pattern exactly — consistent codebase patterns made this easy
- EntityActionPanel role-based actions work well: admin sees "Ver envio", driver sees "Llamar", customer sees limited set
- TestRoutingPage was the least impactful migration — its stops are optimization previews, not real shipments
