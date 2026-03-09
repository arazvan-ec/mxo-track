# Plan de Ejecución: Fase 1 — Routing Port (OSRM)

## Contexto

Extraer `OsrmClient` en un puerto `RoutingEngineInterface` siguiendo el patrón de `GeocoderInterface`. Solo 2 consumidores: `EtaService` y `RouteOptimizationService`. Menor superficie posible, establece el patrón DDD para fases 2-6.

## Archivos críticos existentes

| Archivo | Rol actual |
|---------|-----------|
| `src/Service/OsrmClient.php` | Cliente HTTP concreto OSRM. Métodos: `getRoute()`, `getRouteWithWaypoints()` |
| `src/Service/EtaService.php` | Consume `getRoute()` — usa `durationSeconds` y `distanceKm` |
| `src/Service/RouteOptimizationService.php` | Consume `getRoute()` (3 calls) y `getRouteWithWaypoints()` (2 calls) |
| `config/services.yaml` | Wiring: `OsrmClient: arguments: $osrmUrl: '%env(OSRM_URL)%'` |

## Patrón de referencia (Geocoding)

- Interfaz en namespace root: `App\Geocoding\GeocoderInterface`
- VOs como `final readonly class` con constructor promotion
- Adaptador `final class` con `implements`
- Null adapter con `LoggerInterface`
- Alias en services.yaml: `Interface: alias: ConcreteAdapter`

---

## Plan de commits atómicos (5 commits)

### Commit 1: Value Objects
Crear 3 value objects sin dependencias:

| Archivo nuevo | Contenido |
|--------------|-----------|
| `src/Routing/Coordinate.php` | `final readonly class { float $latitude, float $longitude }` |
| `src/Routing/RouteResult.php` | `final readonly class { float $distanceKm, float $durationSeconds }` |
| `src/Routing/MultiWaypointRouteResult.php` | `final readonly class { float $totalDistanceKm, float $totalDurationSeconds, list<RouteResult> $legs }` |

### Commit 2: Port Interface
| Archivo nuevo | Contenido |
|--------------|-----------|
| `src/Routing/RoutingEngineInterface.php` | `route(fromLat, fromLng, toLat, toLng): RouteResult` + `routeWithWaypoints(list<Coordinate>): MultiWaypointRouteResult` |

### Commit 3: Adapters
| Archivo nuevo | Contenido |
|--------------|-----------|
| `src/Routing/OsrmRoutingEngine.php` | `final class implements RoutingEngineInterface`. Copia lógica HTTP de `OsrmClient`. Encapsula `[lng,lat]` order. Timeouts 10s/15s. Retorna VOs en vez de arrays. |
| `src/Routing/NullRoutingEngine.php` | `final class implements RoutingEngineInterface`. Retorna `RouteResult(0,0)`. Log debug via `LoggerInterface`. |

### Commit 4: Migrate consumers
| Archivo modificado | Cambios |
|-------------------|---------|
| `src/Service/EtaService.php` | Constructor: `OsrmClient` → `RoutingEngineInterface`. Array access → property access (`$result['distanceKm']` → `$result->distanceKm`). |
| `src/Service/RouteOptimizationService.php` | Ídem. Además convertir `list<array{lat,lng}>` → `list<Coordinate>` para `routeWithWaypoints()`. |

### Commit 5: Wire + deprecate
| Archivo modificado | Cambios |
|-------------------|---------|
| `config/services.yaml` | Añadir bloque `# Routing (Ports & Adapters)` con `OsrmRoutingEngine` args + alias `RoutingEngineInterface → OsrmRoutingEngine` |
| `src/Service/OsrmClient.php` | Marcar `@deprecated`, delegar internamente a `OsrmRoutingEngine` |

---

## Verificación

1. `php -l` en todos los archivos nuevos de `src/Routing/`
2. `make lint` — debe pasar sin errores
3. Grep: `EtaService` y `RouteOptimizationService` ya no importan `OsrmClient`
4. `services.yaml` tiene alias `RoutingEngineInterface → OsrmRoutingEngine`
