# MapLibre Full Leverage — Design Spec

**Fecha:** 2026-03-18
**Bounded Context:** MapView — pragmático (frontend React)
**Decisiones pasadas consultadas:** [2026-03-17] React SPA + MapView DDD — ya eligió MapLibre + PMTiles pero solo usa raster OSM.

## Problema

MapLibre GL JS v5 y PMTiles están instalados pero infrautilizados:
- Tiles raster de `tile.openstreetmap.org` (dependencia externa, sin dark theme, sin personalización)
- PMTiles registrado como protocolo pero nunca usado (dead code)
- Todos los markers son DOM elements via React `<Marker>` (no escala a 500+ puntos)
- Sin heatmaps, clustering nativo, ni geofences
- El mapa base se ve genérico — no refleja el dark theme de la UI

## Ejes de mejora

### Eje 1: Vector Tiles + Dark Theme Custom

**Objetivo:** Reemplazar raster OSM por vector tiles servidos via PMTiles, con un estilo dark theme que combine con la UI.

**Approach elegido:** Usar tiles de OpenFreeMap/Versatiles (gratuitos, sin API key) via PMTiles protocol ya registrado. Crear un `style.json` dark theme custom basado en el esquema OpenMapTiles.

**Alternativas descartadas:**
- (A) MapTiler/Stadia — requieren API key y tienen límites de uso
- (B) Self-hosted tile generation con planetiler — complejidad de infra excesiva para el beneficio
- (C) Protomaps CDN con PMTiles — buena opción pero menos control sobre el estilo

**Implementación:**
1. Descargar archivo PMTiles de España/Europa desde Protomaps (o usar CDN Protomaps como fallback)
2. Crear `dark-style.json` con colores que coincidan con slate-900/slate-800 de la UI
3. Modificar `MapCanvas.tsx` para usar el estilo vectorial
4. Eliminar las CSS overrides de controles (ya no necesarias con dark theme nativo)

**Decisión clave: CDN vs self-hosted PMTiles**

Para desarrollo y MVP: usar Protomaps CDN (`https://maps.protomaps.com/tiles/v4.json` con key gratuita o `pmtiles://` directo).
Para producción: evaluar self-hosted en Railway (un bucket S3 con el .pmtiles file).

Empezaremos con la variante **más simple y rápida**: usar el esquema de tiles gratuito de Protomaps con el tema `dark` (ya existe como JSON theme). Esto requiere:
- Instalar `protomaps-themes-base` (npm package con temas light/dark/grayscale listos)
- Cambiar `MAP_STYLE` en MapCanvas a usar `dark` theme de protomaps
- El PMTiles protocol ya está registrado — solo falta apuntar a una fuente de tiles

### Eje 2: Layers Nativos WebGL (reemplazar DOM markers)

**Objetivo:** Migrar de React `<Marker>` (DOM elements) a MapLibre native layers (WebGL) para mejor rendimiento y features avanzadas.

**Componentes a migrar:**

| Componente actual | Migración | Prioridad |
|---|---|---|
| `ShipmentMarkersLayer` | `circle` layer + clustering nativo | Alta — puede tener 500+ puntos |
| `ExceptionLayer` | `heatmap` layer + `circle` layer (toggle) | Alta — nueva funcionalidad |
| `VehicleLayer/VehicleMarker` | Mantener como `<Marker>` — son pocos (<50) y necesitan SVG custom + popup | Baja — no migrar |
| `StopMarkersLayer/StopMarker` | Mantener como `<Marker>` — necesitan números dentro | Baja — no migrar |
| `RoutePolylineLayer` | Ya es `Source`+`Layer` nativo | Ya hecho |
| `RouteSegmentsLayer` | Ya es `Source`+`Layer` nativo | Ya hecho |
| `VehicleTrailLayer` | Ya es `Source`+`Layer` nativo | Ya hecho |

**Shipments → Clustering nativo:**
- `Source` con GeoJSON de todos los shipments
- `Layer` tipo `circle` con `cluster: true`, `clusterMaxZoom: 14`, `clusterRadius: 50`
- Layer adicional `symbol` para mostrar count del cluster
- Color por cluster assignment via feature property `color`
- Click en cluster → zoom in, click en punto → callback

**Exceptions → Heatmap + puntos:**
- `Layer` tipo `heatmap` para densidad (zoom bajo)
- `Layer` tipo `circle` para puntos individuales (zoom alto, >12)
- Toggle en la UI para cambiar entre heatmap/puntos
- Popup on click via `queryRenderedFeatures`

### Eje 3: Estilo Visual Profesional

**Objetivo:** Que el mapa completo (no solo la UI) se vea profesional y oscuro.

**Se resuelve principalmente con Eje 1** (dark style). Adicionalmente:
- Labels en español (`text-field: ['get', 'name:es']` con fallback a `name`)
- Énfasis en carreteras (mayor contraste) y menos ruido en POIs/edificios
- Attribution personalizada (reemplazar "OpenStreetMap contributors" por algo más limpio)
- Colores de carreteras que resalten sobre el fondo oscuro (tonos blue/slate)

## Impacto en archivos

### Archivos nuevos
- `frontend/src/components/maps/styles/dark-style.ts` — estilo vectorial dark theme
- `frontend/src/components/maps/layers/ShipmentClusterLayer.tsx` — reemplazo de ShipmentMarkersLayer con clustering nativo
- `frontend/src/components/maps/layers/ExceptionHeatmapLayer.tsx` — heatmap + puntos toggle

### Archivos modificados
- `frontend/src/components/maps/MapCanvas.tsx` — cambiar MAP_STYLE a dark vector
- `frontend/src/pages/admin/RoutePlannerPage.tsx` — usar ShipmentClusterLayer
- `frontend/src/pages/admin/ExceptionMapPage.tsx` — usar ExceptionHeatmapLayer + toggle
- `frontend/src/index.css` — simplificar/eliminar overrides de controles MapLibre
- `frontend/package.json` — añadir `protomaps-themes-base`

### Archivos eliminados
- Ninguno (ShipmentMarkersLayer se mantiene por si alguna page lo usa con pocos datos)

## Riesgos

1. **CDN de tiles podría no estar disponible** → fallback a raster OSM como plan B en MapCanvas
2. **Dark theme demasiado oscuro para labels** → ajustar contraste en style
3. **Clustering nativo pierde la funcionalidad de click individual por shipment** → usar `queryRenderedFeatures` para mantenerla
4. **Heatmap con pocos datos se ve raro** → solo mostrar heatmap si >20 excepciones, sino puntos

## Complejidad estimada: M (Medium)

3 tareas principales independientes, cambios en ~10 archivos frontend.
