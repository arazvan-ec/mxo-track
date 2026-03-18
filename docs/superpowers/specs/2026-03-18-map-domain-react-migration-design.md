# Map Domain: Migración completa a React + MapLibre

## Fecha: 2026-03-18

## Objetivo

Crear un dominio reutilizable de mapas que soporte las 9 vistas con mapa del sistema, migrándolas de Twig+Leaflet+Alpine a React+MapLibre con una arquitectura composable.

## Alcance

**IN:** 9 vistas con mapa + sus APIs backend + SSE reactivo
**OUT:** ~30 páginas CRUD admin (se quedan en Twig), auth pages, email templates

## Vistas a migrar

| # | Vista | URL actual | Complejidad | Prioridad |
|---|-------|-----------|-------------|-----------|
| 1 | Fleet Map | `/fleet/map` | Alta | ✅ Hecho |
| 2 | Admin Route Detail | `/admin/routes/{id}/show` | Alta | P1 |
| 3 | Customer Route Detail | `/customer/routes/{id}` | Media | P2 |
| 4 | Driver Route | `/driver/routes/{id}` | Media | P2 |
| 5 | Test Routing | `/admin/test-routing/map` | Alta | P3 |
| 6 | Operator Live Dashboard | `/operator/dashboard/live` | Alta | P3 |
| 7 | Exception Map | `/admin/reports/exception-map` | Baja | P4 |
| 8 | Route Analysis | `/admin/route/analysis` | Media | P4 |
| 9 | Route Planner | `/admin/route-planner` | Muy Alta | P5 |

## Arquitectura

### Backend: API genérica de MapView

```
src/Infrastructure/MapView/Controller/
  FleetMapDataController.php      ← ya existe
  RouteMapDataController.php      ← nuevo: GET /api/map/route/{publicId}
  ComparisonMapDataController.php ← nuevo: GET /api/map/comparison
  ExceptionMapDataController.php  ← nuevo: GET /api/map/exceptions

src/Application/MapView/
  MapViewService.php              ← nuevo: orquesta la composición de MapViewData
```

Todos los endpoints devuelven `MapViewData` (ya existe en `src/View/MapViewData.php`) con `MapViewOptions` que controlan qué datos incluir según el rol y el tipo de vista.

#### Nuevo endpoint: `/api/map/route/{publicId}`

```php
// Retorna MapViewData para una sola ruta
// Options varían por rol:
//   ROLE_ADMIN: metrics, timing, originalStops, comparison
//   ROLE_CUSTOMER: stops, ETA, vehicle position
//   ROLE_DRIVER: stops, ETA, vehicle position, navigation hints
```

### Frontend: Composición por capas

```
frontend/src/
  components/
    maps/
      MapCanvas.tsx              ← MapLibre wrapper (dark theme, controls, bounds)
      layers/
        VehicleLayer.tsx         ← N vehículos con posiciones live
        RoutePolylineLayer.tsx   ← polyline de ruta (encoded o stop-to-stop)
        StopMarkersLayer.tsx     ← N stops con status colors
        VehicleTrailLayer.tsx    ← ya existe, mover aquí
        ExceptionLayer.tsx       ← puntos de excepción en mapa
        ComparisonLayer.tsx      ← original vs optimizado side-by-side
      shared/
        VehicleMarker.tsx        ← ya existe
        StopMarker.tsx           ← ya existe
        OriginMarker.tsx         ← ya existe
        colors.ts                ← ya existe
        polyline.ts              ← ya existe

    panels/
      StopListPanel.tsx          ← lista de stops reactiva (shared entre route views)
      RouteMetricsPanel.tsx      ← métricas de optimización (admin only)
      VehicleInfoPanel.tsx       ← info del vehículo seleccionado

  hooks/
    useMapSubscription.ts        ← genérico: subscribe a N topics de mapa
    useRouteMapData.ts           ← fetch /api/map/route/{id} + merge SSE updates
    useFleetMapData.ts           ← ya existe
    useVehicleTrail.ts           ← ya existe
    useMercure.ts                ← ya existe (base)
    useMercurePositions.ts       ← ya existe
    useMercureRouteUpdates.ts    ← ya existe

  pages/
    admin/
      FleetMapPage.tsx           ← ya existe
      RouteDetailPage.tsx        ← nuevo
      TestRoutingPage.tsx        ← nuevo
      OperatorDashboardPage.tsx  ← nuevo
      ExceptionMapPage.tsx       ← nuevo
      RouteAnalysisPage.tsx      ← nuevo
      RoutePlannerPage.tsx       ← nuevo (fase final)
    customer/
      CustomerRouteDetailPage.tsx ← nuevo
    driver/
      DriverRoutePage.tsx        ← nuevo
```

### Patrón de composición

Cada página sigue este patrón:

```tsx
function RouteDetailPage() {
  const { route, vehicle, isLoading } = useRouteMapData(routeId);
  const mapRef = useRef<MapCanvasHandle>(null);

  return (
    <div className="flex h-full">
      <Sidebar>
        <StopListPanel stops={route.stops} onSelect={...} />
        <RouteMetricsPanel metrics={route.metrics} />
      </Sidebar>
      <MapCanvas ref={mapRef}>
        <RoutePolylineLayer route={route} />
        <StopMarkersLayer stops={route.stops} />
        {vehicle && <VehicleLayer vehicles={[vehicle]} />}
      </MapCanvas>
    </div>
  );
}
```

### MapCanvas: el componente central

```tsx
interface MapCanvasProps {
  children: ReactNode;           // layers se pasan como children
  initialBounds?: LngLatBounds;  // auto-fit inicial
  darkTheme?: boolean;           // default true
  showControls?: boolean;        // zoom, etc.
}

interface MapCanvasHandle {
  flyTo(lng: number, lat: number, zoom?: number): void;
  fitBounds(points: {lat: number; lng: number}[]): void;
  getMapRef(): MapRef;
}
```

### Layers como componentes React

Cada layer es un componente que usa `<Source>` + `<Layer>` de react-map-gl:

```tsx
// Se usan como children de MapCanvas
<MapCanvas>
  <RoutePolylineLayer routeId="abc" polyline="encoded..." color="#3b82f6" />
  <StopMarkersLayer stops={stops} onStopClick={handleClick} />
  <VehicleLayer vehicles={vehicles} onVehicleClick={handleClick} />
</MapCanvas>
```

### SSE: useMapSubscription genérico

```tsx
// Reemplaza useMercurePositions + useMercureRouteUpdates por un hook unificado
function useMapSubscription(config: {
  vehicleIds?: string[];
  routeIds?: string[];
  enabled?: boolean;
}) {
  // Internamente usa useMercure con los topics correctos
  // Retorna: { positions: Map, routeUpdates: Map, connected: boolean }
}
```

## Decisiones de diseño

1. **MapCanvas wrappea react-map-gl** — no re-inventar el wheel, solo dark theme + controls + ref handle
2. **Layers como children** — composable, cada página elige qué layers mostrar
3. **Sin "MapPage genérico"** — cada página es su propia composición (evita god component)
4. **Backend reutiliza MapViewData** — ya tiene routes, stops, metrics, options. Solo añadir endpoints
5. **Coexistencia Twig/React** — las URLs React van bajo `/app/*`, las Twig mantienen su URL
6. **Panels compartidos** — StopListPanel se reutiliza en admin/customer/driver route views
7. **Un hook de datos por tipo de vista** — `useRouteMapData`, `useFleetMapData`, etc.

## Fases de implementación

### Fase 0: Refactor shared infrastructure (prerequisito)
- Extraer `MapCanvas` de `FleetMap.tsx`
- Mover layers a `components/maps/layers/`
- Crear `useMapSubscription` unificado
- Crear `StopListPanel` y `RouteMetricsPanel` shared

### Fase 1: Route Detail (admin) — la vista más rica
- Backend: `/api/map/route/{publicId}` endpoint
- Frontend: `RouteDetailPage` con MapCanvas + RoutePolylineLayer + StopMarkersLayer + VehicleLayer
- Sidebar: StopListPanel + RouteMetricsPanel + route event timeline
- SSE: route updates + vehicle position

### Fase 2: Customer + Driver route views
- Reutilizar RouteDetailPage con options por rol
- Customer: sin métricas, sin comparison
- Driver: con ETAs prominentes, sin métricas admin

### Fase 3: Test Routing + Operator Dashboard
- Test Routing: ComparisonLayer (original vs optimizado), métricas globales
- Operator: variante del Fleet Map con KPIs diferentes

### Fase 4: Exception Map + Route Analysis
- Exception Map: ExceptionLayer sobre MapCanvas
- Route Analysis: overlay de performance metrics

### Fase 5: Route Planner (la más compleja)
- Interactivo: drag-drop shipments, clustering visual, preview routes
- Requiere diseño propio (brainstorming separado)
