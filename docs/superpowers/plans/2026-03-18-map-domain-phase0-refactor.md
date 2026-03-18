# Plan: Fase 0 — Refactor shared map infrastructure

**Spec:** `docs/superpowers/specs/2026-03-18-map-domain-react-migration-design.md`
**Branch:** `claude/map-domain-reactive-routes-iXBRx`
**Objetivo:** Extraer componentes reutilizables del FleetMap actual para que las 9 vistas puedan componerlos.

## Tareas

### 0.1 Extraer MapCanvas de FleetMap
- [x] Crear `components/maps/MapCanvas.tsx` con MapLibre wrapper, dark theme, forwardRef handle
- [x] FleetMap pasa a ser composición de MapCanvas + layers
- [x] Commit + push

### 0.2 Reorganizar layers
- [x] Crear directorio `components/maps/layers/`
- [x] Mover `VehicleTrailLayer.tsx` → `layers/VehicleTrailLayer.tsx`
- [x] Mover `RouteSegments.tsx` → `layers/RouteSegmentsLayer.tsx`
- [x] Crear `layers/VehicleLayer.tsx` (renderiza N VehicleMarkers con popups)
- [x] Crear `layers/StopMarkersLayer.tsx` (renderiza N StopMarkers)
- [x] Crear `layers/RoutePolylineLayer.tsx` (polyline encoded, reutiliza RouteLayer)
- [x] Actualizar FleetMap para usar los nuevos layers
- [x] Commit + push

### 0.3 Crear useMapSubscription unificado
- [x] Crear `hooks/useMapSubscription.ts` que combina positions + route updates
- [x] Refactor `useFleetMapData` para usar `useMapSubscription`
- [x] Commit + push

### 0.4 Crear panels compartidos
- [x] Crear `components/panels/StopListPanel.tsx` (lista de stops con status, click-to-select)
- [x] Crear `components/panels/RouteMetricsPanel.tsx` (distance before/after, savings, timing)
- [x] Crear `components/panels/VehicleInfoPanel.tsx` (detalles del vehículo)
- [x] Commit + push

### 0.5 Verificación
- [x] TypeScript compila sin errores
- [x] FleetMapPage sigue funcionando con los componentes refactorizados
- [x] Commit + push final
