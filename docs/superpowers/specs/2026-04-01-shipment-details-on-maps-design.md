# Spec: Shipment Details on Maps — Unified Map Interaction Architecture

**Date:** 2026-04-01
**Type:** Feature (enhancement + architecture)
**Branch:** `claude/shipment-details-on-maps-s9JkL`

## Problem

1. Stops on maps have no navigation to shipment detail in any view
2. Each of the 6 map pages manually wires click handlers ad-hoc (no reuse)
3. `RouteStop.getShipment()` exists in backend but `shipmentPublicId` is never exposed to frontend
4. No unified interaction pattern — vehicles have popups, stops have fly-to only
5. OperatorDashboard doesn't even show stops/polylines on the map

## Design

### Approach: `useMapSelection` hook + `EntityActionPanel` + mini-popup

**Three layers of interaction:**

1. **Mini-popup** — appears on marker hover/click with basic info (name, status, 1-line address). Lightweight, non-blocking.
2. **Selection state** — managed by `useMapSelection()` hook. Tracks `{type, entityId, data}`.
3. **EntityActionPanel** — renders inside BottomSheet when an entity is selected. Shows full details + action buttons.

### Data Pipeline Changes (Backend)

Add `shipmentPublicId` to stop data:

```
RouteStop.getShipment() → StopMapView.shipmentPublicId → StopViewData.shipmentPublicId → StopData.shipmentPublicId (TS)
```

Add `routePublicId` to fleet stop data (for navigation from OperatorDashboard/FleetMap):

```
FleetStop.routePublicId → enables "Ver ruta" navigation
```

### Frontend Architecture

```
useMapSelection()                    — shared hook, manages selected entity
├── type: 'stop' | 'vehicle'
├── entityId: string
├── data: StopData | VehicleData     — full entity data for panel rendering
└── actions: clear(), select()

EntityActionPanel                    — renders contextual actions
├── StopActionPanel                  — "Ver envío", "Copiar dirección", "Ver POD", recipient info
└── VehicleActionPanel               — "Ver ruta", "Ver trail", driver/speed info

MapEntityPopup                       — mini-popup on marker
├── StopPopup                        — sequence badge, address (1 line), status
└── VehiclePopup (existing)          — already exists, enrich with route link
```

### Interaction Flow

```
User clicks StopMarker on map
  → StopPopup appears (mini: #3 - Calle Example..., PENDING)
  → useMapSelection.select('stop', stopData)
  → BottomSheet scrolls to show EntityActionPanel
  → Panel shows: full address, recipient, phone, status, ETA
  → Action buttons: [Ver envío] [Copiar dirección] [Ver POD]
  → "Ver envío" navigates to /admin/shipments/{shipmentPublicId} (Twig page)
```

```
User clicks VehicleMarker on map
  → VehiclePopup appears (existing, enriched with "Ver ruta" link)
  → useMapSelection.select('vehicle', vehicleData)
  → Panel shows: vehicle name, driver, speed, route info
  → Action buttons: [Ver ruta] [Ver trail en mapa]
```

### Pages to Migrate (6 total)

| Page | Current State | After Migration |
|------|--------------|----------------|
| `OperatorDashboardPage` | VehicleLayer only, no stops | + route polylines + stop markers + selection + panel |
| `FleetMapPage` | FleetMap with ad-hoc handlers | useMapSelection + EntityActionPanel |
| `RouteDetailPage` | StopMarkersLayer + ad-hoc click | useMapSelection + EntityActionPanel |
| `CustomerRouteDetailPage` | Same as RouteDetail, limited | useMapSelection + EntityActionPanel (limited actions) |
| `DriverRoutePage` | Similar pattern | useMapSelection + EntityActionPanel (delivery actions) |
| `TestRoutingPage` | Widget-based | useMapSelection + EntityActionPanel |

### Stop Actions by Role

| Action | Admin | Customer | Driver |
|--------|-------|----------|--------|
| Ver envío (navigate) | ✓ | ✓ | ✗ |
| Copiar dirección | ✓ | ✓ | ✓ |
| Ver POD | ✓ | ✓ | ✗ |
| Ver destinatario | ✓ | ✓ | ✓ |
| Llamar destinatario | ✗ | ✗ | ✓ |

### Vehicle Actions by Role

| Action | Admin | Customer | Driver |
|--------|-------|----------|--------|
| Ver ruta (navigate) | ✓ | ✓ | ✗ |
| Ver trail | ✓ | ✗ | ✗ |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `StopMarker` | Transform | Add popup + selection callback |
| `VehicleMarker` | Transform | Enrich popup with route link |
| `StopListPanel` | Transform | Wire to selection system |
| `StopMarkersLayer` | Transform | Pass popup + selection props |
| `VehicleLayer` | Include | Already has popup support |
| `FleetMap` | Transform | Use useMapSelection |
| `BottomSheet` | Include | EntityActionPanel renders inside |
| `VehiclePopup` | Transform | Add route link |
| `StopViewData` | Transform | Add shipmentPublicId |
| `StopMapView` | Transform | Add shipmentPublicId |
| `FleetStop` type | Transform | Add shipmentPublicId + routePublicId |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `ExceptionMapPage` | Omit (this iteration) | Uses heatmap, not stop markers — different interaction model |
| `RouteAnalysisPage` | Omit (this iteration) | Analysis-focused, uses different data |
| `RoutePlannerPage` | Omit (this iteration) | Planning mode, not tracking mode |
| Long-press / right-click context menu | Omit (v0) | Click-select + panel is sufficient for v0 |

## Non-Goals

- Full context menu (right-click) — future iteration
- Shipment CRUD from map — out of scope
- Real-time panel updates via Mercure — future iteration
