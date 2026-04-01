# Plan: Shipment Details on Maps — Unified Map Interaction Architecture

**Spec:** `docs/superpowers/specs/2026-04-01-shipment-details-on-maps-design.md`
**Branch:** `claude/shipment-details-on-maps-s9JkL`

## Phase 1 (v0) — Working implementation with tests

### Task 1: Backend — Add `shipmentPublicId` to stop data pipeline

**Files:**
- `backend/src/Domain/Route/Model/StopMapView.php` — add `?string $shipmentPublicId`
- `backend/src/View/StopViewData.php` — add `?string $shipmentPublicId`
- `backend/src/View/RouteViewData.php` — pass shipmentPublicId in `fromMapView()`
- `backend/src/Domain/Route/Service/RouteMapProjection.php` — populate shipmentPublicId from RouteStop→Shipment
- `backend/tests/Unit/View/MapViewDataTest.php` — update tests

**TDD:** Write test that StopViewData.toArray() includes shipmentPublicId → fail → implement → pass.

**Commit after passing.**

### Task 2: Backend — Add `shipmentPublicId` + `routePublicId` to fleet stop data

**Files:**
- `backend/src/Application/Fleet/FleetOverviewService.php` — enrich stop data with shipmentPublicId + routePublicId from route projection
- `frontend/src/api/types.ts` — add `shipmentPublicId?` and `routePublicId?` to `FleetStop` + `StopData`

**Commit after updating types.**

### Task 3: Frontend — `useMapSelection` hook

**New file:** `frontend/src/hooks/useMapSelection.ts`

```typescript
type MapEntityType = 'stop' | 'vehicle';
interface MapSelection {
  type: MapEntityType;
  entityId: string;
  data: Record<string, unknown>;
}
interface UseMapSelectionReturn {
  selection: MapSelection | null;
  select: (type: MapEntityType, entityId: string, data: Record<string, unknown>) => void;
  clear: () => void;
  isSelected: (type: MapEntityType, entityId: string) => boolean;
}
```

**Commit after implementing.**

### Task 4: Frontend — `MapEntityPopup` (mini-popup on marker)

**New file:** `frontend/src/components/maps/shared/MapEntityPopup.tsx`

A small popup that appears over the marker when clicked:
- **Stop:** sequence badge + address (truncated) + status badge
- **Vehicle:** name + speed + route name (reuses existing VehiclePopup pattern)

For stops, enhance `StopMarker.tsx` to show a `Popup` (from react-map-gl) on click, similar to how `VehicleMarker` already does it.

**Files:**
- `frontend/src/components/maps/shared/StopMarker.tsx` — add popup with basic info
- `frontend/src/components/maps/shared/MapEntityPopup.tsx` — new shared popup component

**Commit after implementing.**

### Task 5: Frontend — `EntityActionPanel` component

**New file:** `frontend/src/components/panels/EntityActionPanel.tsx`

Renders inside BottomSheet when an entity is selected:
- **Stop selected:** full address, recipient name, phone, status, ETA, shipment reference
  - Actions: "Ver envío" (link to `/admin/shipments/{id}`), "Copiar dirección", "Ver POD"
- **Vehicle selected:** name, driver, speed, route
  - Actions: "Ver ruta" (link to `/app/admin/routes/{id}`), "Ver trail"

**Sub-files:**
- `frontend/src/components/panels/StopActionPanel.tsx`
- `frontend/src/components/panels/VehicleActionPanel.tsx`

Action visibility is controlled by `userRole` from `useMe()` hook (per spec matrix).

**Commit after implementing.**

### Task 6: Migrate `OperatorDashboardPage`

This is the page that triggered the issue — currently shows vehicles only, no stops.

**Changes:**
- Add route polylines + stop markers to the map (currently missing)
- Wire `useMapSelection` hook
- Add `EntityActionPanel` inside BottomSheet
- Stop click → select + fly + show panel
- Vehicle click → select + show panel

**File:** `frontend/src/pages/admin/OperatorDashboardPage.tsx`

**Commit after working.**

### Task 7: Migrate `FleetMapPage`

**Changes:**
- Replace ad-hoc `selectedStopSequence` + `handleStopClick` with `useMapSelection`
- Add `EntityActionPanel` inside BottomSheet
- Wire `FleetMap` stop/vehicle clicks to selection

**File:** `frontend/src/pages/admin/FleetMapPage.tsx`

**Commit after working.**

### Task 8: Migrate `RouteDetailPage`

**Changes:**
- Replace ad-hoc `selectedStopSequence` with `useMapSelection`
- Add `EntityActionPanel` inside BottomSheet
- `StopListPanel` click → same selection system

**File:** `frontend/src/pages/admin/RouteDetailPage.tsx`

**Commit after working.**

### Task 9: Migrate `CustomerRouteDetailPage`

**Changes:**
- Same as RouteDetailPage but with role-restricted actions (no trail, limited actions)

**File:** `frontend/src/pages/customer/CustomerRouteDetailPage.tsx`

**Commit after working.**

### Task 10: Migrate `DriverRoutePage`

**Changes:**
- Same pattern, driver-specific actions (call recipient, delivery focus)
- Preserve auto-track behavior for vehicle position

**File:** `frontend/src/pages/driver/DriverRoutePage.tsx`

**Commit after working.**

### Task 11: Migrate `TestRoutingPage`

**Changes:**
- Wire `useMapSelection` to existing stop click handlers
- Add `EntityActionPanel` inside widget-based BottomSheet

**File:** `frontend/src/pages/admin/TestRoutingPage.tsx`

**Commit after working.**

### Task 12: Verification

- `npx tsc --noEmit` — zero TypeScript errors
- `make lint` — zero PHP lint errors  
- `php vendor/bin/phpunit` — zero new failures
- Manual verification: all 6 pages load, stop click shows popup + panel, vehicle click shows popup + panel
- Navigation links work (Ver envío, Ver ruta)

## Phase 2 (Mature) — Refactoring

- Extract common map page layout pattern (TopBar + NavSidebar + MapCanvas + BottomSheet)
- Add Mercure-driven panel updates (stop status changes while panel is open)
- Add long-press/right-click context menu
- Add "Copiar dirección" clipboard integration
- Add "Ver POD" inline preview (currently just navigation)
