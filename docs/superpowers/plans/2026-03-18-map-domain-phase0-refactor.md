# Plan: Fase 0 — Refactor shared map infrastructure

**Spec:** `docs/superpowers/specs/2026-03-18-map-domain-react-migration-design.md`
**Branch:** `claude/map-domain-reactive-routes-iXBRx`
**Objetivo:** Extraer componentes reutilizables del FleetMap actual para que las 9 vistas puedan componerlos.

## Tareas

### 0.1 Extraer MapCanvas de FleetMap
- [ ] Crear `components/maps/MapCanvas.tsx` con MapLibre wrapper, dark theme, forwardRef handle
- [ ] FleetMap pasa a ser composición de MapCanvas + layers
- [ ] Commit + push

### 0.2 Reorganizar layers
- [ ] Crear directorio `components/maps/layers/`
- [ ] Mover `VehicleTrailLayer.tsx` → `layers/VehicleTrailLayer.tsx`
- [ ] Mover `RouteSegments.tsx` → `layers/RouteSegmentsLayer.tsx`
- [ ] Crear `layers/VehicleLayer.tsx` (renderiza N VehicleMarkers con popups)
- [ ] Crear `layers/StopMarkersLayer.tsx` (renderiza N StopMarkers)
- [ ] Crear `layers/RoutePolylineLayer.tsx` (polyline encoded, reutiliza RouteLayer)
- [ ] Actualizar FleetMap para usar los nuevos layers
- [ ] Commit + push

### 0.3 Crear useMapSubscription unificado
- [ ] Crear `hooks/useMapSubscription.ts` que combina positions + route updates
- [ ] Refactor `useFleetMapData` para usar `useMapSubscription`
- [ ] Commit + push

### 0.4 Crear panels compartidos
- [ ] Crear `components/panels/StopListPanel.tsx` (lista de stops con status, click-to-select)
- [ ] Crear `components/panels/RouteMetricsPanel.tsx` (distance before/after, savings, timing)
- [ ] Crear `components/panels/VehicleInfoPanel.tsx` (detalles del vehículo)
- [ ] Commit + push

### 0.5 Verificación
- [ ] TypeScript compila sin errores
- [ ] FleetMapPage sigue funcionando con los componentes refactorizados
- [ ] Commit + push final
