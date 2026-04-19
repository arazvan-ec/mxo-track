# Map Components

**Última actualización:** 2026-04-19
**Estado:** Vigente

**Consultar antes de:** trabajar con `frontend/src/components/maps/`, añadir nuevas
capas al mapa, crear páginas con mapa + BottomSheet, integrar datos en tiempo real
desde Mercure al mapa, modificar markers/popups de vehículos o paradas.

## Library Choice

| Layer | Tech | Version | Notes |
|-------|------|---------|-------|
| Map library | MapLibre GL JS | `maplibre-gl` ^5.20.2 | Fork FOSS de Mapbox GL — sin token requerido |
| React wrapper | react-map-gl (MapLibre build) | `react-map-gl` ^8.1.0 | Import desde `react-map-gl/maplibre` |
| Tile source | OpenStreetMap raster | — | `https://tile.openstreetmap.org/{z}/{x}/{y}.png` |
| Polylines | Google-encoded polyline | — | Decodificado a GeoJSON en `shared/polyline.ts` |

**Protomaps (PMTiles)** aparece en `package.json` pero **no está activo** — el estilo actual
usa raster OSM (`styles/map-style.ts`). El comentario en `map-style.ts` indica que se migrará
cuando haya API key válida de Protomaps.

**Nunca importar `mapbox-gl`.** Todo el código usa `maplibre-gl` + `react-map-gl/maplibre`.

## Key Abstraction — MapCanvas

`MapCanvas` (`components/maps/MapCanvas.tsx`) es el wrapper único sobre `react-map-gl`. Todas
las páginas con mapa deben usarlo en vez de montar `<Map>` directamente. Razones:
- Inyecta estilo raster OSM con soporte dark-mode (vía `useTheme`).
- Expone API imperativa `MapCanvasHandle` vía `forwardRef`.
- Centraliza `NavigationControl`, `preserveDrawingBuffer`, y viewport inicial.

### MapCanvasHandle (API imperativa)

```ts
interface MapCanvasHandle {
  flyTo(lng: number, lat: number, zoom?: number, padding?: PaddingOptions): void;
  fitBounds(
    points: Array<{ lat: number; lng: number }>,
    options?: { padding?: number | { top; right; bottom; left } },
  ): void;
  getMapRef(): MapRef | null;  // Escape hatch para acceder al map nativo
}
```

- `flyTo`: animación 1000 ms, zoom default 15.
- `fitBounds`: construye `LngLatBounds` desde los puntos, padding default 80 px, duración 1000 ms.
- `getMapRef`: para casos avanzados (e.g. `map.queryRenderedFeatures`).

`FleetMap` (`components/maps/FleetMap.tsx`) compone `MapCanvas` + layers fleet-específicas
y re-exporta el handle. Es un wrapper de conveniencia — páginas fleet-like lo consumen;
otras páginas usan `MapCanvas` directamente y componen sus propias capas.

## Layers Inventory

Todas las capas viven en `components/maps/layers/`. Se exportan desde
`components/maps/layers/index.ts`.

| Layer | Props principales | Propósito |
|-------|-------------------|-----------|
| `VehicleLayer` | `vehicles: VehicleData[]`, `onVehicleClick`, `renderPopup` | N markers de vehículos genéricos — delega en `VehicleMarker` |
| `VehicleTrailLayer` | `coordinates: [lng,lat][]`, `showArrows` | Polyline azul del histórico GPS + flechas de dirección |
| `RoutePolylineLayer` | `id`, `polyline` (encoded), `color`, `dashed`, `showArrows`, `opacity`, `lineWidth` | Línea de ruta decodificada de polyline Google; flechas opcionales |
| `StopMarkersLayer` | `stops: StopData[]`, `onStopClick`, `routeColor`, `selectedSequence`, `renderPopup` | N markers numerados de paradas — PENDING colorea con `routeColor`, otros por status |
| `RouteMapLayers` | `routes: FleetRoute[]`, `selectedRouteId`, `selectedStopSequence`, `selection`, `keyPrefix` | Compuesto: polylines + stops + toggle de flechas ON/OFF (botón fijo top-left) |
| `ExceptionLayer` | `exceptions: ExceptionData[]` | Markers rojos `!` con tooltip hover y popup click |
| `ExceptionHeatmapLayer` | `exceptions`, `mode: 'heatmap' \| 'points'` | Heatmap WebGL nativo; fallback a points si `<20` excepciones |
| `ShipmentMarkersLayer` | `shipments`, `clusters`, `selectedShipmentIds`, `onShipmentClick` | Dots pequeños coloreados por cluster (React markers) |
| `ShipmentClusterLayer` | Igual que anterior | Clustering WebGL nativo MapLibre — usar para ≥500 puntos. Export `SHIPMENT_INTERACTIVE_LAYERS` para `interactiveLayerIds` |

### Shared Components (`components/maps/shared/`)

| File | Purpose |
|------|---------|
| `VehicleMarker.tsx` | Marker circular con icono camión, rotación por `course`, popup opcional |
| `StopMarker.tsx` | Círculo numerado (6px / 9px selected), color por status o `routeColor`, popup toggle |
| `OriginMarker.tsx` | Círculo verde "O" para el punto de origen de una ruta |
| `StopPopup.tsx` | Contenido estándar de popup de parada (secuencia, status badge, dirección, recipient, shipment ID) |
| `colors.ts` | `ROUTE_COLORS[6]`, `STOP_STATUS_COLORS` (PENDING/DELIVERED/EXCEPTION/SKIPPED con aliases minúsc), `SKILL_COLORS`, `getVehicleColor(v)` |
| `polyline.ts` | `decodePolyline(encoded)` + `polylineToGeoJSON(encoded)` — port del helper Twig |
| `directionArrows.ts` | `directionArrowsConfig(color)` — layout/paint compartido para capas symbol `▶` sobre polyline |
| `styles/map-style.ts` | `createMapStyle(theme)` — OSM raster con filtros brightness/saturate en dark mode |

## Real-Time Data Flow (SSE → Layers)

Los mapas consumen actualizaciones Mercure vía tres hooks en `frontend/src/api/hooks/`.
El hook unificado es **`useMapSubscription`** — los otros dos son hooks legacy/específicos.

| Hook | Topics suscritos | Retorna |
|------|------------------|---------|
| `useMapSubscription({ vehicleIds, routeIds, enabled })` | `/map/vehicles/{id}/position` + `/map/routes/{id}/updates` | `{ positions, routeUpdates, connected }` — preferido para mapas que mezclan vehículos + rutas |
| `useMercurePositions(vehicleIds)` | `/map/vehicles/{id}/position` | `Map<vehiclePublicId, VehiclePosition>` |
| `useMercureRouteUpdates(routeIds)` | `/map/routes/{id}/updates` | `Map<routePublicId, Partial<FleetRoute>>` — solo eventos `route_snapshot` merge stops; otros tipos (stop_delivered) dependen de refetch React Query |

Todos construyen topics sobre el hook bajo-nivel `useMercure(topics, handler, enabled)`.

### Data-loader hooks (wrapping pattern)

Los hooks de datos del mapa envuelven `useMapSubscription` + `useQuery` para entregar
datos enriquecidos con posición live:

- **`useFleetMapData()`** → GET `/api/fleet/map-data` (refetch 60 s) + SSE para todos los vehicleIds/routeIds devueltos. Merge `last_position` con live.
- **`useRouteMapData(publicId)`** → GET `/api/map/route/{publicId}` + SSE del vehicleId y routeId. Merge `vehiclePosition` live.
- **`useExceptionMapData()`**, **`useVehicleTrail(id)`** — carga sin SSE.

**Ver también:** `docs/knowledge/realtime.md` para topics Mercure, JWT y backend.

## Pages Using the Map

| Page | Maps usage | Notes |
|------|-----------|-------|
| `admin/FleetMapPage.tsx` | `FleetMap` (wrapper) + BottomSheet | Flota completa, fitBounds al cargar, flyTo al seleccionar vehículo/stop |
| `admin/RouteDetailPage.tsx` | `MapCanvas` + `StopMarkersLayer` + `RoutePolylineLayer` + `VehicleLayer` + `VehicleTrailLayer` | Detalle de una ruta admin |
| `admin/RoutePlannerPage.tsx` | `MapCanvas` + `ShipmentClusterLayer` + `RoutePolylineLayer` + `StopMarkersLayer` | Planner drag-to-plan |
| `admin/RouteAnalysisPage.tsx` | `MapCanvas` + stops + polyline | Comparación pre/post optimización |
| `admin/OperatorDashboardPage.tsx` | `MapCanvas` + `RouteMapLayers` + `VehicleLayer` | Dashboard operador en vivo |
| `admin/ExceptionMapPage.tsx` | `MapCanvas` + `ExceptionHeatmapLayer` | Heatmap/points toggle |
| `admin/TestRoutingPage.tsx` | `MapCanvas` + polyline + stops + BottomSheet | Sandbox de routing engine |
| `customer/CustomerRouteDetailPage.tsx` | `MapCanvas` + layers del cliente | Vista de ruta desde portal cliente |
| `driver/DriverRoutePage.tsx` | `MapCanvas` + BottomSheet | Vista conductor con POD |

## Common Patterns

### flyTo with bottom padding for BottomSheet

Cuando la página tiene un `BottomSheet` que cubre parte del viewport inferior, `flyTo`
debe recibir padding inferior equivalente al alto actual del sheet para que el punto
quede centrado en el área **visible** del mapa:

```tsx
import { SHEET_HEIGHTS } from '@/components/bottom-sheet/useBottomSheet';

const bottomPadding = window.innerHeight * SHEET_HEIGHTS[sheetState];
mapRef.current?.flyTo(stop.lng, stop.lat, 16, { bottom: bottomPadding });
```

`SHEET_HEIGHTS` expone los tres estados: `collapsed: 0.20`, `half: 0.50`, `full: 0.85`
(fracción de `window.innerHeight`). Este patrón se replica en todas las páginas con
BottomSheet — no reimplementar, importar la constante.

### fitBounds on initial load

El `fitBounds` debe envolverse en `setTimeout(..., 100-200ms)` para esperar a que
MapLibre termine el layout inicial. Sin el delay, el primer fit puede no tener
dimensiones de canvas válidas:

```tsx
useEffect(() => {
  if (points.length > 0) {
    setTimeout(() => mapRef.current?.fitBounds(points), 200);
  }
}, [deps]);
```

`FleetMap` usa 100 ms internamente en su auto-fit; páginas individuales suelen usar 200 ms.
Usar una ref `hasFittedRef` para evitar re-fit en cada update de datos live (solo fit-once
al cargar la ruta/flota inicial).

### Arrow toggle over polyline

Cuando una página muestra polylines de ruta, `RouteMapLayers` ya incluye un botón
`ON/OFF` en top-left (`absolute top-4 left-4 z-10`) para togglear las flechas de dirección.
No duplicar el toggle — componer `RouteMapLayers` en vez de usar `RoutePolylineLayer`
directamente si quieres el toggle gratis.

### Selection state across map + BottomSheet

Patrón `useMapSelection` (en `hooks/useMapSelection`): stop click → `selectStop()` →
actualiza selection context → BottomSheet muestra `EntityActionPanel` de esa parada. El
stop marker resalta cuando `selectedSequence` matchea (via `StopMarkersLayer.selectedSequence`
o, en fleet, vía `RouteMapLayers.selectedRouteId + selectedStopSequence`).

## Key Files Reference

| File | Rol |
|------|-----|
| `frontend/src/components/maps/MapCanvas.tsx` | Wrapper raíz — SIEMPRE entrar por aquí |
| `frontend/src/components/maps/FleetMap.tsx` | Composición fleet-específica (vehículos + rutas + trail) |
| `frontend/src/components/maps/layers/index.ts` | Barrel export de todas las capas |
| `frontend/src/components/maps/layers/RouteMapLayers.tsx` | Compuesto polylines + stops + toggle flechas |
| `frontend/src/components/maps/shared/colors.ts` | Paleta unificada (rutas/status/skills) |
| `frontend/src/components/maps/shared/polyline.ts` | Decoder polyline Google → GeoJSON |
| `frontend/src/components/maps/styles/map-style.ts` | Estilo OSM raster con dark-mode filter |
| `frontend/src/api/hooks/useMapSubscription.ts` | Hook SSE unificado (vehicles + routes) |
| `frontend/src/api/hooks/useFleetMapData.ts` | Loader flota + SSE merge |
| `frontend/src/api/hooks/useRouteMapData.ts` | Loader ruta detalle + SSE merge |
| `frontend/src/components/bottom-sheet/useBottomSheet.ts` | `SHEET_HEIGHTS` constante para padding |

## Gotchas

**Cleanup de eventos MapLibre nativos.** `ShipmentClusterLayer` registra un handler
`map.on('click', ...)` directamente sobre la instancia MapLibre (bypass de react-map-gl).
Devolver cleanup con `map.off('click', ...)` es OBLIGATORIO — si omites el cleanup, cada
re-mount acumula handlers y explota después de N navegaciones. Ver el patrón en
`ShipmentClusterLayer.tsx:103-123`.

**`interactiveLayerIds` para capas WebGL nativas.** Las capas construidas con `<Source>`
+ `<Layer>` (ShipmentCluster, ExceptionHeatmap) no reciben click events automáticamente.
Debes pasar sus layer IDs en el prop `interactiveLayerIds` de `MapCanvas` y manejar el
click vía `onClick`. Usa la constante `SHIPMENT_INTERACTIVE_LAYERS` exportada.

**Stacking con elementos fixed/absolute.** `MapCanvas` fija `width: 100%; height: 100%` y
no gestiona z-index. El `NavigationControl` vive dentro del canvas; botones custom sobre
el mapa (p.ej. toggle flechas en `RouteMapLayers`) usan `absolute top-4 left-4 z-10`.
El `BottomSheet` usa su propio stacking — cuando el sheet está `full`, puede cubrir
controles del mapa; compensa con el `bottom` padding en `flyTo` (ver patrón arriba) o
moviendo controles a `top-*`.

**`preserveDrawingBuffer: true`.** `MapCanvas` lo activa para permitir capturas/screenshots
del canvas. Tiene coste de perf menor; no desactivar salvo medición.

**Dark mode vía filtros raster, no tiles separadas.** `createMapStyle('dark')` aplica
`raster-brightness-max`, `raster-saturation`, `raster-contrast` sobre las mismas tiles
OSM. Si alguna vez migras a vector tiles (Protomaps), reemplazar la función entera en
vez de parchear los filtros.

**Color de stop `PENDING` vs status finales.** `StopMarker` colorea PENDING con el
`routeColor` (no con el color PENDING por defecto) para que las paradas pendientes
compartan identidad visual con su polyline. DELIVERED/EXCEPTION/SKIPPED mantienen el
color de status. Si añades un nuevo status, actualiza `STOP_STATUS_COLORS` con variantes
mayúsculas Y minúsculas (el código busca ambas como fallback).

**Keys de markers en maps múltiples.** Cuando una página renderiza varias rutas con
stops solapados en `RouteMapLayers`, los keys colisionan si no hay prefix. Pasar
`keyPrefix="fleet-"` o similar para aislar — ya está previsto en `RouteMapLayers` y
`StopMarkersLayer`.
