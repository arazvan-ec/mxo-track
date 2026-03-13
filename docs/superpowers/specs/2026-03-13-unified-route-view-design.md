# Unified Route View Layer

**Fecha:** 2026-03-13
**Estado:** Propuesta
**Alcance:** Vistas de ruta (test-routing, planner step 3, customer route show, driver route show, route analysis)

## Problema

8 implementaciones de mapa con codigo Leaflet duplicado. Cada template tiene su propia logica de marcadores, polylines, metricas y tracking. Cuando se anade una feature nueva (ej: mostrar detalles de 2 rutas), hay que implementarla en cada lugar por separado.

**Vistas afectadas (5):**

| Vista | Rol | Rutas | Features actuales |
|-------|-----|-------|-------------------|
| test-routing/map | Admin | N rutas | Polylines, optimization before/after, metrics, logs |
| route_planner step 3 | Admin | N rutas | Polylines, capacity bars, driver suggestions |
| customer/route/show | Customer | 1 ruta | Stops + status, vehicle tracking (Mercure) |
| driver/routes/show | Driver | 1 ruta | Stops + status, vehicle tracking (Mercure) |
| admin/route/analysis | Admin | 1 ruta | Planned vs actual polylines, AI analysis |

**Fuera de alcance (por ahora):** fleet map, operator dashboard live, exception heatmap (logica diferente: tracking masivo, heatmaps).

## Solucion

Dos capas:

1. **Backend: `RouteViewService`** — Servicio PHP que toma entidades Route y produce DTOs estandarizados para display, filtrando datos segun rol y features solicitadas.
2. **Frontend: Twig Components + JS** — Componentes Twig reutilizables con una clase JS `MxoRouteMap` compartida.

### Principio de visibilidad

```
Datos mostrados = filtro_rol(rol_usuario) ∩ features_pagina(config_controlador)
```

- El servicio aplica un filtro base por rol (driver no ve metricas de negocio)
- Cada controlador configura que features activar dentro de lo permitido

---

## Capa Backend

### RouteViewService

```php
namespace App\View;

final class RouteViewService
{
    public function buildMapView(
        array $routes,          // Route entities
        MapViewOptions $options,
    ): MapViewData;
}
```

### MapViewOptions (config del controlador)

```php
namespace App\View;

final class MapViewOptions
{
    public function __construct(
        public readonly string $role,                    // ROLE_ADMIN, ROLE_CUSTOMER, ROLE_DRIVER
        public readonly bool $showOptimizationMetrics = false,   // distancia antes/despues, ahorro
        public readonly bool $showTimingBreakdown = false,       // desglose conduccion/entregas
        public readonly bool $showVehicleTracking = false,       // marcador vehiculo en vivo
        public readonly bool $showStopStatus = true,             // colores por estado de parada
        public readonly bool $showCapacityValidation = false,    // barras peso/volumen/bultos
        public readonly bool $showOriginalOrder = false,         // orden antes de optimizar
        public readonly bool $showPolylines = true,              // lineas de ruta en mapa
        public readonly bool $showOptimizationLog = false,       // panel de log lateral
        public readonly ?string $comparisonMode = null,          // 'planned_vs_actual' para analysis
        public readonly ?string $vehiclePublicId = null,         // para Mercure tracking
        public readonly ?array $vehiclePosition = null,          // posicion actual del vehiculo
        public readonly ?array $optimizationLog = null,          // datos del log panel
    ) {}
}
```

### MapViewData (output para template)

```php
namespace App\View;

final class MapViewData
{
    public function __construct(
        public readonly array $routes,           // RouteViewData[]
        public readonly ?array $origin,          // {lat, lng, address} o null
        public readonly ?array $globalMetrics,   // {distanceBeforeKm, distanceAfterKm, savedPercent, ...}
        public readonly MapViewOptions $options,
    ) {}

    public function toJson(): string;   // para pasar a JS
}
```

### RouteViewData (datos de una ruta)

```php
namespace App\View;

final class RouteViewData
{
    public function __construct(
        public readonly string $publicId,
        public readonly string $name,
        public readonly string $color,              // hex color asignado por indice
        public readonly ?string $vehicleName,
        public readonly ?string $driverName,
        public readonly ?string $status,             // PLANNED, ACTIVE, DONE, CANCELLED
        public readonly array $stops,                // StopViewData[]
        public readonly ?string $polyline,           // encoded polyline (OSRM)
        public readonly ?array $metrics,             // {distanceKm, durationMinutes, savedPercent, ...}
        public readonly ?array $timing,              // {drivingTimeMinutes, deliveryTimeMinutes, totalTimeMinutes}
        public readonly ?array $validation,          // {valid, totalWeightKg, weightUtilization, ...}
        public readonly ?array $originalStops,       // StopViewData[] antes de optimizar
        public readonly ?string $comparisonPolyline, // para analysis: polyline actual vs planned
    ) {}
}
```

### StopViewData

```php
namespace App\View;

final class StopViewData
{
    public function __construct(
        public readonly int $sequence,
        public readonly string $address,
        public readonly ?string $recipientName,
        public readonly ?string $recipientPhone,
        public readonly float $lat,
        public readonly float $lng,
        public readonly string $status,     // PENDING, DELIVERED, EXCEPTION, SKIPPED
        public readonly bool $isOrigin,
        public readonly ?string $deliveredAt,
        public readonly ?string $notes,
    ) {}
}
```

### Filtro por rol

El servicio aplica estos filtros ANTES de construir los DTOs:

| Campo | Admin | Customer | Driver |
|-------|-------|----------|--------|
| stops (all) | Si | Si | Si |
| stop.recipientPhone | Si | Si | Si |
| vehicle tracking | Si | Si | Si |
| metrics (distance, savings) | Si | No | No |
| timing breakdown | Si | No | No |
| optimization log | Si | No | No |
| capacity validation | Si | No | No |
| original stop order | Si | No | No |
| comparison polyline | Si | No | No |
| stop.notes | Si | Si | Solo propios |

Si `options.showOptimizationMetrics = true` pero `role = ROLE_CUSTOMER`, el servicio ignora la feature silenciosamente (no error, simplemente no incluye los datos).

---

## Capa Frontend

### Estructura de archivos

```
templates/components/route/
    _map.html.twig            # Mapa Leaflet con rutas
    _map_js.html.twig         # Clase JS MxoRouteMap (incluir 1 vez por pagina)
    _metrics.html.twig        # Cards de metricas globales
    _stop_list.html.twig      # Tabla de paradas
    _route_card.html.twig     # Card de detalle por ruta (metrics + timing + stops)
    _optimization_log.html.twig  # Panel log (refactor del existente)
```

### _map.html.twig

```twig
{# Incluir JS una sola vez en la pagina #}
{% if not _mxo_route_map_js_loaded is defined %}
    {% set _mxo_route_map_js_loaded = true %}
    {% include 'components/route/_map_js.html.twig' %}
{% endif %}

<div id="{{ mapId }}" style="height: {{ height|default('500px') }}; border-radius: 0.5rem;"
     class="border border-gray-200 shadow-sm"></div>

<script>
(function() {
    new MxoRouteMap('{{ mapId }}', {{ mapData.toJson()|raw }});
})();
</script>
```

### _map_js.html.twig (clase JS compartida)

Contiene la clase `MxoRouteMap` que maneja:

- Inicializacion Leaflet con OSM tiles
- Renderizado de N rutas con polylines (colores por indice)
- Marcadores de paradas (numerados, color por status)
- Marcador de origen (verde "O")
- Decodificacion de polylines encoded (Google format)
- Fallback a lineas rectas si no hay polyline
- Toggle de visibilidad por ruta
- Marcador de vehiculo con tracking Mercure
- Fit bounds automatico
- Polyline de comparacion (planned vs actual)
- Flechas de direccion (polyline decorator)

```javascript
class MxoRouteMap {
    constructor(elementId, data) {
        this.data = data;
        this.map = L.map(elementId);
        this.layers = {};
        this.init();
    }

    init() {
        this.addTiles();
        this.renderRoutes();
        this.renderOrigin();
        if (this.data.options.showVehicleTracking) {
            this.initVehicleTracking();
        }
        this.fitBounds();
    }

    // ... metodos para cada feature
}
```

### _metrics.html.twig

```twig
{# Uso: {% include 'components/route/_metrics.html.twig' with {metrics: mapData.globalMetrics} %} #}
{% if metrics %}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    {# Cards: distancia original, optimizada, ahorro, tiempo #}
</div>
{% endif %}
```

### _stop_list.html.twig

```twig
{# Uso: {% include 'components/route/_stop_list.html.twig' with {stops: route.stops, showStatus: true} %} #}
<table class="w-full text-sm">
    <thead>...</thead>
    <tbody>
        {% for stop in stops %}
        <tr>
            <td>{{ stop.sequence }}</td>
            <td>{{ stop.recipientName }}</td>
            <td>{{ stop.address }}</td>
            {% if showStatus|default(false) %}
            <td>{{ stop.status }}</td>
            {% endif %}
        </tr>
        {% endfor %}
    </tbody>
</table>
```

### _route_card.html.twig

```twig
{# Card con detalle de una ruta: metrics + timing + stop tables #}
{# Uso: {% include 'components/route/_route_card.html.twig' with {route: routeViewData, options: mapData.options} %} #}
<div class="bg-white shadow rounded-lg overflow-hidden mb-6">
    <div class="px-4 py-3 border-b flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-4 h-4 rounded-full" style="background: {{ route.color }};"></div>
            <h3>{{ route.name }}</h3>
            {% if route.vehicleName %}<span>{{ route.vehicleName }}</span>{% endif %}
        </div>
    </div>

    {% if route.metrics and options.showOptimizationMetrics %}
        {# Metricas por ruta #}
    {% endif %}

    {% if route.timing and options.showTimingBreakdown %}
        {# Desglose de tiempos #}
    {% endif %}

    {% if options.showOriginalOrder and route.originalStops %}
        {# Tablas lado a lado: original vs optimizado #}
    {% endif %}

    {% if options.showCapacityValidation and route.validation %}
        {# Barras de capacidad #}
    {% endif %}
</div>
```

---

## Ejemplo de uso por vista

### test-routing/map (Admin)

```php
// Controller
$mapView = $routeViewService->buildMapView($routes, new MapViewOptions(
    role: 'ROLE_ADMIN',
    showOptimizationMetrics: true,
    showTimingBreakdown: true,
    showOriginalOrder: true,
    showPolylines: true,
    showOptimizationLog: true,
    optimizationLog: $logData,
));
```

```twig
{# Template #}
{% include 'components/route/_map.html.twig' with {mapId: 'routing-map', mapData: mapView, height: '600px'} %}
{% include 'components/route/_metrics.html.twig' with {metrics: mapView.globalMetrics} %}
{% if mapView.options.showOptimizationLog %}
    {% include 'components/route/_optimization_log.html.twig' with {log: mapView.options.optimizationLog} %}
{% endif %}
{% for route in mapView.routes %}
    {% include 'components/route/_route_card.html.twig' with {route: route, options: mapView.options} %}
{% endfor %}
```

### customer/route/show (Customer)

```php
$mapView = $routeViewService->buildMapView([$route], new MapViewOptions(
    role: 'ROLE_CUSTOMER',
    showVehicleTracking: true,
    showStopStatus: true,
    vehiclePublicId: $vehiclePublicId,
    vehiclePosition: $vehiclePosition,
));
```

```twig
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
    <div class="lg:col-span-2">
        {% include 'components/route/_stop_list.html.twig' with {stops: mapView.routes[0].stops, showStatus: true} %}
    </div>
    <div class="lg:col-span-3">
        {% include 'components/route/_map.html.twig' with {mapId: 'route-map', mapData: mapView, height: '600px'} %}
    </div>
</div>
```

### driver/routes/show (Driver)

```php
$mapView = $routeViewService->buildMapView([$route], new MapViewOptions(
    role: 'ROLE_DRIVER',
    showVehicleTracking: true,
    showStopStatus: true,
    vehiclePublicId: $vehiclePublicId,
    vehiclePosition: $vehiclePosition,
));
```

Template identico al customer (mismos componentes, datos filtrados por rol).

### route_planner step 3 (Admin)

```php
$mapView = $routeViewService->buildMapView($routes, new MapViewOptions(
    role: 'ROLE_ADMIN',
    showCapacityValidation: true,
    showPolylines: true,
));
```

```twig
{% include 'components/route/_map.html.twig' with {mapId: 'preview-map', mapData: mapView, height: '400px'} %}
{% for route in mapView.routes %}
    {% include 'components/route/_route_card.html.twig' with {route: route, options: mapView.options} %}
{% endfor %}
```

### admin/route/analysis (Admin)

```php
$mapView = $routeViewService->buildMapView([$route], new MapViewOptions(
    role: 'ROLE_ADMIN',
    showPolylines: true,
    showStopStatus: true,
    comparisonMode: 'planned_vs_actual',
));
```

```twig
{% include 'components/route/_map.html.twig' with {mapId: 'analysis-map', mapData: mapView, height: '500px'} %}
{% include 'components/route/_metrics.html.twig' with {metrics: mapView.globalMetrics} %}
```

---

## Constantes compartidas

Los colores y estilos se definen una sola vez en la clase JS:

```javascript
MxoRouteMap.COLORS = {
    routes: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'],
    stopStatus: {
        PENDING: '#3b82f6',
        DELIVERED: '#22c55e',
        EXCEPTION: '#ef4444',
        SKIPPED: '#6b7280',
    },
    origin: '#059669',
    vehicle: '#1e3a8a',
    originalRoute: '#ef4444',
    comparisonActual: '#ef4444',
    comparisonPlanned: '#3b82f6',
};
```

---

## Plan de migracion

Orden de migracion incremental (cada paso es independiente y deployable):

1. **Crear DTOs y RouteViewService** — sin cambiar ningun template aun
2. **Crear componentes Twig + MxoRouteMap JS** — nuevos archivos, sin afectar existentes
3. **Migrar customer/route/show** — vista mas simple (1 ruta, status, tracking)
4. **Migrar driver/routes/show** — casi identica a customer
5. **Migrar test-routing/map** — multi-ruta con metricas
6. **Migrar route_planner step 3** — mas complejo por Alpine.js + driver suggestions
7. **Migrar admin/route/analysis** — comparison mode

Cada paso: PR independiente, tests de humo, verificacion visual.

---

## Archivos a crear/modificar

**Nuevos:**
- `src/View/RouteViewService.php`
- `src/View/MapViewOptions.php`
- `src/View/MapViewData.php`
- `src/View/RouteViewData.php`
- `src/View/StopViewData.php`
- `templates/components/route/_map.html.twig`
- `templates/components/route/_map_js.html.twig`
- `templates/components/route/_metrics.html.twig`
- `templates/components/route/_stop_list.html.twig`
- `templates/components/route/_route_card.html.twig`
- `templates/components/route/_optimization_log.html.twig` (refactor)

**Modificados (migracion incremental):**
- `templates/customer/route/show.html.twig`
- `templates/driver/routes/show.html.twig`
- `templates/admin/test-routing/map.html.twig`
- `templates/admin/route_planner/index.html.twig` (step 3 section)
- `templates/admin/route/analysis.html.twig`
- Controllers correspondientes (anaden `RouteViewService` y pasan `MapViewData`)

**Sin cambios:** fleet map, operator dashboard, exception heatmap (fuera de alcance).
