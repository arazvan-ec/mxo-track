# Plan — Unify Route Map Layers

**Spec:** `docs/superpowers/specs/2026-04-07-unify-route-map-layers-design.md`

## Phase 1 (v0)

### Tarea 1 — Crear `RouteMapLayers` component
- Crear `frontend/src/components/maps/layers/RouteMapLayers.tsx`
- Props: routes, onStopClick, selectedRouteId, selectedStopSequence, selection, keyPrefix
- Estado interno: `showArrows`
- Renderiza: RoutePolylineLayer + StopMarkersLayer + StopPopup + botón toggle
- TDD: verificar TypeScript compila

### Tarea 2 — Migrar FleetMap wrapper
- Modificar `frontend/src/components/maps/FleetMap.tsx`
- Reemplazar renderizado manual de RoutePolylineLayer + StopMarkersLayer por `<RouteMapLayers>`
- Eliminar prop `showArrows` de FleetMap (ahora interno en RouteMapLayers)
- Actualizar FleetMapPage para no pasar `showArrows` ni renderizar botón toggle
- TDD: verificar TypeScript compila

### Tarea 3 — Migrar OperatorDashboardPage
- Modificar `frontend/src/pages/admin/OperatorDashboardPage.tsx`
- Reemplazar renderizado manual de RoutePolylineLayer + StopMarkersLayer por `<RouteMapLayers>`
- Eliminar estado `showArrows` local y botón toggle
- TDD: verificar TypeScript compila

### Tarea 4 — Verificación final
- `npx tsc --noEmit` limpio
- Verificar que no quedan imports huérfanos
- Commit + push

## Archivos afectados
- `frontend/src/components/maps/layers/RouteMapLayers.tsx` (NUEVO)
- `frontend/src/components/maps/FleetMap.tsx` (MODIFICAR)
- `frontend/src/pages/admin/FleetMapPage.tsx` (MODIFICAR)
- `frontend/src/pages/admin/OperatorDashboardPage.tsx` (MODIFICAR)
