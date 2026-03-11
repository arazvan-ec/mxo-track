# Route Optimization

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Providers de Optimización

| Provider | Tipo | Infraestructura | Descripción |
|----------|------|-----------------|-------------|
| **VROOM** | RouteOptimizer | Sí (servidor VROOM + OSRM) | VRP solver real con road distances |
| **Greedy** | RouteOptimizer | No | Nearest-neighbor heurístico, PHP puro |

## Providers de Routing

| Provider | Tipo | Infraestructura | Descripción |
|----------|------|-----------------|-------------|
| **OSRM** | RoutingEngine | Sí (servidor OSRM + mapa) | Road routing con distancias/duraciones reales |
| **Google Directions** | RoutingEngine | No (API key) | Google Maps Directions API |

## VROOM Integration

### Componentes

| Componente | Responsabilidad |
|------------|----------------|
| `RouteBuilder` | Construye rutas optimizadas via VROOM (multi-vehículo) |
| `VroomApiClient` | HTTP client para VROOM Express API (POST JSON) |
| `VroomRequestMapper` | Convierte entities → formato VROOM |
| `VroomResponseMapper` | Convierte respuesta VROOM → Route/RouteStop |
| `RouteOptimizationService` | Re-optimiza stop order de rutas existentes |
| `RouteCapacityValidator` | Valida restricciones de capacidad |

### Formato VROOM

- **Coordenadas**: `[longitude, latitude]` (orden invertido vs Google Maps)
- **Capacidad**: 3 dimensiones enteras: `[weight_grams, volume_cm3, parcels]`
- **Request**: POST JSON con arrays de `vehicles` y `jobs`
- **Response**: Array de `routes` con `steps` ordenados

### Greedy Optimizer

- Algoritmo nearest-neighbor en PHP puro
- Usa cálculo haversine interno (distancia en línea recta) para ordenar paradas
- Sin infraestructura requerida, ideal para deploy lite

## OSRM

### Preparación del Mapa

```bash
./docker/osrm/prepare-map.sh
```

Descarga mapa de Madrid (~75 MB) y genera ficheros `.osrm.*`. Solo una vez.

### Variables de Entorno

```env
OSRM_URL=http://osrm:5000                    # local
# OSRM_URL=http://osrm-mxo.railway.internal:5000  # Railway
VROOM_URL=http://vroom:3000                    # local
# VROOM_URL=http://vroom-mxo.railway.internal:3000  # Railway
```

## Google Directions

### Configuración

```env
DEFAULT_ROUTING_ENGINE=google_directions
GOOGLE_DIRECTIONS_API_KEY=<API key con Directions API habilitada>
```

`GoogleDirectionsFactory` inyecta `$defaultApiKey` desde env var. Si `CustomerIntegration.config` no trae `api_key`, usa el default global.

## Capacidad de Vehículos

Para optimización con VROOM, se necesita:

1. Configurar volumen y peso en cada **entrega** (Shipment: `weight_grams`, `volume_cm3`, `parcel_count`)
2. Configurar capacidad de cada **vehículo** (Vehicle: `max_weight_grams`, `max_volume_cm3`, `max_parcels`)

`RouteCapacityValidator` verifica que no se exceda la capacidad antes de confirmar rutas.

## Servicios de Análisis de Rutas

| Servicio | Propósito |
|----------|-----------|
| `RoutePlanningService` | Planificación de rutas (selección vehículos + optimización) |
| `RouteLifecycleService` | Transiciones de estado (PLANNED → ACTIVE → DONE) |
| `RouteOptimizationService` | Re-optimización de rutas existentes via VROOM |
| `RouteComparisonService` | Comparación planificado vs real (usa haversine interno) |
| `PostRouteAnalyzer` | Análisis de eficiencia post-ruta |

## Historial

- 2026-03-11: Creación inicial
