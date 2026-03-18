# Plan: MapLibre Full Leverage

**Spec:** `docs/superpowers/specs/2026-03-18-maplibre-full-leverage-design.md`
**Goal:** Aprovechar MapLibre al máximo: vector tiles dark theme, clustering nativo, heatmaps
**Tech Stack:** React 19, MapLibre GL JS 5.x, react-map-gl 8.x, PMTiles 4.x, @protomaps/basemaps
**Archivos afectados:** ~10 archivos frontend

---

## Eje 1: Vector Tiles + Dark Theme

### Task 1.1: Instalar @protomaps/basemaps y configurar dark style

**Goal:** Reemplazar raster OSM por vector tiles con dark theme nativo.

**Steps:**

1. Instalar dependencia:
```bash
cd frontend && npm install @protomaps/basemaps
```

2. Crear archivo de estilo dark:

**File:** `frontend/src/components/maps/styles/dark-style.ts`
```typescript
import { layersWithCustomTheme, namedFlavor } from '@protomaps/basemaps';
import type { StyleSpecification } from 'maplibre-gl';

// Protomaps CDN tile source — gratuito para desarrollo
const TILE_URL = 'https://maps.protomaps.com/tiles/v4/{z}/{x}/{y}.mvt';

/**
 * Dark vector tile style for MapLibre.
 * Uses Protomaps basemap with dark flavor, customized for logistics UI.
 */
export function createDarkStyle(): StyleSpecification {
  // Start with the dark flavor and customize for our slate-900 UI
  const flavor = {
    ...namedFlavor('dark'),
    // Override to match our slate-900/slate-800 palette
    background: '#0f172a',      // slate-900
    earth: '#0f172a',           // slate-900
    water: '#1e293b',           // slate-800
    // Emphasize roads (our core business is logistics)
    majorRoad: '#334155',       // slate-700
    mediumRoad: '#1e293b',      // slate-800
    minorRoad: '#1e293b',       // slate-800
    highway: '#475569',         // slate-600
  };

  const layers = layersWithCustomTheme('protomaps', flavor, 'es');

  return {
    version: 8,
    glyphs: 'https://cdn.protomaps.com/fonts/pbf/{fontstack}/{range}.pbf',
    sources: {
      protomaps: {
        type: 'vector',
        tiles: [TILE_URL],
        maxzoom: 15,
        attribution: '© <a href="https://protomaps.com">Protomaps</a> © <a href="https://openstreetmap.org">OpenStreetMap</a>',
      },
    },
    layers,
  };
}

// Fallback raster style (in case vector tiles are unavailable)
export const FALLBACK_RASTER_STYLE: StyleSpecification = {
  version: 8,
  sources: {
    osm: {
      type: 'raster',
      tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
      tileSize: 256,
      attribution: '© OpenStreetMap contributors',
    },
  },
  layers: [{ id: 'osm', type: 'raster', source: 'osm' }],
};
```

3. Modificar `MapCanvas.tsx` para usar el dark style:

**File:** `frontend/src/components/maps/MapCanvas.tsx`

Replace the raster style constant and import:
```typescript
import { createDarkStyle, FALLBACK_RASTER_STYLE } from './styles/dark-style';

// Lazy-init the dark style (called once)
let _darkStyle: StyleSpecification | null = null;
function getDarkStyle(): StyleSpecification {
  if (!_darkStyle) _darkStyle = createDarkStyle();
  return _darkStyle;
}
```

Replace `mapStyle={MAP_STYLE}` with `mapStyle={getDarkStyle()}`.

Remove the old `MAP_STYLE` constant entirely.

4. Simplificar CSS overrides en `index.css`:

The dark theme makes most control overrides unnecessary. Keep only popup styling (which is for our custom popups, not map controls). Remove or simplify the zoom control overrides since vector tiles with dark theme will have matching controls.

5. **Verify:** Run `npm run build` — should compile without errors. Open Fleet Map — should show dark vector map.

6. **Commit:** `feat: replace raster OSM with dark vector tiles via Protomaps`

- [ ] Task 1.1 complete

---

### Task 1.2: Labels en español y ajuste de carreteras

**Goal:** Que los labels del mapa salgan en español y las carreteras destaquen.

**Steps:**

1. En `dark-style.ts`, el tercer argumento de `layersWithCustomTheme` ya es `'es'` para labels en español. Verificar que funciona.

2. Si los labels no salen en español (porque Protomaps usa `name:es` solo donde existe), añadir fallback:
   - Los layers de tipo `symbol` con `text-field` deben usar: `['coalesce', ['get', 'name:es'], ['get', 'name']]`
   - Esto se puede hacer post-procesando el array de layers antes de asignarlo al style.

3. Ajustar contraste de carreteras si es necesario — verificar visualmente que las carreteras principales (highway, trunk) se ven claramente sobre el fondo slate-900.

4. **Verify:** Navegar el mapa y verificar labels en español en ciudades españolas.

5. **Commit:** `feat: configure Spanish labels and road emphasis in dark style`

- [ ] Task 1.2 complete

---

## Eje 2: Layers Nativos WebGL

### Task 2.1: ShipmentClusterLayer — clustering nativo para shipments

**Goal:** Reemplazar ShipmentMarkersLayer (DOM markers) con clustering nativo de MapLibre para mejor rendimiento con 500+ shipments.

**Steps:**

1. Crear nuevo componente:

**File:** `frontend/src/components/maps/layers/ShipmentClusterLayer.tsx`
```typescript
import { Source, Layer, useMap } from 'react-map-gl/maplibre';
import { useCallback, useMemo } from 'react';
import { ROUTE_COLORS } from '../shared/colors';
import type { PlannerShipment, PlannerCluster } from '@/api/types';

const UNASSIGNED_COLOR = '#6B7280';
const SOURCE_ID = 'shipments-source';

interface Props {
  shipments: PlannerShipment[];
  clusters?: PlannerCluster[];
  selectedShipmentIds?: Set<string>;
  onShipmentClick?: (publicId: string) => void;
}

export function ShipmentClusterLayer({
  shipments,
  clusters = [],
  selectedShipmentIds,
  onShipmentClick,
}: Props) {
  const { current: map } = useMap();

  // Build color lookup: shipmentId -> color
  const colorMap = useMemo(() => {
    const m = new Map<string, string>();
    clusters.forEach((cluster, idx) => {
      const color = cluster.color || ROUTE_COLORS[idx % ROUTE_COLORS.length];
      cluster.shipmentIds.forEach((id) => m.set(id, color));
    });
    return m;
  }, [clusters]);

  // Convert shipments to GeoJSON FeatureCollection
  const geojson = useMemo(() => ({
    type: 'FeatureCollection' as const,
    features: shipments
      .filter((s) => s.lat && s.lng)
      .map((s) => ({
        type: 'Feature' as const,
        geometry: {
          type: 'Point' as const,
          coordinates: [s.lng, s.lat],
        },
        properties: {
          publicId: s.publicId,
          color: colorMap.get(s.publicId) ?? UNASSIGNED_COLOR,
          name: s.recipientName,
          address: s.address,
          selected: !selectedShipmentIds || selectedShipmentIds.has(s.publicId) ? 1 : 0,
        },
      })),
  }), [shipments, colorMap, selectedShipmentIds]);

  // Handle click on unclustered point
  const handleClick = useCallback((e: maplibregl.MapLayerMouseEvent) => {
    const feature = e.features?.[0];
    if (!feature) return;

    // If it's a cluster, zoom into it
    if (feature.properties?.cluster) {
      const clusterId = feature.properties.cluster_id;
      const source = map?.getMap().getSource(SOURCE_ID) as maplibregl.GeoJSONSource;
      source?.getClusterExpansionZoom(clusterId).then((zoom) => {
        map?.flyTo({
          center: (feature.geometry as GeoJSON.Point).coordinates as [number, number],
          zoom,
        });
      });
      return;
    }

    // Individual point click
    const publicId = feature.properties?.publicId;
    if (publicId && onShipmentClick) {
      onShipmentClick(publicId);
    }
  }, [map, onShipmentClick]);

  // Register click handlers
  // Note: react-map-gl <Layer> supports interactiveLayerIds on the <Map> component
  // For simplicity, we'll use onClick on the Layer via the map's onClick

  return (
    <Source
      id={SOURCE_ID}
      type="geojson"
      data={geojson}
      cluster={true}
      clusterMaxZoom={14}
      clusterRadius={50}
    >
      {/* Cluster circles */}
      <Layer
        id="shipment-clusters"
        type="circle"
        filter={['has', 'point_count']}
        paint={{
          'circle-color': [
            'step',
            ['get', 'point_count'],
            '#3B82F6',  // blue-500 for small clusters
            20, '#8B5CF6',  // violet-500 for medium
            50, '#EF4444',  // red-500 for large
          ],
          'circle-radius': [
            'step',
            ['get', 'point_count'],
            15,   // small
            20, 20,  // medium
            50, 25,  // large
          ],
          'circle-stroke-width': 2,
          'circle-stroke-color': 'rgba(255,255,255,0.3)',
        }}
      />

      {/* Cluster count label */}
      <Layer
        id="shipment-cluster-count"
        type="symbol"
        filter={['has', 'point_count']}
        layout={{
          'text-field': '{point_count_abbreviated}',
          'text-size': 12,
          'text-font': ['Noto Sans Regular'],
        }}
        paint={{
          'text-color': '#ffffff',
        }}
      />

      {/* Individual unclustered points */}
      <Layer
        id="shipment-unclustered"
        type="circle"
        filter={['!', ['has', 'point_count']]}
        paint={{
          'circle-color': ['get', 'color'],
          'circle-radius': [
            'case',
            ['==', ['get', 'selected'], 1], 6,
            4,
          ],
          'circle-stroke-width': 1.5,
          'circle-stroke-color': 'rgba(255,255,255,0.6)',
          'circle-opacity': [
            'case',
            ['==', ['get', 'selected'], 1], 1,
            0.4,
          ],
        }}
      />
    </Source>
  );
}
```

2. Registrar interactiveLayerIds en las pages que usan este layer.

En `RoutePlannerPage.tsx`, donde se renderiza `<MapCanvas>`, necesitamos pasar los IDs de layers interactivos al Map. Esto requiere que MapCanvas acepte una prop `interactiveLayerIds` y un `onClick` handler.

**Modificar MapCanvas.tsx** — añadir props opcionales:
```typescript
interface Props {
  children?: ReactNode;
  initialCenter?: { lat: number; lng: number };
  initialZoom?: number;
  showControls?: boolean;
  interactiveLayerIds?: string[];
  onClick?: (e: maplibregl.MapLayerMouseEvent) => void;
}
```

Y pasarlas al `<Map>` component:
```tsx
<Map
  ...
  interactiveLayerIds={interactiveLayerIds}
  onClick={onClick}
>
```

3. En `RoutePlannerPage.tsx`, reemplazar `<ShipmentMarkersLayer>` con `<ShipmentClusterLayer>` y pasar `interactiveLayerIds={['shipment-clusters', 'shipment-unclustered']}` a MapCanvas.

4. **Verify:** Abrir Route Planner, verificar que shipments se muestran con clusters. Zoom in → clusters se expanden. Click en punto → selección funciona.

5. **Commit:** `feat: add ShipmentClusterLayer with native MapLibre clustering`

- [ ] Task 2.1 complete

---

### Task 2.2: ExceptionHeatmapLayer — heatmap + puntos toggle

**Goal:** Visualizar excepciones como heatmap (densidad) con toggle a puntos individuales.

**Steps:**

1. Crear nuevo componente:

**File:** `frontend/src/components/maps/layers/ExceptionHeatmapLayer.tsx`
```typescript
import { Source, Layer } from 'react-map-gl/maplibre';
import { useMemo } from 'react';

export interface ExceptionData {
  lat: number;
  lng: number;
  address: string;
  type: string;
  routeName: string;
  date: string | null;
}

interface Props {
  exceptions: ExceptionData[];
  mode: 'heatmap' | 'points';
}

const SOURCE_ID = 'exceptions-source';

export function ExceptionHeatmapLayer({ exceptions, mode }: Props) {
  const geojson = useMemo(() => ({
    type: 'FeatureCollection' as const,
    features: exceptions.map((ex, i) => ({
      type: 'Feature' as const,
      id: i,
      geometry: {
        type: 'Point' as const,
        coordinates: [ex.lng, ex.lat],
      },
      properties: {
        type: ex.type,
        address: ex.address,
        routeName: ex.routeName,
        date: ex.date,
      },
    })),
  }), [exceptions]);

  // Auto-select mode: heatmap if >20 exceptions, points otherwise
  const effectiveMode = exceptions.length < 20 ? 'points' : mode;

  return (
    <Source id={SOURCE_ID} type="geojson" data={geojson}>
      {/* Heatmap layer — visible when mode is heatmap */}
      <Layer
        id="exceptions-heatmap"
        type="heatmap"
        layout={{
          visibility: effectiveMode === 'heatmap' ? 'visible' : 'none',
        }}
        paint={{
          // Weight: all exceptions equal weight
          'heatmap-weight': 1,
          // Intensity increases with zoom
          'heatmap-intensity': [
            'interpolate', ['linear'], ['zoom'],
            0, 1,
            12, 3,
          ],
          // Color ramp: transparent → blue → yellow → red
          'heatmap-color': [
            'interpolate', ['linear'], ['heatmap-density'],
            0, 'rgba(0,0,0,0)',
            0.2, 'rgba(59,130,246,0.5)',    // blue-500
            0.4, 'rgba(139,92,246,0.6)',    // violet-500
            0.6, 'rgba(245,158,11,0.7)',    // amber-500
            0.8, 'rgba(239,68,68,0.8)',     // red-500
            1, 'rgba(239,68,68,1)',         // red-500 solid
          ],
          // Radius increases with zoom
          'heatmap-radius': [
            'interpolate', ['linear'], ['zoom'],
            0, 15,
            12, 30,
            16, 50,
          ],
          // Fade out heatmap at high zoom (where points take over)
          'heatmap-opacity': [
            'interpolate', ['linear'], ['zoom'],
            12, 1,
            16, 0.6,
          ],
        }}
      />

      {/* Point circles — visible when mode is points */}
      <Layer
        id="exceptions-points"
        type="circle"
        layout={{
          visibility: effectiveMode === 'points' ? 'visible' : 'none',
        }}
        paint={{
          'circle-color': 'rgba(239, 68, 68, 0.85)',
          'circle-radius': [
            'interpolate', ['linear'], ['zoom'],
            6, 4,
            12, 7,
            16, 10,
          ],
          'circle-stroke-width': 2,
          'circle-stroke-color': 'rgba(255,255,255,0.6)',
        }}
      />
    </Source>
  );
}
```

2. Modificar `ExceptionMapPage.tsx`:

- Añadir state para toggle: `const [viewMode, setViewMode] = useState<'heatmap' | 'points'>('heatmap');`
- Añadir toggle button en el sidebar
- Reemplazar `<ExceptionLayer exceptions={exceptions} />` con `<ExceptionHeatmapLayer exceptions={exceptions} mode={viewMode} />`
- Añadir click handler para mostrar popup cuando mode='points':
  - Pasar `interactiveLayerIds={['exceptions-points']}` a MapCanvas
  - En onClick, usar `e.features[0].properties` para mostrar info
  - Usar un state `selectedExceptionIdx` + un `<Popup>` de react-map-gl

3. Añadir toggle UI en el sidebar de ExceptionMapPage:
```tsx
<div className="flex gap-2 mb-4">
  <button
    onClick={() => setViewMode('heatmap')}
    className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${
      viewMode === 'heatmap'
        ? 'bg-red-500/20 text-red-400 border border-red-500/50'
        : 'bg-slate-800/50 text-slate-400 border border-slate-700/30 hover:text-slate-200'
    }`}
  >
    Heatmap
  </button>
  <button
    onClick={() => setViewMode('points')}
    className={`flex-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors ${
      viewMode === 'points'
        ? 'bg-red-500/20 text-red-400 border border-red-500/50'
        : 'bg-slate-800/50 text-slate-400 border border-slate-700/30 hover:text-slate-200'
    }`}
  >
    Puntos
  </button>
</div>
```

4. **Verify:** Abrir Exception Map, verificar heatmap con muchas excepciones. Toggle a puntos. Click en punto → popup con info.

5. **Commit:** `feat: add ExceptionHeatmapLayer with heatmap/points toggle`

- [ ] Task 2.2 complete

---

### Task 2.3: Actualizar MapCanvas para soportar interactiveLayerIds y onClick

**Goal:** MapCanvas necesita pasar props de interactividad al Map component de react-map-gl.

**Steps:**

1. Modificar `MapCanvas.tsx` Props interface:
```typescript
interface Props {
  children?: ReactNode;
  initialCenter?: { lat: number; lng: number };
  initialZoom?: number;
  showControls?: boolean;
  interactiveLayerIds?: string[];
  onClick?: (e: maplibregl.MapLayerMouseEvent) => void;
  cursor?: string;
}
```

2. Pasar props al `<Map>`:
```tsx
<Map
  ref={mapRef}
  mapLib={maplibregl}
  mapStyle={getDarkStyle()}
  initialViewState={{ ... }}
  style={{ width: '100%', height: '100%' }}
  interactiveLayerIds={interactiveLayerIds}
  onClick={onClick}
  cursor={cursor}
>
```

3. **Verify:** Build compiles. Existing pages sin interactiveLayerIds siguen funcionando.

4. **Commit:** `feat: add interactiveLayerIds and onClick props to MapCanvas`

**NOTE:** This task should be done BEFORE Task 2.1 and 2.2 since they depend on it.

- [ ] Task 2.3 complete

---

### Task 2.4: Actualizar layers/index.ts con nuevas exportaciones

**File:** `frontend/src/components/maps/layers/index.ts`

Añadir exports de los nuevos layers:
```typescript
export { ShipmentClusterLayer } from './ShipmentClusterLayer';
export { ExceptionHeatmapLayer } from './ExceptionHeatmapLayer';
```

- [ ] Task 2.4 complete

---

## Eje 3: Estilo Visual Profesional

### Task 3.1: Limpiar CSS overrides obsoletas

**Goal:** Con dark vector tiles, muchas CSS overrides de controles MapLibre ya no son necesarias.

**Steps:**

1. En `index.css`, evaluar qué overrides siguen siendo necesarias:
   - `.maplibregl-popup-content` — MANTENER (custom popup styling para nuestros popups)
   - `.maplibregl-popup-tip` — MANTENER
   - `.maplibregl-popup-close-button` — MANTENER
   - `.maplibregl-ctrl-group` — EVALUAR: con dark map, los controles de zoom podrían verse bien sin override. Si el fondo del mapa ya es oscuro, el contraste es suficiente. Probar sin y decidir.
   - `.maplibregl-ctrl-group button` — EVALUAR
   - `.maplibregl-ctrl-icon filter:invert(1)` — PROBABLEMENTE ELIMINAR: el invert era porque el fondo era claro

2. Probar visualmente sin las overrides de `ctrl-group` y decidir.

3. **Commit:** `refactor: clean up MapLibre CSS overrides after dark theme migration`

- [ ] Task 3.1 complete

---

## Orden de Ejecución

El orden óptimo (respetando dependencias):

1. **Task 1.1** — Dark style + Protomaps (fundación para todo)
2. **Task 1.2** — Labels español + carreteras (refinamiento del estilo)
3. **Task 2.3** — MapCanvas interactiveLayerIds (prerrequisito para layers nativos)
4. **Task 2.1** — ShipmentClusterLayer
5. **Task 2.2** — ExceptionHeatmapLayer
6. **Task 2.4** — Actualizar index.ts
7. **Task 3.1** — Limpiar CSS

---

## Verificación Final

Después de todas las tareas:

1. `cd frontend && npm run build` — sin errores
2. `cd frontend && npm run lint` — sin warnings nuevos
3. Verificar visualmente cada página con mapa:
   - Fleet Map (`/app/admin/fleet-map`) — dark tiles, vehicles visibles
   - Route Planner (`/app/admin/route-planner`) — clustering funciona
   - Exception Map (`/app/admin/exceptions`) — heatmap/points toggle funciona
   - Route Analysis — polylines visibles sobre dark tiles
   - Customer Route Detail — stops y polyline visibles
4. Verificar que el fallback a raster funciona si se desconecta el CDN de Protomaps
