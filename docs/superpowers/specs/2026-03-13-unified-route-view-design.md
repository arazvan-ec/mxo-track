# Unified Route View Layer

**Fecha:** 2026-03-13
**Estado:** Propuesta v2
**Alcance:** Vistas de ruta (test-routing, planner step 3, customer route show, driver route show, route analysis)

## Problema

1. **Codigo duplicado:** 8 implementaciones de mapa con Leaflet inline. Cada feature nueva se implementa N veces.
2. **Sin persistencia de optimizacion:** Polylines OSRM, metricas de ahorro, orden original y timing se recalculan en CADA carga de pagina. El test-routing hace 4+ llamadas HTTP a OSRM cada vez.
3. **Sin propagacion de eventos:** Cuando una parada se entrega, solo cambia el estado en BD. Las vistas abiertas no se actualizan (excepto tracking de vehiculo via Mercure).

**Vistas afectadas (5):**

| Vista | Rol | Rutas | Features |
|-------|-----|-------|----------|
| test-routing/map | Admin | N rutas | Polylines, optimization before/after, metrics, logs |
| route_planner step 3 | Admin | N rutas | Polylines, capacity bars, driver suggestions |
| customer/route/show | Customer | 1 ruta | Stops + status, vehicle tracking (Mercure) |
| driver/routes/show | Driver | 1 ruta | Stops + status, vehicle tracking (Mercure) |
| admin/route/analysis | Admin | 1 ruta | Planned vs actual polylines, AI analysis |

**Fuera de alcance:** fleet map, operator dashboard live, exception heatmap.

## Solucion: 3 capas

```
┌─────────────────────────────────────────────────┐
│  Frontend: Twig Components + MxoRouteMap JS     │
│  (_map, _metrics, _stop_list, _route_card)      │
│  Recibe MapViewData JSON, renderiza, escucha    │
│  Mercure para re-render completo                │
├─────────────────────────────────────────────────┤
│  View Service: RouteViewService                 │
│  Lee RouteSnapshot (persistido), filtra por     │
│  rol + features, produce MapViewData DTO        │
├─────────────────────────────────────────────────┤
│  Domain: RouteSnapshot entity                   │
│  Persiste resultado de optimizacion.            │
│  Se crea/actualiza en build/optimize.           │
│  Se re-serializa y publica via Mercure          │
│  en cada evento de ruta.                        │
└─────────────────────────────────────────────────┘
```

### Principio de visibilidad

```
Datos mostrados = filtro_rol(rol_usuario) ∩ features_pagina(config_controlador)
```

El servicio aplica filtro base por rol Y cada controlador configura features adicionales.

---

## Capa 1: Domain — RouteSnapshot

### Entidad RouteSnapshot

Relacion **OneToOne** con Route. Captura el estado completo de la visualizacion de una ruta. Se recrea cuando se re-optimiza, se actualiza parcialmente cuando ocurren eventos.

```php
namespace App\Entity;

#[ORM\Entity]
#[ORM\Table(name: 'route_snapshot')]
class RouteSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    // ── Polylines (encoded Google format from OSRM) ──

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $polyline = null;              // ruta optimizada

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $originalPolyline = null;      // ruta antes de optimizar

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $actualPolyline = null;        // ruta real (GPS trail, post-ruta)

    // ── Metricas de optimizacion ──

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $distanceBeforeKm = null;       // distancia antes de optimizar

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $distanceAfterKm = null;        // distancia optimizada

    #[ORM\Column(type: 'decimal', precision: 5, scale: 1, nullable: true)]
    private ?float $savingsPercent = null;          // % ahorro

    // ── Timing ──

    #[ORM\Column(nullable: true)]
    private ?int $drivingTimeMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $deliveryTimeMinutes = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalTimeMinutes = null;

    // ── Snapshots de paradas ──

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $originalStopOrder = null;      // [{seq, address, recipient, lat, lng}]

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $stopStates = null;             // [{seq, status, deliveredAt}] — actualizado por eventos

    // ── Capacidad (snapshot del momento de creacion) ──

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $capacityValidation = null;     // {valid, totalWeightKg, weightUtilization, ...}

    // ── Timestamps ──

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    // getters/setters...
}
```

### Cuando se crea/actualiza el snapshot

| Evento | Accion sobre RouteSnapshot |
|--------|---------------------------|
| **Route built** (RouteBuilder) | Crear snapshot: polyline, distanceBefore/After, timing, originalStopOrder, capacityValidation |
| **Route optimized** (RoutePlanningService) | Recrear snapshot: nuevo polyline, nuevas metricas, nuevo orden. Mantener originalPolyline del anterior |
| **Stop delivered** (DeliveryService) | Actualizar stopStates. Publicar re-render completo via Mercure |
| **Stop exception** (DeliveryService) | Actualizar stopStates. Publicar re-render via Mercure |
| **Route started** | Actualizar stopStates (all PENDING). Publicar via Mercure |
| **Route completed** | Generar actualPolyline (GPS trail). Publicar via Mercure |
| **Stop reordered** | Recalcular polyline + timing. Publicar via Mercure |

### Servicio de gestion: RouteSnapshotManager

```php
namespace App\Service;

final class RouteSnapshotManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RoutingEngineInterface $routingEngine,
        private readonly RouteCapacityValidator $capacityValidator,
        private readonly RouteOptimizationService $optimizationService,
    ) {}

    /**
     * Crea o recrea el snapshot completo despues de build/optimize.
     * Llama a OSRM una sola vez para obtener la polyline.
     */
    public function createSnapshot(Route $route, ?float $distanceBeforeKm = null, ?array $originalStopOrder = null): RouteSnapshot;

    /**
     * Actualiza solo los stopStates (rapido, sin OSRM).
     */
    public function updateStopStates(Route $route): RouteSnapshot;

    /**
     * Regenera la polyline (despues de reordenar paradas).
     */
    public function refreshPolyline(Route $route): RouteSnapshot;

    /**
     * Genera la actualPolyline desde GPS trail (post-ruta).
     */
    public function generateActualPolyline(Route $route): RouteSnapshot;
}
```

### Integracion con eventos existentes

Los listeners existentes ya manejan eventos. Anadimos un nuevo listener:

```php
namespace App\EventListener\Domain;

final class RouteSnapshotListener
{
    public function __construct(
        private readonly RouteSnapshotManager $snapshotManager,
        private readonly RouteViewService $viewService,
        private readonly HubInterface $mercureHub,
    ) {}

    #[AsEventListener(event: StopDelivered::class)]
    public function onStopDelivered(StopDelivered $event): void
    {
        $snapshot = $this->snapshotManager->updateStopStates($event->getRoute());
        $this->publishRouteUpdate($event->getRoute());
    }

    #[AsEventListener(event: StopExceptionReported::class)]
    public function onStopException(StopExceptionReported $event): void
    {
        $snapshot = $this->snapshotManager->updateStopStates($event->getRoute());
        $this->publishRouteUpdate($event->getRoute());
    }

    #[AsEventListener(event: RouteCompleted::class)]
    public function onRouteCompleted(RouteCompleted $event): void
    {
        $this->snapshotManager->generateActualPolyline($event->getRoute());
        $this->publishRouteUpdate($event->getRoute());
    }

    private function publishRouteUpdate(Route $route): void
    {
        // Construir MapViewData para CADA rol activo y publicar en topic por rol
        // Topic: /routes/{publicId}/view/{role}
        // Payload: MapViewData serializado (JSON completo para re-render)
        foreach (['ROLE_ADMIN', 'ROLE_CUSTOMER', 'ROLE_DRIVER'] as $role) {
            $mapData = $this->viewService->buildSingleRouteView($route, $role);
            $update = new Update(
                sprintf('/routes/%s/view/%s', $route->getPublicIdString(), strtolower($role)),
                json_encode($mapData->toArray()),
            );
            $this->mercureHub->publish($update);
        }
    }
}
```

### Mercure topics para route view

```
/routes/{publicId}/view/role_admin      → MapViewData completo (metricas, logs, etc)
/routes/{publicId}/view/role_customer   → MapViewData filtrado (stops + status)
/routes/{publicId}/view/role_driver     → MapViewData filtrado (stops + navegacion)
```

El frontend se subscribe al topic de su rol y hace re-render completo al recibir un update.

---

## Capa 2: View Service — RouteViewService

Lee de RouteSnapshot (persistido). No hace llamadas OSRM.

```php
namespace App\View;

final class RouteViewService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteSnapshotRepository $snapshotRepo,
    ) {}

    /**
     * Vista de una sola ruta (customer show, driver show, analysis).
     * Lee de RouteSnapshot persistido.
     */
    public function buildSingleRouteView(Route $route, string $role, ?MapViewOptions $options = null): MapViewData;

    /**
     * Vista multi-ruta (planner, test-routing).
     * Lee de RouteSnapshot de cada ruta.
     */
    public function buildMultiRouteView(array $routes, string $role, ?MapViewOptions $options = null): MapViewData;

    /**
     * Serializa MapViewData para Mercure publish.
     * Aplica filtro de rol al serializar.
     */
    public function serializeForRole(MapViewData $data, string $role): array;
}
```

### MapViewOptions (config del controlador)

```php
namespace App\View;

final class MapViewOptions
{
    public function __construct(
        public readonly bool $showOptimizationMetrics = false,
        public readonly bool $showTimingBreakdown = false,
        public readonly bool $showVehicleTracking = false,
        public readonly bool $showStopStatus = true,
        public readonly bool $showCapacityValidation = false,
        public readonly bool $showOriginalOrder = false,
        public readonly bool $showPolylines = true,
        public readonly bool $showOptimizationLog = false,
        public readonly ?string $comparisonMode = null,         // 'planned_vs_actual'
        public readonly ?string $vehiclePublicId = null,
        public readonly ?array $vehiclePosition = null,
        public readonly ?array $optimizationLog = null,
    ) {}
}
```

### MapViewData (output)

```php
namespace App\View;

final class MapViewData
{
    public function __construct(
        public readonly array $routes,           // RouteViewData[]
        public readonly ?array $origin,          // {lat, lng, address}
        public readonly ?array $globalMetrics,   // {distanceBeforeKm, distanceAfterKm, savedPercent, ...}
        public readonly MapViewOptions $options,
        public readonly ?string $mercureTopic,   // topic Mercure para updates de esta vista
    ) {}

    public function toJson(): string;
    public function toArray(): array;
}
```

### RouteViewData

```php
namespace App\View;

final class RouteViewData
{
    public function __construct(
        public readonly string $publicId,
        public readonly string $name,
        public readonly string $color,
        public readonly ?string $vehicleName,
        public readonly ?string $driverName,
        public readonly ?string $status,
        public readonly array $stops,                // StopViewData[]
        public readonly ?string $polyline,           // desde RouteSnapshot (persistido)
        public readonly ?array $metrics,
        public readonly ?array $timing,
        public readonly ?array $validation,
        public readonly ?array $originalStops,       // desde RouteSnapshot (persistido)
        public readonly ?string $comparisonPolyline, // actualPolyline desde RouteSnapshot
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
        public readonly string $status,
        public readonly bool $isOrigin,
        public readonly ?string $deliveredAt,
        public readonly ?string $notes,
    ) {}
}
```

### Filtro por rol

| Campo | Admin | Customer | Driver |
|-------|-------|----------|--------|
| stops (all) | Si | Si | Si |
| stop.recipientPhone | Si | Si | Si |
| vehicle tracking | Si | Si | Si |
| polyline | Si | Si | Si |
| metrics (distance, savings) | Si | No | No |
| timing breakdown | Si | No | No |
| optimization log | Si | No | No |
| capacity validation | Si | No | No |
| original stop order | Si | No | No |
| comparison polyline | Si | No | No |
| stop.notes | Si | Si | Solo propios |

Si `options.showOptimizationMetrics = true` pero `role = ROLE_CUSTOMER`, el servicio ignora la feature (no error).

---

## Capa 3: Frontend — Twig Components + MxoRouteMap JS

### Estructura de archivos

```
templates/components/route/
    _map.html.twig              # Mapa Leaflet
    _map_js.html.twig           # Clase JS MxoRouteMap (1 vez por pagina)
    _metrics.html.twig          # Cards de metricas globales
    _stop_list.html.twig        # Tabla/lista de paradas
    _route_card.html.twig       # Card detalle por ruta
    _optimization_log.html.twig # Panel log (refactor del existente)
```

### MxoRouteMap JS — features

- Init Leaflet con OSM tiles
- Render N rutas con polylines (colores por indice)
- Decode polylines encoded (Google/OSRM format)
- Fallback lineas rectas si no hay polyline
- Marcadores de paradas (numerados, color por status)
- Marcador de origen (verde "O")
- Toggle visibilidad por ruta
- Marcador de vehiculo + tracking Mercure
- Polyline comparacion (planned vs actual)
- Flechas de direccion (polyline decorator)
- Fit bounds automatico
- **`update(newData)`** — metodo para re-render completo con nuevos datos (recibidos via Mercure)

### Mercure integration en el frontend

```javascript
class MxoRouteMap {
    constructor(elementId, data) {
        this.data = data;
        this.init();
        if (data.mercureTopic) {
            this.subscribeMercure(data.mercureTopic);
        }
    }

    subscribeMercure(topic) {
        // Fetch token, create EventSource
        fetch('/api/mercure-token', { credentials: 'include' })
            .then(res => res.json())
            .then(tokenData => {
                const hub = new URL(this.data.mercureUrl);
                hub.searchParams.set('topic', topic);
                if (tokenData.token) {
                    hub.searchParams.set('authorization', tokenData.token);
                }
                const es = new EventSource(hub);
                es.onmessage = (e) => {
                    const newData = JSON.parse(e.data);
                    this.update(newData);  // re-render completo
                };
            });
    }

    update(newData) {
        // Reemplaza datos y re-renderiza mapa + componentes
        this.data = { ...this.data, ...newData };
        this.clearLayers();
        this.renderRoutes();
        this.renderOrigin();
        this.fitBounds();
        // Emitir evento custom para que componentes Twig externos se actualicen
        document.dispatchEvent(new CustomEvent('mxo:route-updated', {
            detail: { mapId: this.elementId, data: this.data }
        }));
    }
}
```

Los componentes fuera del mapa (metrics, stop list, route card) escuchan `mxo:route-updated` y se actualizan via Alpine.js `x-data` reactivo o re-render manual.

### Constantes compartidas (en _map_js.html.twig)

```javascript
MxoRouteMap.COLORS = {
    routes: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'],
    stopStatus: {
        PENDING: '#3b82f6', DELIVERED: '#22c55e',
        EXCEPTION: '#ef4444', SKIPPED: '#6b7280',
    },
    origin: '#059669',
    vehicle: '#1e3a8a',
    originalRoute: '#ef4444',
    comparisonActual: '#ef4444',
    comparisonPlanned: '#3b82f6',
};
```

---

## Ejemplo de uso completo: customer/route/show

### Controller

```php
public function show(string $publicId): Response
{
    $route = $this->routeRepo->findOneByPublicId($publicId);
    $vehicle = $route->getVehicle();

    $mapView = $this->routeViewService->buildSingleRouteView(
        $route,
        'ROLE_CUSTOMER',
        new MapViewOptions(
            showVehicleTracking: true,
            showStopStatus: true,
            vehiclePublicId: $vehicle?->getPublicIdString(),
            vehiclePosition: $this->getVehiclePosition($vehicle),
        ),
    );

    return $this->render('customer/route/show.html.twig', [
        'route' => $route,
        'mapView' => $mapView,
    ]);
}
```

### Template

```twig
{% extends 'base.html.twig' %}

{% block content %}
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
    <div class="lg:col-span-2">
        {% include 'components/route/_stop_list.html.twig' with {
            stops: mapView.routes[0].stops,
            showStatus: true,
            listenUpdates: mapView.mercureTopic
        } %}
    </div>
    <div class="lg:col-span-3">
        {% include 'components/route/_map.html.twig' with {
            mapId: 'route-map',
            mapData: mapView,
            height: '600px'
        } %}
    </div>
</div>
{% endblock %}

{% block scripts %}
{{ parent() }}
{% include 'components/route/_map_js.html.twig' %}
{% endblock %}
```

### Flujo en tiempo real

1. Driver marca parada como entregada (POST /api/driver/stops/{id}/deliver)
2. `StopDelivered` event se despacha
3. `MercureRouteProgressListener` publica progreso (existente)
4. **`RouteSnapshotListener`** actualiza stopStates en RouteSnapshot
5. **`RouteSnapshotListener`** publica MapViewData completo en `/routes/{id}/view/role_customer`
6. Frontend del customer recibe el evento Mercure
7. `MxoRouteMap.update()` re-renderiza el mapa con la parada en verde
8. `mxo:route-updated` event actualiza la lista de paradas

---

## Plan de migracion

### Fase 1: Infraestructura (sin cambiar vistas existentes)
1. Crear entidad `RouteSnapshot` + migracion
2. Crear `RouteSnapshotManager` (create, updateStopStates, refreshPolyline)
3. Integrar `RouteSnapshotManager` en `RouteBuilder.buildRoutes()` y `RoutePlanningService.optimizeRoute()`
4. Crear `RouteSnapshotListener` (eventos → update snapshot → publish Mercure)
5. Crear DTOs: `MapViewOptions`, `MapViewData`, `RouteViewData`, `StopViewData`
6. Crear `RouteViewService` (lee de snapshot, filtra por rol)

### Fase 2: Frontend components (sin migrar vistas aun)
7. Crear `_map_js.html.twig` con clase `MxoRouteMap`
8. Crear `_map.html.twig`, `_metrics.html.twig`, `_stop_list.html.twig`, `_route_card.html.twig`
9. Crear `_optimization_log.html.twig` (refactor del existente)

### Fase 3: Migracion de vistas (una por una)
10. Migrar `customer/route/show` — mas simple, 1 ruta, status, tracking
11. Migrar `driver/routes/show` — casi identica
12. Migrar `test-routing/map` — multi-ruta con metricas
13. Migrar `route_planner step 3` — mas complejo (Alpine.js + driver suggestions)
14. Migrar `admin/route/analysis` — comparison mode

Cada paso: commit independiente, tests, verificacion visual.

---

## Archivos nuevos

```
src/Entity/RouteSnapshot.php
src/Repository/RouteSnapshotRepository.php
src/Service/RouteSnapshotManager.php
src/EventListener/Domain/RouteSnapshotListener.php
src/View/RouteViewService.php
src/View/MapViewOptions.php
src/View/MapViewData.php
src/View/RouteViewData.php
src/View/StopViewData.php
templates/components/route/_map.html.twig
templates/components/route/_map_js.html.twig
templates/components/route/_metrics.html.twig
templates/components/route/_stop_list.html.twig
templates/components/route/_route_card.html.twig
templates/components/route/_optimization_log.html.twig
migrations/VersionXXX_route_snapshot.php
```

## Archivos modificados

```
src/Service/RouteBuilder.php                        — crear snapshot despues de build
src/Application/Route/RoutePlanningService.php      — crear snapshot despues de optimize
templates/customer/route/show.html.twig             — usar componentes
templates/driver/routes/show.html.twig              — usar componentes
templates/admin/test-routing/map.html.twig          — usar componentes
templates/admin/route_planner/index.html.twig       — step 3 usa componentes
templates/admin/route/analysis.html.twig            — usar componentes
+ controllers correspondientes
```
