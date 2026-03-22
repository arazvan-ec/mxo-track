# Map Stop Selection Highlight — Design Spec

**Date:** 2026-03-22
**Status:** Approved
**Approach:** B — Visual highlight + bidirectional selection

## Problem

When a delivery stop is selected (from sidebar list or map marker click), the map marker shows no visual distinction. The user cannot tell which stop is selected on the map. This applies to all route detail pages (admin, customer, driver) and FleetMap.

## Alternatives Evaluated

### Approach A: Solo highlight visual en StopMarker (mínimo)
- Ventaja: Cambio mínimo (2 componentes)
- Desventaja: No incluye FleetMap, pierde oportunidad de unificar

### Approach B: Highlight + selección bidireccional (elegido)
- Ventaja: Cierra el gap completo, el flujo bidireccional ya existe en route detail pages
- Ventaja: FleetMap también gana selección individual de stops
- Trade-off: Toca 7 archivos pero todos son prop threading mecánico

### Approach C: Highlight + popup + selección bidireccional
- Desventaja: Over-engineering, popup no aporta valor claro sobre el sidebar que ya muestra la info

## Design

### 1. StopMarker highlight (shared component)

Add `isSelected` prop to `StopMarker`. When selected:
- Scale up: `w-8 h-8` (from `w-6 h-6`)
- Add ring/glow: `ring-2 ring-white ring-offset-2 ring-offset-slate-900`
- Higher z-index via Marker's `style={{ zIndex: isSelected ? 10 : 1 }}`
- Smooth transition on size change

### 2. StopMarkersLayer passes selection state

Add `selectedSequence` prop to `StopMarkersLayer`, forwarded to each `StopMarker` as `isSelected={stop.sequence === selectedSequence}`.

### 3. Route detail pages (admin, customer, driver)

Already pass `selectedStopSequence` state and `handleStopClick`. Just wire `selectedSequence` to `StopMarkersLayer`. Zero logic changes — only prop threading.

### 4. FleetMap stop selection

Add `selectedStopSequence` and `onStopClick` props to `FleetMap`. Wire stop markers with click handlers and selection state. `FleetMapPage` manages the state.

## Files affected

- `frontend/src/components/maps/shared/StopMarker.tsx` — add `isSelected` prop + visual
- `frontend/src/components/maps/layers/StopMarkersLayer.tsx` — add `selectedSequence` prop
- `frontend/src/pages/admin/RouteDetailPage.tsx` — pass `selectedSequence` to layer
- `frontend/src/pages/customer/CustomerRouteDetailPage.tsx` — pass `selectedSequence` to layer
- `frontend/src/pages/driver/DriverRoutePage.tsx` — pass `selectedSequence` to layer
- `frontend/src/components/maps/FleetMap.tsx` — add stop selection props + click handlers
- `frontend/src/pages/admin/FleetMapPage.tsx` — manage stop selection state
