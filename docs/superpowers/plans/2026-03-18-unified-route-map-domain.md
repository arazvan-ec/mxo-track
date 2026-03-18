# Plan: Unified Route Map Domain

**Spec:** `docs/superpowers/specs/2026-03-18-unified-route-map-domain-design.md`
**Goal:** Migrate RouteSnapshot to DDD domain, create RouteMapProjection service, unify JSON output across all map endpoints.

## Architecture

```
Domain Layer (src/Domain/Route/)
  Model/RouteSnapshot.php          ← POPO (migrated from Entity)
  Model/RouteMapView.php           ← Value Object
  Model/StopMapView.php            ← Value Object
  Model/RouteMapMetrics.php        ← Value Object
  Model/RouteMapTiming.php         ← Value Object
  Model/RouteMapOptions.php        ← Value Object
  Repository/RouteSnapshotRepositoryInterface.php
  Service/RouteMapProjection.php   ← Domain Service

Infrastructure Layer (src/Infrastructure/Route/)
  Doctrine/DoctrineRouteSnapshotRepository.php

Config
  config/doctrine/mapping/Route.RouteSnapshot.orm.xml
```

## Tasks

### Phase 1: Domain Value Objects

- [ ] **Task 1: Create RouteMapOptions VO**
  - File: `backend/src/Domain/Route/Model/RouteMapOptions.php`
  - POPO with boolean flags + static factories `full()` and `minimal()`

- [ ] **Task 2: Create RouteMapMetrics VO**
  - File: `backend/src/Domain/Route/Model/RouteMapMetrics.php`
  - Readonly: distanceBeforeKm, distanceAfterKm, savingsPercent
  - `toArray()` method

- [ ] **Task 3: Create RouteMapTiming VO**
  - File: `backend/src/Domain/Route/Model/RouteMapTiming.php`
  - Readonly: drivingTimeMinutes, deliveryTimeMinutes, totalTimeMinutes
  - `toArray()` method

- [ ] **Task 4: Create StopMapView VO**
  - File: `backend/src/Domain/Route/Model/StopMapView.php`
  - All stop fields, `toArray()` filtering nulls, camelCase keys
  - Static factory `fromSnapshotState(array $state, ?array $etaData): self`

- [ ] **Task 5: Create RouteMapView VO**
  - File: `backend/src/Domain/Route/Model/RouteMapView.php`
  - All route fields including list<StopMapView>
  - `toArray()` filtering nulls, camelCase keys
  - Commit & push

### Phase 2: Migrate RouteSnapshot to Domain

- [ ] **Task 6: Create RouteSnapshotRepositoryInterface**
  - File: `backend/src/Domain/Route/Repository/RouteSnapshotRepositoryInterface.php`
  - Methods: `findByRoute(Route $route): ?RouteSnapshot`, `save(RouteSnapshot $snapshot): void`

- [ ] **Task 7: Create Domain RouteSnapshot POPO**
  - File: `backend/src/Domain/Route/Model/RouteSnapshot.php`
  - Copy fields from Entity, remove ALL `#[ORM\...]` attributes
  - Keep same getters/setters, constructor takes Route
  - Keep `touch()` method

- [ ] **Task 8: Create XML mapping**
  - File: `backend/config/doctrine/mapping/Route.RouteSnapshot.orm.xml`
  - Map all fields exactly as current attribute mapping
  - OneToOne to Route with cascade remove

- [ ] **Task 9: Update doctrine.yaml**
  - Add mapping for `App\Domain\Route\Model` namespace pointing to XML directory
  - Keep existing `App\Entity` mapping

- [ ] **Task 10: Create DoctrineRouteSnapshotRepository**
  - File: `backend/src/Infrastructure/Route/Doctrine/DoctrineRouteSnapshotRepository.php`
  - Implements `RouteSnapshotRepositoryInterface`
  - Extends `ServiceEntityRepository<RouteSnapshot>`
  - `findByRoute()` and `save()` methods

- [ ] **Task 11: Delete old Entity + Repository**
  - Delete `src/Entity/RouteSnapshot.php`
  - Delete `src/Repository/RouteSnapshotRepository.php`
  - Verify: `php bin/console doctrine:schema:validate`
  - Commit & push

### Phase 3: RouteMapProjection Domain Service

- [ ] **Task 12: Create RouteMapProjection**
  - File: `backend/src/Domain/Route/Service/RouteMapProjection.php`
  - Dependencies: `RouteSnapshotRepositoryInterface`
  - `projectRoute(Route $route, RouteMapOptions $options): RouteMapView`
  - `projectRoutes(array $routes, RouteMapOptions $options): array`
  - Logic: fetch snapshot, build StopMapView[] from stopStates, assemble RouteMapView
  - Commit & push

### Phase 4: Refactor Consumers

- [ ] **Task 13: Update RouteSnapshotManager**
  - Change `RouteSnapshotRepository` → `RouteSnapshotRepositoryInterface`
  - Change `RouteSnapshot` import to domain model
  - Remove the `findByRoute()` delegation method (consumers use RouteMapProjection)

- [ ] **Task 14: Update RouteSnapshotListener**
  - Change imports to domain model + interface

- [ ] **Task 15: Refactor FleetOverviewService**
  - Inject `RouteMapProjection`
  - Replace ad-hoc snapshot query + array building with `projectRoutes()`
  - Route data uses `RouteMapView::toArray()` — camelCase output
  - Remove `RouteSnapshot` import

- [ ] **Task 16: Refactor RoutePlanningService**
  - Inject `RouteMapProjection`
  - After build, use `projectRoute()` to get polyline
  - Include `RouteMapView::toArray()` in response
  - Remove direct snapshot manager call for polyline
  - Commit & push

- [ ] **Task 17: Refactor RouteViewService**
  - Inject `RouteMapProjection`
  - Delegate route data building to `projectRoute()` with appropriate options
  - `RouteViewData` wraps `RouteMapView` or is replaced by it
  - Keep MapViewData as the container (adds Mercure, origin, globalMetrics)
  - Commit & push

### Phase 5: Frontend camelCase Migration

- [ ] **Task 18: Update FleetRoute type to camelCase**
  - `frontend/src/api/types.ts` — FleetRoute: publicId, vehicleName, driverName, etc.
  - FleetStop: keep as-is (already camelCase-compatible)

- [ ] **Task 19: Update FleetMapPage + FleetSidebar + FleetMap**
  - Change all `route.public_id` → `route.publicId`, etc.
  - Change `vehicle.public_id` → stays (FleetVehicle is separate API)

- [ ] **Task 20: Verify build & commit**
  - `npm run build` — TypeScript catches all mismatches
  - Run backend lint
  - Final commit & push

### Phase 6: Verification

- [ ] **Task 21: Run full test suite**
  - `php vendor/bin/phpunit`
  - `php bin/console doctrine:schema:validate`
  - `npm run build`

- [ ] **Task 22: Update knowledge module**
  - Update `docs/knowledge/domain-model.md` — RouteSnapshot location
  - Update `docs/knowledge/architecture-ddd.md` — Route Planning context progress
  - Add decision log entry
