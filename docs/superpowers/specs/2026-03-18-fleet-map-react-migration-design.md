# Fleet Map: Migrar a React + MapLibre

## Fecha: 2026-03-18

## Objetivo

Migrar el Fleet Map (`/fleet/map`) de Twig+Leaflet+Alpine a React+MapLibre, reutilizando y extendiendo los componentes React existentes en `frontend/`.

## Alcance

**IN:** Paridad funcional completa con la vista Twig actual (810 LOC)
**OUT:** Nuevas funcionalidades no presentes en Twig, migrar otras vistas

## Arquitectura

```
FleetMapPage (page layout)
  +-- FleetSidebar (left panel)
  |     +-- KpiPills
  |     +-- TabSelector (vehicles | routes)
  |     +-- VehicleSearch
  |     +-- VehicleList
  |     +-- RouteList
  |     +-- RouteProgressBar
  +-- FleetMapView (map area)
  |     +-- HeaderBar (clock, SSE status, demo link)
  |     +-- MapCanvas (react-map-gl)
  |           +-- VehicleMarker[] (existing, enhance with popup)
  |           +-- StopMarker[] (existing, conditional on selection)
  |           +-- RouteSegments (polylines stop-to-stop, color by status)
  |           +-- VehicleTrail (polyline of historical positions)
  +-- Hooks
        +-- useFleetMapData (existing, enhance with route updates)
        +-- useMercurePositions (existing)
        +-- useMercureRouteUpdates (new - listen to /map/routes/*/updates)
        +-- useVehicleTrail (new - fetch /api/vehicles/{id}/positions)
```

## Estado del mapa (React state)

```typescript
interface FleetMapState {
  activeTab: 'vehicles' | 'routes';
  searchQuery: string;
  selectedVehicleId: string | null;
  selectedRouteId: string | null;
}
```

El estado se gestiona con `useState` en `FleetMapPage`. No se necesita estado global.

## Datos

- **Inicial:** `GET /api/fleet/map-data` (ya existe, `FleetMapDataController`)
- **KPIs:** `GET /api/fleet/summary` (ya existe, `FleetMapController::summary`)
- **Trail:** `GET /api/vehicles/{id}/positions?order=ASC&limit=500` (ya existe)
- **SSE posiciones:** `/map/vehicles/{id}/position` (ya funciona con `useMercurePositions`)
- **SSE route updates:** `/map/routes/{id}/updates` (nuevo hook)

## Decisiones de diseño

1. **Sin Alpine.js** - todo React puro con hooks
2. **Sin Leaflet** - MapLibre GL via react-map-gl
3. **Mismos estilos** - Tailwind dark theme, misma paleta
4. **Mismos endpoints** - no se modifica el backend
5. **Coexistencia** - la vista Twig permanece en `/fleet/map`, la React en `/app/admin/fleet-map` hasta que se valide paridad
6. **Fly-to** - via `mapRef.current.flyTo()` de react-map-gl

## Componentes a crear/modificar

| Componente | Estado | Descripción |
|-----------|--------|-------------|
| `FleetMapPage` | Modificar | Agregar state management, sidebar, header |
| `FleetSidebar` | Crear | Panel izquierdo con tabs/search/lists |
| `KpiPills` | Crear | 3 KPI badges |
| `VehicleList` | Extraer | Lista de vehículos seleccionables |
| `RouteList` | Crear | Lista de rutas seleccionables |
| `RouteProgressBar` | Crear | Barra de progreso de ruta |
| `HeaderBar` | Crear | Reloj, SSE status, links |
| `FleetMapView` | Crear | Wrapper del mapa con markers condicionales |
| `VehicleMarker` | Modificar | Agregar popup on click |
| `VehiclePopup` | Crear | Popup con detalles del vehículo |
| `RouteSegments` | Crear | Polylines stop-to-stop con color por status |
| `VehicleTrail` | Crear | Polyline del trail histórico |
| `useMercureRouteUpdates` | Crear | Hook para route updates SSE |
| `useVehicleTrail` | Crear | Hook para fetch trail positions |
| `useFleetKpi` | Crear | Hook para KPI summary |
