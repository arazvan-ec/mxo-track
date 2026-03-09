# Plan de Ejecución: Fase 2 — Route Optimization Port (VROOM)

## Contexto

Extraer la integración VROOM en un puerto `RouteOptimizerInterface` siguiendo el patrón de `GeocoderInterface` y `RoutingEngineInterface` (Fase 1). VROOM tiene 3 clases acopladas: `VroomApiClient`, `VroomRequestMapper`, `VroomResponseMapper`. 2 consumidores: `RouteBuilder` y `RouteOptimizationService`.

## Archivos críticos existentes

| Archivo | Rol | VROOM-specifics |
|---------|-----|-----------------|
| `src/Service/VroomApiClient.php` | HTTP POST a VROOM Express | URL, timeout 30s, code check |
| `src/Service/VroomRequestMapper.php` | Domain → VROOM format | `[lng,lat]` coords, `[grams,cm³,parcels]` capacity, priority 0-100 |
| `src/Service/VroomResponseMapper.php` | VROOM response → Route/RouteStop entities | Creates entities, distance m→km |
| `src/Service/RouteBuilder.php` | Orquesta build: mapper→client→response | Inyecta las 3 clases VROOM |
| `src/Service/RouteOptimizationService.php` | Re-optimiza orden de paradas | Construye request VROOM inline |

## Diseño del puerto

**Decisión clave**: El puerto SOLO maneja la optimización matemática (asignación + secuenciación). La creación de entidades (Route, RouteStop) se queda en `RouteBuilder`.

```
Domain entities → [RouteBuilder convierte a VOs] → RouteOptimizerInterface.optimize() → OptimizationResult → [RouteBuilder crea entities]
```

## Plan de commits (6 commits atómicos)

### Commit 1: Value Objects (5 archivos nuevos)

| Archivo | Propiedades |
|---------|-------------|
| `src/RouteOptimization/OptimizableVehicle.php` | `id: int\|string`, `startLat/Lng: ?float`, `endLat/Lng: ?float`, `maxWeightKg: ?float`, `maxVolumeM3: ?float`, `maxParcels: ?int`, `maxTasks: ?int`, `skills: list<string>` |
| `src/RouteOptimization/OptimizableJob.php` | `id: int\|string`, `latitude: float`, `longitude: float`, `serviceTimeSeconds: int`, `weightKg: float`, `volumeM3: float`, `parcels: int`, `priority: int`, `timeWindows: list<array{start: int, end: int}>`, `requiredSkills: list<string>` |
| `src/RouteOptimization/OptimizationResult.php` | `routes: list<OptimizedRoute>`, `unassignedJobIds: list<int\|string>` |
| `src/RouteOptimization/OptimizedRoute.php` | `vehicleId: int\|string`, `steps: list<OptimizedStep>`, `distanceMeters: int`, `durationSeconds: int` |
| `src/RouteOptimization/OptimizedStep.php` | `jobId: int\|string`, `type: string` (job/start/end), `arrivalSeconds: int`, `serviceSeconds: int` |

### Commit 2: Port Interface

| Archivo | Métodos |
|---------|---------|
| `src/RouteOptimization/RouteOptimizerInterface.php` | `optimize(list<OptimizableVehicle>, list<OptimizableJob>): OptimizationResult` |

### Commit 3: VroomRouteOptimizer adapter

| Archivo | Contenido |
|---------|-----------|
| `src/RouteOptimization/VroomRouteOptimizer.php` | Absorbe `VroomApiClient` HTTP + `VroomRequestMapper` conversiones. Constructor: `HttpClientInterface, LoggerInterface, string $vroomUrl`. Encapsula: `[lng,lat]`, kg→grams, m³→cm³, priority mapping, response parsing. |
| `src/RouteOptimization/NullRouteOptimizer.php` | Retorna result vacío con todos los jobs como unassigned. |

### Commit 4: Migrate RouteBuilder

Cambios en `src/Service/RouteBuilder.php`:
- Constructor: quitar `VroomApiClient`, `VroomRequestMapper`. Añadir `RouteOptimizerInterface`.
- Método `buildRoutes()`: convertir `Vehicle`→`OptimizableVehicle`, `Shipment`→`OptimizableJob`, llamar `optimizer->optimize()`, luego usar `VroomResponseMapper` refactorizado (o crear entities inline desde `OptimizationResult`).
- **VroomResponseMapper**: mantener temporalmente para entity creation, pero alimentar con `OptimizationResult` en vez de raw VROOM JSON.

### Commit 5: Migrate RouteOptimizationService

Cambios en `src/Service/RouteOptimizationService.php`:
- Constructor: quitar `VroomApiClient`. Añadir `RouteOptimizerInterface`.
- `optimizeStopOrder()`: construir `OptimizableVehicle` + `OptimizableJob` desde `RouteStop` entities, llamar `optimizer->optimize()`, parsear `OptimizationResult` en vez de raw VROOM response.

### Commit 6: Wire + deprecate

- `config/services.yaml`: añadir `VroomRouteOptimizer` con `$vroomUrl`, alias `RouteOptimizerInterface → VroomRouteOptimizer`. Eliminar entry de `VroomApiClient`.
- `src/Service/VroomApiClient.php`: marcar `@deprecated`
- `src/Service/VroomRequestMapper.php`: marcar `@deprecated`
- `src/Service/VroomResponseMapper.php`: marcar `@deprecated` (mantener funcional para transition)

## Verificación

1. `php -l` en cada archivo nuevo/modificado
2. `make lint` al final
3. `grep -r 'VroomApiClient\|VroomRequestMapper' backend/src/Service/RouteBuilder.php backend/src/Service/RouteOptimizationService.php` → solo `VroomResponseMapper` puede quedar temporalmente en RouteBuilder
4. Verificar alias en services.yaml
