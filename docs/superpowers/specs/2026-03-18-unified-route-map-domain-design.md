# Unified Route Map Domain — Design Spec

**Date:** 2026-03-18
**Bounded Context:** Route Planning (critico — DDD puro)
**Status:** Approved

## Problem

3 backend services query RouteSnapshot + stops independently, producing 3 different JSON structures:

| Service | Query method | JSON naming | Fields included |
|---------|-------------|-------------|-----------------|
| `RouteViewService` | Repository + role filtering | camelCase | Full (polyline, metrics, timing, originalStops) |
| `FleetOverviewService` | DQL ad-hoc + snapshot map | snake_case | Partial (polyline, stops, status) |
| `RoutePlanningService` | `snapshotManager->findByRoute()` | camelCase | Partial (polyline, stops, validation) |

Additionally, `RouteSnapshot` lives in `src/Entity/` (pragmatic) but Route Planning is a critical bounded context that requires DDD placement.

## Solution: Approach A — Domain Service + Value Objects

### 1. Migrate RouteSnapshot to Domain Layer

**Before:**
```
src/Entity/RouteSnapshot.php          (ORM attributes inline)
src/Repository/RouteSnapshotRepository.php
```

**After:**
```
src/Domain/Route/Model/RouteSnapshot.php           (POPO, no ORM)
src/Domain/Route/Repository/RouteSnapshotRepositoryInterface.php
src/Infrastructure/Route/Doctrine/DoctrineRouteSnapshotRepository.php
config/doctrine/mapping/Route.RouteSnapshot.orm.xml  (external mapping)
```

RouteSnapshot becomes a pure domain model:
- No `#[ORM\...]` attributes
- All persistence via external XML mapping
- Repository interface in domain, implementation in infrastructure

### 2. Create RouteMapProjection Domain Service

```php
// src/Domain/Route/Service/RouteMapProjection.php
final readonly class RouteMapProjection
{
    public function __construct(
        private RouteSnapshotRepositoryInterface $snapshotRepo,
    ) {}

    public function projectRoute(Route $route, RouteMapOptions $options): RouteMapView { ... }

    /** @return list<RouteMapView> */
    public function projectRoutes(array $routes, RouteMapOptions $options): array { ... }
}
```

This replaces the duplicated query+transform logic in FleetOverviewService, RoutePlanningService, and RouteViewService.

### 3. Value Objects

**RouteMapView** — unified route representation for all map consumers:

```php
// src/Domain/Route/Model/RouteMapView.php
final readonly class RouteMapView
{
    public function __construct(
        public string $publicId,
        public string $name,
        public string $color,
        public ?string $polyline,
        public array $stops,              // list<StopMapView>
        public ?string $status,
        public ?string $vehicleName,
        public ?string $driverName,
        public ?RouteMapMetrics $metrics, // null if not requested
        public ?RouteMapTiming $timing,
        public ?array $validation,
        public ?string $comparisonPolyline,
        public ?array $originalStops,     // list<StopMapView>
    ) {}

    public function toArray(): array { /* filters nulls, camelCase */ }
}
```

**StopMapView** — unified stop representation:

```php
// src/Domain/Route/Model/StopMapView.php
final readonly class StopMapView
{
    public function __construct(
        public int $sequence,
        public string $address,
        public ?float $lat,
        public ?float $lng,
        public string $status,
        public bool $isOrigin,
        public ?string $recipientName,
        public ?string $recipientPhone,
        public ?string $deliveredAt,
        public ?string $exceptionCode,
        public ?int $etaMinutes,
        public ?string $etaTime,
        public ?float $etaDistanceKm,
    ) {}

    public function toArray(): array { /* filters nulls, camelCase */ }
}
```

**RouteMapMetrics** — optimization metrics:

```php
// src/Domain/Route/Model/RouteMapMetrics.php
final readonly class RouteMapMetrics
{
    public function __construct(
        public ?float $distanceBeforeKm,
        public ?float $distanceAfterKm,
        public ?float $savingsPercent,
    ) {}
}
```

**RouteMapTiming:**

```php
// src/Domain/Route/Model/RouteMapTiming.php
final readonly class RouteMapTiming
{
    public function __construct(
        public ?int $drivingTimeMinutes,
        public ?int $deliveryTimeMinutes,
        public ?int $totalTimeMinutes,
    ) {}
}
```

**RouteMapOptions** — controls what data to include:

```php
// src/Domain/Route/Model/RouteMapOptions.php
final readonly class RouteMapOptions
{
    public function __construct(
        public bool $includeMetrics = false,
        public bool $includeTiming = false,
        public bool $includeValidation = false,
        public bool $includeOriginalStops = false,
        public bool $includeComparisonPolyline = false,
        public bool $includeEtas = false,
    ) {}

    public static function full(): self { return new self(true, true, true, true, true, true); }
    public static function minimal(): self { return new self(); }
}
```

### 4. Refactor Consumers

**FleetOverviewService** — replace ad-hoc query with:
```php
$projection = $this->routeMapProjection->projectRoutes($activeRoutes, RouteMapOptions::minimal());
// Use $projection[i]->toArray() directly in response
```

**RoutePlanningService** — replace ad-hoc query with:
```php
$projection = $this->routeMapProjection->projectRoute($route, RouteMapOptions::minimal());
// Include polyline from projection
```

**RouteViewService** — replace internal logic with:
```php
$options = $this->buildOptionsFromRole($role, $mapViewOptions);
$projection = $this->routeMapProjection->projectRoute($route, $options);
// Wrap in MapViewData
```

### 5. JSON Structure (camelCase, unified)

All endpoints produce the same shape for route data:

```json
{
  "publicId": "01ABCD...",
  "name": "Ruta 1 - 18/03/2026",
  "color": "#3B82F6",
  "polyline": "encoded_osrm_polyline...",
  "status": "ACTIVE",
  "vehicleName": "Camión 1",
  "driverName": "Juan",
  "stops": [
    {
      "sequence": 0,
      "address": "Calle Mayor 1",
      "lat": 40.4168,
      "lng": -3.7038,
      "status": "PENDING",
      "isOrigin": true,
      "recipientName": null
    }
  ],
  "metrics": { "distanceBeforeKm": 45.2, "distanceAfterKm": 32.1, "savingsPercent": 29.0 },
  "timing": { "drivingTimeMinutes": 45, "deliveryTimeMinutes": 30, "totalTimeMinutes": 75 }
}
```

Fields not requested via options are omitted (not null).

### 6. Frontend Impact

The frontend `FleetRoute` type currently uses snake_case. After unification:
- `FleetRoute` changes to camelCase (matching all other types)
- `FleetMapPage`, `FleetSidebar`, `FleetMap` update field references
- All types share the same base structure from `RouteMapView.toArray()`

### 7. Files Changed

**New files (Domain):**
- `src/Domain/Route/Model/RouteSnapshot.php` (POPO)
- `src/Domain/Route/Model/RouteMapView.php`
- `src/Domain/Route/Model/StopMapView.php`
- `src/Domain/Route/Model/RouteMapMetrics.php`
- `src/Domain/Route/Model/RouteMapTiming.php`
- `src/Domain/Route/Model/RouteMapOptions.php`
- `src/Domain/Route/Repository/RouteSnapshotRepositoryInterface.php`
- `src/Domain/Route/Service/RouteMapProjection.php`

**New files (Infrastructure):**
- `src/Infrastructure/Route/Doctrine/DoctrineRouteSnapshotRepository.php`
- `config/doctrine/mapping/Route.RouteSnapshot.orm.xml`

**Modified files:**
- `src/Application/Fleet/FleetOverviewService.php` — use RouteMapProjection
- `src/Application/Route/RoutePlanningService.php` — use RouteMapProjection
- `src/View/RouteViewService.php` — delegate to RouteMapProjection
- `src/Service/RouteSnapshotManager.php` — use domain interface
- `src/EventListener/Domain/RouteSnapshotListener.php` — use domain interface
- `config/packages/doctrine.yaml` — add XML mapping path

**Deleted files:**
- `src/Entity/RouteSnapshot.php` (replaced by domain model)
- `src/Repository/RouteSnapshotRepository.php` (replaced by infrastructure impl)

**Frontend modified:**
- `src/api/types.ts` — FleetRoute to camelCase
- `src/components/maps/FleetMap.tsx` — camelCase fields
- `src/components/fleet/FleetSidebar.tsx` — camelCase fields
- `src/components/fleet/VehiclePopup.tsx` — if uses route fields
- `src/pages/admin/FleetMapPage.tsx` — camelCase fields

### 8. Migration Strategy

No DB migration needed — the table structure doesn't change. Only the ORM mapping source changes from PHP attributes to XML.

### 9. Risk Assessment

- **Low risk:** Value objects are new code, no existing behavior changes
- **Medium risk:** RouteSnapshot migration (ORM attributes → XML). Mitigated by running tests after migration.
- **Low risk:** Frontend camelCase migration — TypeScript compiler catches all mismatches
