# Spec — Unify Route Map Layers

**Fecha:** 2026-04-07
**Tipo:** Refactor (unificación)
**Enfoque elegido:** B — Componente `<RouteMapLayers>` compartido

## Problema

FleetMapPage y OperatorDashboardPage ensamblan las mismas capas de mapa (RoutePolylineLayer, StopMarkersLayer, toggle de flechas) de forma independiente. Cuando se añade un feature a una, se olvida en la otra.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| RoutePolylineLayer | Include (consumed by nuevo componente) | Capa base compartida |
| StopMarkersLayer | Include (consumed by nuevo componente) | Capa base compartida |
| showArrows state + toggle button | Include (dentro del nuevo componente) | Feature unificada |
| StopPopup renderizado | Include (callback del componente) | Idéntico en ambas páginas |
| Vehicle layers (VehicleMarker/VehicleLayer) | Omit | Distintos por contexto de rendimiento |
| Vehicle trail | Omit | Feature específica de FleetMap |
| Route filtering (visibleRoutes) | Omit | Cada página filtra antes de pasar routes al componente |
| Selection model | Omit | Distinto por propósito de cada página |

## Diseño

### Componente `<RouteMapLayers>`

**Ubicación:** `frontend/src/components/maps/layers/RouteMapLayers.tsx`

**Props:**
```typescript
interface RouteMapLayersProps {
  routes: FleetRoute[];
  onStopClick?: (routePublicId: string, sequence: number) => void;
  selectedRouteId?: string | null;
  selectedStopSequence?: number | null;
  /** Prefix for marker keys to avoid collisions */
  keyPrefix?: string;
  /** Selection object for stop highlighting by entityId match */
  selection?: { type: string; entityId: string; data: unknown } | null;
}
```

**Responsabilidades:**
1. Renderizar RoutePolylineLayer por cada ruta con polyline
2. Renderizar StopMarkersLayer por cada ruta
3. Gestionar estado `showArrows` internamente
4. Renderizar botón toggle ON/OFF de flechas

**Lo que NO hace:**
- No decide qué rutas mostrar (recibe `routes` ya filtradas)
- No gestiona vehículos
- No gestiona selección (recibe props para highlighting)

### Migración de páginas

**FleetMapPage:** FleetMap wrapper pasa a usar `<RouteMapLayers>`. Elimina su renderizado manual de polylines + stops.

**OperatorDashboardPage:** Reemplaza su renderizado manual de polylines + stops por `<RouteMapLayers>`. Elimina estado `showArrows` local.

### Stop selection highlighting

Cada página tiene un modelo de selección distinto. RouteMapLayers acepta dos modos:
- FleetMapPage: `selectedRouteId` + `selectedStopSequence`
- OperatorDashboardPage: `selection` object con `entityId.includes(routePublicId)`

El componente soporta ambos via props opcionales.
