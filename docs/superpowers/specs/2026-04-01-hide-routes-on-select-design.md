# Spec — Ocultar rutas al seleccionar una

**Fecha:** 2026-04-01
**Tipo:** Enhancement (UI)
**Vista:** Operator Dashboard (`/app/admin/operator-dashboard`)

## Problema

Al seleccionar una ruta en el bottom sheet, todas las demás rutas siguen visibles en el mapa, generando ruido visual. El operador necesita enfocarse en la ruta seleccionada.

## Diseño

Cuando `expandedRouteId` tiene valor (ruta seleccionada), filtrar las capas del mapa para mostrar solo esa ruta. Al deseleccionar, volver a mostrar todas.

### Cambios

1. **Derivar `visibleRoutes`** — filtro sobre `activeRoutes` basado en `expandedRouteId`
2. **Usar `visibleRoutes`** en las capas del mapa (polylines + stop markers)
3. **Condicionar `fitBounds`** — solo al expandir, no al colapsar

### Inventario de funcionalidad existente

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| `expandedRouteId` state | Reusar | Ya trackea la ruta seleccionada |
| `activeRoutes` array | Transformar | Derivar `visibleRoutes` para el mapa |
| RoutePolylineLayer (L141-150) | Transformar | Usar `visibleRoutes` |
| StopMarkersLayer (L153-185) | Transformar | Usar `visibleRoutes` |
| VehicleLayer (L188-197) | Sin cambio | Vehículos independientes |
| Bottom sheet route list (L254-274) | Sin cambio | Debe mostrar todas para navegación |
| `allStopMarkers` memo (L72-90) | Sin cambio | Click handler necesita todos |

### Decisiones de omisión

| Elemento | Decisión | Justificación |
|----------|----------|---------------|
| Animación fade | Omitir | Over-engineering para este scope |
| Filtrar vehículos | Omitir | Son entidades independientes, siempre visibles |
