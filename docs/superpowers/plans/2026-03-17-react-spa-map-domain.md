# Implementation Plan: React SPA + Map Domain DDD — Phase 1

**Date:** 2026-03-17
**Spec:** `docs/superpowers/specs/2026-03-17-react-spa-map-domain-design.md`
**Branch:** `claude/map-domain-reactive-routes-iXBRx`
**Goal:** Deliver a functional Fleet Map at `/app/admin/fleet-map` with MapLibre GL JS, Mercure SSE, and a clean MapView bounded context.

## Architecture

- **Backend:** Symfony 7.4, PHP 8.4, PostgreSQL 16, Mercure SSE
- **Frontend:** React 19, Vite 6, TypeScript, MapLibre GL JS, TanStack Query, Tailwind 4
- **Pattern:** DDD bounded context `MapView` with event consolidation

## File Structure

### Backend (new files)

```
src/Domain/MapView/
├── Model/
│   ├── MapUpdate.php
│   ├── MapUpdateType.php
│   └── VehiclePosition.php
├── Projection/
│   ├── MapProjectableEventInterface.php
│   └── MapProjectorInterface.php
└── Publisher/
    └── MapPublisherInterface.php

src/Infrastructure/MapView/
├── Projection/
│   └── MapEventProjector.php
├── Publisher/
│   └── MercureMapPublisher.php
└── Controller/
    ├── FleetMapDataController.php
    ├── MeController.php
    └── SpaController.php
```

### Backend (modified files)

```
src/Security/TopicResolver.php
src/EventListener/Domain/MercurePositionListener.php        → DELETE
src/EventListener/Domain/MercureRouteProgressListener.php   → DELETE
src/EventListener/Domain/RouteSnapshotListener.php          → REMOVE Mercure publishing
src/EventListener/Domain/EtaRecalculationListener.php       → REMOVE Mercure publishing
src/EventListener/Domain/RouteEventLogListener.php          → REMOVE Mercure publishing
templates/tracking/map.html.twig                            → UPDATE SSE topics
templates/components/route/_map_js.html.twig                → UPDATE SSE topics
```

### Frontend (new directory)

```
frontend/
├── package.json
├── tsconfig.json
├── vite.config.ts
├── index.html
└── src/
    ├── main.tsx
    ├── router.tsx
    ├── auth.tsx
    ├── api/
    │   ├── client.ts
    │   ├── types.ts
    │   └── hooks/
    │       ├── useMercure.ts
    │       ├── useMercurePositions.ts
    │       ├── useFleetMapData.ts
    │       └── useMe.ts
    ├── components/
    │   ├── layout/
    │   │   ├── AppShell.tsx
    │   │   └── Sidebar.tsx
    │   ├── maps/
    │   │   ├── FleetMap.tsx
    │   │   └── shared/
    │   │       ├── VehicleMarker.tsx
    │   │       ├── StopMarker.tsx
    │   │       ├── RouteLayer.tsx
    │   │       ├── OriginMarker.tsx
    │   │       ├── MapControls.tsx
    │   │       ├── useMapBounds.ts
    │   │       ├── polyline.ts
    │   │       └── colors.ts
    │   └── ui/
    │       ├── Loading.tsx
    │       └── Toast.tsx
    ├── pages/
    │   └── admin/
    │       └── FleetMapPage.tsx
    └── stores/
        └── uiStore.ts
```

---

## Tasks

### Task 1: Backend — MapView Domain Layer (VOs + Interfaces)

Create the domain layer with value objects and interfaces. No infrastructure dependencies.

**Files to create:**

- [ ] `backend/src/Domain/MapView/Model/MapUpdateType.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\MapView\Model;

enum MapUpdateType: string
{
    case StopDelivered = 'stop_delivered';
    case StopException = 'stop_exception';
    case RouteStarted = 'route_started';
    case RouteCompleted = 'route_completed';
    case RouteCancelled = 'route_cancelled';
    case RouteOptimized = 'route_optimized';
    case RouteAssigned = 'route_assigned';
    case RoutesBuilt = 'routes_built';
    case EtaChanged = 'eta_changed';
    case DeviationDetected = 'deviation_detected';
    case DeviationEnded = 'deviation_ended';
    case RouteSnapshot = 'route_snapshot';
}
```

- [ ] `backend/src/Domain/MapView/Model/MapUpdate.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\MapView\Model;

final readonly class MapUpdate
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public MapUpdateType $type,
        public string $routePublicId,
        public array $data,
        public \DateTimeImmutable $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'routePublicId' => $this->routePublicId,
            'data' => $this->data,
            'occurredAt' => $this->occurredAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

- [ ] `backend/src/Domain/MapView/Model/VehiclePosition.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\MapView\Model;

final readonly class VehiclePosition
{
    public function __construct(
        public string $vehiclePublicId,
        public float $lat,
        public float $lng,
        public ?float $speed,
        public ?float $course,
        public \DateTimeImmutable $deviceTime,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'vehiclePublicId' => $this->vehiclePublicId,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'speed' => $this->speed,
            'course' => $this->course,
            'deviceTime' => $this->deviceTime->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

- [ ] `backend/src/Domain/MapView/Projection/MapProjectableEventInterface.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\MapView\Projection;

interface MapProjectableEventInterface
{
    public function getRoutePublicId(): string;
    public function getOccurredAt(): \DateTimeImmutable;
}
```

- [ ] `backend/src/Domain/MapView/Projection/MapProjectorInterface.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\MapView\Projection;

use App\Domain\Event\VehiclePositionReceived;
use App\Domain\MapView\Model\MapUpdate;
use App\Domain\MapView\Model\VehiclePosition;

interface MapProjectorInterface
{
    /** @return list<MapUpdate> */
    public function projectRouteEvent(MapProjectableEventInterface $event): array;

    public function projectVehiclePosition(VehiclePositionReceived $event): VehiclePosition;
}
```

- [ ] `backend/src/Domain/MapView/Publisher/MapPublisherInterface.php`

```php
<?php
declare(strict_types=1);
namespace App\Domain\MapView\Publisher;

use App\Domain\MapView\Model\MapUpdate;
use App\Domain\MapView\Model\VehiclePosition;

interface MapPublisherInterface
{
    public function publishRouteUpdate(MapUpdate $update): void;
    public function publishVehiclePosition(VehiclePosition $position): void;
}
```

**Test:** `make lint` passes.
**Commit:** `feat: add MapView domain layer (VOs, interfaces)`

---

### Task 2: Backend — Add MapProjectableEventInterface to Domain Events

Add the `MapProjectableEventInterface` to the 12 route-related domain events (not `VehiclePositionReceived` which is handled separately, not `ShipmentsImported` which doesn't affect maps).

**Files to modify:** All events in `backend/src/Domain/Event/` that have a `routePublicId` property:
- `StopDelivered.php` — add `implements MapProjectableEventInterface`, add `getRoutePublicId()` and `getOccurredAt()` methods
- `StopExceptionReported.php`
- `RouteStarted.php`
- `RouteCompleted.php`
- `RouteCancelled.php`
- `RouteOptimized.php`
- `RouteAssigned.php`
- `RoutesBuilt.php`
- `EtaChanged.php`
- `DeviationDetected.php`
- `DeviationEnded.php`

Example modification for `StopDelivered`:
```php
use App\Domain\MapView\Projection\MapProjectableEventInterface;

final readonly class StopDelivered implements MapProjectableEventInterface
{
    // ... existing constructor ...

    public function getRoutePublicId(): string
    {
        return $this->routePublicId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
```

**Note:** `RoutesBuilt` may have multiple routes. Check its constructor — if it has a single `routePublicId`, implement normally. If it has multiple, use the first route or implement a `MapProjectableMultiRouteEventInterface`.

**Test:** `make lint` passes. `php vendor/bin/phpunit` passes (no behavioral change).
**Commit:** `feat: add MapProjectableEventInterface to domain events`

---

### Task 3: Backend — Implement MercureMapPublisher

Create the Mercure implementation that publishes to the 3 unified topics.

**File:** `backend/src/Infrastructure/MapView/Publisher/MercureMapPublisher.php`

```php
<?php
declare(strict_types=1);
namespace App\Infrastructure\MapView\Publisher;

use App\Domain\MapView\Model\MapUpdate;
use App\Domain\MapView\Model\VehiclePosition;
use App\Domain\MapView\Publisher\MapPublisherInterface;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;

final readonly class MercureMapPublisher implements MapPublisherInterface
{
    public function __construct(
        private RealtimePublisherInterface $publisher,
    ) {}

    public function publishRouteUpdate(MapUpdate $update): void
    {
        $this->publisher->publish(new SseMessage(
            data: $update->toArray(),
            topics: [sprintf('/map/routes/%s/updates', $update->routePublicId)],
            private: true,
        ));
    }

    public function publishVehiclePosition(VehiclePosition $position): void
    {
        $this->publisher->publish(new SseMessage(
            data: $position->toArray(),
            topics: [sprintf('/map/vehicles/%s/position', $position->vehiclePublicId)],
            private: true,
        ));
    }
}
```

**Key:** Uses `RealtimePublisherInterface` (already exists at `src/Realtime/RealtimePublisherInterface.php`) instead of `HubInterface` directly — fixing the D (SOLID) violation.

**Test:** Write unit test `tests/Unit/Infrastructure/MapView/Publisher/MercureMapPublisherTest.php` with mock `RealtimePublisherInterface`.
**Commit:** `feat: implement MercureMapPublisher with unified topics`

---

### Task 4: Backend — Implement MapEventProjector

Create the event projector that listens to all domain events and delegates to `MapPublisherInterface`.

**File:** `backend/src/Infrastructure/MapView/Projection/MapEventProjector.php`

This class:
1. Implements `MapProjectorInterface`
2. Has `#[AsEventListener]` methods for each domain event
3. Projects events to `MapUpdate` VOs
4. Publishes via `MapPublisherInterface`

**Dependencies:**
- `MapPublisherInterface` (to publish)
- `RouteViewService` (to build `route_snapshot` MapViewData — needed for snapshot-type updates)
- `RouteRepository` (to look up routes when needed)

**Listener methods:**
```php
#[AsEventListener]
public function onStopDelivered(StopDelivered $event): void
{
    $this->publishRouteUpdate($event, MapUpdateType::StopDelivered, [
        'stopPublicId' => $event->stopPublicId,
        'shipmentPublicId' => $event->shipmentPublicId,
        'podPublicId' => $event->podPublicId,
    ]);
}

// Similar for: onStopExceptionReported, onRouteStarted, onRouteCompleted, etc.

#[AsEventListener]
public function onVehiclePositionReceived(VehiclePositionReceived $event): void
{
    $position = $this->projectVehiclePosition($event);
    $this->publisher->publishVehiclePosition($position);
}
```

**Important:** The existing `RouteSnapshotListener` publishes full `MapViewData` for role-based views. The new projector publishes `MapUpdate` with type-specific data instead. For `route_snapshot` type (triggered after stop state changes), include the full `MapViewData.toArray()` in the data field.

**Test:** Write unit test with mocked `MapPublisherInterface`. Verify each event type produces correct `MapUpdate`.
**Commit:** `feat: implement MapEventProjector consolidating 6 listeners`

---

### Task 5: Backend — Remove Mercure Publishing from Old Listeners

Remove the Mercure publishing responsibility from the 6 existing listeners. Keep their persistence logic intact.

**Files to modify:**

1. **DELETE** `src/EventListener/Domain/MercurePositionListener.php` — fully replaced by MapEventProjector
2. **DELETE** `src/EventListener/Domain/MercureRouteProgressListener.php` — fully replaced
3. **MODIFY** `src/EventListener/Domain/RouteSnapshotListener.php`:
   - Remove `HubInterface` dependency from constructor
   - Remove `publishRouteViewUpdate()` private method
   - Keep `handleProgressEvent()` which calls `snapshotManager->updateStopStates()`
   - The persistence part stays; only the Mercure publish is removed
4. **MODIFY** `src/EventListener/Domain/EtaRecalculationListener.php`:
   - Remove `HubInterface` dependency
   - Remove `RouteViewService` dependency (if only used for Mercure publishing)
   - Remove methods that publish to Mercure (`publishDeviationAlert`, `publishRouteViewUpdate`)
   - Keep ETA calculation and event dispatching logic
5. **MODIFY** `src/EventListener/Domain/RouteEventLogListener.php`:
   - Remove `HubInterface` dependency
   - Remove Mercure publish call from `persistAndPublish()` — keep only `persist()`
   - Rename to `RouteEventPersistenceListener` for clarity
6. **KEEP** `src/EventListener/Domain/DeviationAlertListener.php` — only does persistence (no Mercure)

**Critical:** Search all constructor call sites before modifying:
```bash
grep -r "new MercurePositionListener\|new MercureRouteProgressListener\|new RouteSnapshotListener\|new EtaRecalculationListener\|new RouteEventLogListener" src/ tests/
```

**Test:** `php vendor/bin/phpunit` — all tests pass. No `ArgumentCountError`.
**Commit:** `refactor: remove Mercure publishing from old listeners (consolidated in MapEventProjector)`

---

### Task 6: Backend — Update TopicResolver

Update `src/Security/TopicResolver.php` to resolve the 3 new topic patterns instead of the old 6.

**Dependencies needed:** Add `RouteRepository` to resolve active route public IDs per customer/driver.

**New logic:**
```php
// ROLE_ADMIN: ['*'] (unchanged)

// ROLE_CUSTOMER:
$topics = [sprintf('/map/users/%s/notifications', $user->getId())];
foreach ($allowedVehiclePublicIds as $publicId) {
    $topics[] = sprintf('/map/vehicles/%s/position', $publicId);
}
// Add route-specific topics for customer's active routes
$customerRoutePublicIds = $this->routeRepo->findActiveRoutePublicIdsForCustomer($user->getCustomer());
foreach ($customerRoutePublicIds as $routePublicId) {
    $topics[] = sprintf('/map/routes/%s/updates', $routePublicId);
}

// ROLE_DRIVER:
$topics = [sprintf('/map/users/%s/notifications', $user->getId())];
foreach ($allowedVehiclePublicIds as $publicId) {
    $topics[] = sprintf('/map/vehicles/%s/position', $publicId);
}
$assignedRoutePublicIds = $this->routeRepo->findActiveRoutePublicIdsForDriver($user);
foreach ($assignedRoutePublicIds as $routePublicId) {
    $topics[] = sprintf('/map/routes/%s/updates', $routePublicId);
}
```

**Also update:** `RouteRepository` to add `findActiveRoutePublicIdsForCustomer()` and `findActiveRoutePublicIdsForDriver()` methods.

**Test:** Unit test `TopicResolver` with mocked repo. Verify each role gets correct topics.
**Commit:** `feat: update TopicResolver for unified /map/* topic patterns`

---

### Task 7: Backend — Adapt Twig JS to New Topics

Update the SSE connection logic in Twig templates to subscribe to the new topic patterns.

**File 1:** `templates/tracking/map.html.twig`
- Lines ~650+: `connectSse()` method
- Change topic construction:
  - `/vehicles/{id}/position` → `/map/vehicles/{id}/position`
  - `/routes/{id}/view/admin` → `/map/routes/{id}/updates`
  - Remove `/operator/fleet` (will be derived from route updates)
- Update message parsing: the new `/map/routes/{id}/updates` messages have `type` + `data` structure instead of full `MapViewData`. For `route_snapshot` type, `data` contains the full MapViewData.

**File 2:** `templates/components/route/_map_js.html.twig`
- `subscribeMercure()` method
- Change topic: `/routes/{id}/view/{role}` → `/map/routes/{id}/updates`
- `subscribeVehicle()` method
- Change topic: `/vehicles/{id}/position` → `/map/vehicles/{id}/position`

**Test:** Open fleet map and route detail in browser. Verify SSE connections use new topics (check Network tab).
**Commit:** `refactor: adapt Twig JS to unified /map/* Mercure topics`

---

### Task 8: Backend — API Endpoints

Create the 3 new API endpoints.

**File 1:** `backend/src/Infrastructure/MapView/Controller/MeController.php`
```php
#[Route('/api/me', name: 'api_me', methods: ['GET'])]
public function __invoke(): JsonResponse
{
    $user = $this->getUser();
    return $this->json([
        'id' => $user->getId(),
        'email' => $user->getEmail(),
        'role' => $user->getRoles()[0], // Primary role
        'customerId' => $user->getCustomer()?->getPublicIdString(),
        'customerName' => $user->getCustomer()?->getName(),
    ]);
}
```

**File 2:** `backend/src/Infrastructure/MapView/Controller/FleetMapDataController.php`
```php
#[Route('/api/fleet/map-data', name: 'api_fleet_map_data', methods: ['GET'])]
public function __invoke(FleetOverviewService $fleetService): JsonResponse
{
    $user = $this->getUser();
    $fleetData = $fleetService->getFleetMapData($user);
    return $this->json($fleetData);
}
```

**Note:** `FleetOverviewService::getFleetMapData()` already returns a structured array. Verify it's JSON-serializable.

**File 3:** `backend/src/Infrastructure/MapView/Controller/SpaController.php`
```php
#[Route('/app/{path}', name: 'spa_entrypoint', requirements: ['path' => '.*'], methods: ['GET'])]
public function __invoke(): Response
{
    $indexPath = $this->getParameter('kernel.project_dir') . '/public/app/index.html';
    if (!file_exists($indexPath)) {
        throw $this->createNotFoundException('SPA not built. Run: cd frontend && npm run build');
    }
    return new Response(file_get_contents($indexPath), 200, [
        'Content-Type' => 'text/html',
    ]);
}
```

**Test:** `make lint`. `curl localhost:8000/api/me` returns user JSON (with auth).
**Commit:** `feat: add API endpoints (GET /api/me, GET /api/fleet/map-data, SPA controller)`

---

### Task 9: Backend — Unit Tests

Write tests for the new backend code.

**Tests to create:**
1. `tests/Unit/Domain/MapView/Model/MapUpdateTest.php` — test `toArray()` serialization
2. `tests/Unit/Domain/MapView/Model/VehiclePositionTest.php` — test `toArray()` serialization
3. `tests/Unit/Infrastructure/MapView/Publisher/MercureMapPublisherTest.php` — mock `RealtimePublisherInterface`, verify correct topics and payload
4. `tests/Unit/Infrastructure/MapView/Projection/MapEventProjectorTest.php` — mock `MapPublisherInterface`, dispatch each event type, verify correct `MapUpdate` produced
5. `tests/Unit/Security/TopicResolverTest.php` — test all 4 roles produce correct topic lists

**Verify:** `php vendor/bin/phpunit` — all pass.
**Commit:** `test: add MapView domain and infrastructure tests`

---

### Task 10: Frontend — Scaffold

Create the `frontend/` directory with Vite, React, TypeScript, Tailwind 4.

**Commands:**
```bash
cd /home/user/mxo-track
mkdir frontend && cd frontend
npm create vite@latest . -- --template react-ts
npm install
npm install -D tailwindcss @tailwindcss/vite
npm install react-router @tanstack/react-query zustand zod
npm install maplibre-gl react-map-gl pmtiles
```

**Configure `vite.config.ts`:**
```typescript
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: '/app/',
  build: {
    outDir: '../backend/public/app',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://localhost:8000',
      '/login': 'http://localhost:8000',
      '/logout': 'http://localhost:8000',
    },
  },
});
```

**Configure `tsconfig.json`:** Strict mode, path aliases (`@/` → `src/`).

**Configure `index.html`:** Base template with `<div id="root">`.

**Configure `src/main.css`:** `@import "tailwindcss";`

**Add `frontend/` to backend `.gitignore`'s `node_modules` pattern if needed.

**Test:** `cd frontend && npm run build` — outputs to `backend/public/app/`.
**Commit:** `feat: scaffold React SPA frontend with Vite, TypeScript, Tailwind 4`

---

### Task 11: Frontend — API Client + Types

**File:** `frontend/src/api/client.ts`
```typescript
const API_BASE = '';

async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_BASE}${path}`, {
    credentials: 'same-origin',
    headers: { 'Accept': 'application/json', ...init?.headers },
    ...init,
  });
  if (!response.ok) {
    if (response.status === 401) {
      window.location.href = '/login';
      throw new Error('Unauthorized');
    }
    throw new Error(`API error: ${response.status}`);
  }
  return response.json();
}

export const api = {
  get: <T>(path: string) => apiFetch<T>(path),
  post: <T>(path: string, body: unknown) => apiFetch<T>(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  }),
};
```

**File:** `frontend/src/api/types.ts` — All TypeScript interfaces matching backend DTOs (as documented in spec Section 6.3).

**Test:** TypeScript compiles without errors.
**Commit:** `feat: add API client and TypeScript types`

---

### Task 12: Frontend — Mercure SSE Hooks

**File:** `frontend/src/api/hooks/useMercure.ts` — Generic SSE hook
**File:** `frontend/src/api/hooks/useMercurePositions.ts` — Vehicle position SSE
**File:** `frontend/src/api/hooks/useMe.ts` — Current user hook

The `useMercure` hook:
1. Fetches JWT from `/api/mercure-token` (via TanStack Query, `staleTime: 5 * 60 * 1000`)
2. Opens `EventSource` with topic subscriptions
3. Reconnects on error with exponential backoff (1s → 30s ceiling, max 10 retries)
4. Cleans up on unmount

The `useMercurePositions` hook:
1. Uses `useMercure` with `/map/vehicles/{id}/position` topics
2. Maintains a `Map<vehiclePublicId, VehiclePosition>` state
3. Returns the live positions map

**Test:** TypeScript compiles. Hook renders without errors in test component.
**Commit:** `feat: add Mercure SSE hooks (useMercure, useMercurePositions)`

---

### Task 13: Frontend — Map Utilities

**File:** `frontend/src/components/maps/shared/polyline.ts`
Port the polyline decoder from `_map_js.html.twig` lines 29-42. Convert decoded lat/lng pairs to GeoJSON `LineString` format for MapLibre.

**File:** `frontend/src/components/maps/shared/colors.ts`
Port the `COLORS` object from `_map_js.html.twig` lines 14-26.

**Test:** Unit test for `decodePolyline()` with known encoded input.
**Commit:** `feat: port polyline decoder and color constants to TypeScript`

---

### Task 14: Frontend — Map Components

**Components to create:**

1. `VehicleMarker.tsx` — Custom marker with vehicle icon rotated by `course` angle. Uses MapLibre `Marker`.
2. `StopMarker.tsx` — Numbered circle marker with status-based color (green=delivered, red=exception, blue=pending). Uses MapLibre `Marker`.
3. `RouteLayer.tsx` — GeoJSON `Source` + `Layer` for route polyline. Decodes encoded polyline and renders as colored line.
4. `OriginMarker.tsx` — Green "O" marker for route origin.
5. `MapControls.tsx` — Buttons for reset bounds, layer toggle.
6. `useMapBounds.ts` — Hook to auto-fit map bounds to all visible markers/polylines.

**Test:** Components render without errors.
**Commit:** `feat: create shared map components for MapLibre GL`

---

### Task 15: Frontend — Fleet Map + Fleet Map Page

**File:** `frontend/src/api/hooks/useFleetMapData.ts`
```typescript
export function useFleetMapData() {
  const query = useQuery({
    queryKey: ['fleet-map'],
    queryFn: () => api.get<FleetMapResponse>('/api/fleet/map-data'),
    refetchInterval: 60_000, // Fallback polling every 60s
  });

  const vehicleIds = query.data?.vehicles.map(v => v.public_id) ?? [];
  const livePositions = useMercurePositions(vehicleIds);

  return {
    vehicles: mergePositions(query.data?.vehicles ?? [], livePositions),
    routes: query.data?.routes ?? [],
    isLoading: query.isLoading,
    error: query.error,
  };
}
```

**File:** `frontend/src/components/maps/FleetMap.tsx`
Main fleet map component with:
- MapLibre `Map` with PMTiles style
- Vehicle markers (from `useFleetMapData`)
- Route polylines (decoded)
- Stop markers
- Auto-fit bounds
- Click handlers (select vehicle, select route)

**File:** `frontend/src/pages/admin/FleetMapPage.tsx`
Page wrapper with side panel:
- Vehicle list (filterable)
- Route list (filterable)
- Fleet KPIs summary
- Map occupies main area

**Test:** `npm run build` succeeds. Page renders at `/app/admin/fleet-map`.
**Commit:** `feat: create Fleet Map page with live vehicle tracking`

---

### Task 16: Frontend — App Shell + Router

**File:** `frontend/src/components/layout/AppShell.tsx`
Sidebar + topbar + content area. Sidebar shows role-appropriate navigation links.

**File:** `frontend/src/components/layout/Sidebar.tsx`
Role-aware navigation. Admin sees: Fleet Map, Routes, Vehicles, etc.

**File:** `frontend/src/auth.tsx`
Auth context that fetches `/api/me` on mount and provides user info to children.

**File:** `frontend/src/router.tsx`
React Router setup with routes under `/app/`:
- `/app/admin/fleet-map` → `FleetMapPage`
- Catch-all redirect to fleet map for now

**File:** `frontend/src/main.tsx`
Entry point: QueryClientProvider + AuthProvider + RouterProvider.

**File:** `frontend/src/stores/uiStore.ts`
Zustand store for sidebar open/close, selected vehicle/route.

**Test:** `npm run build`. Navigate to `/app/admin/fleet-map` — shows app shell with fleet map.
**Commit:** `feat: create App Shell with role-aware sidebar and router`

---

### Task 17: Verification

Run full verification suite:

```bash
# Backend
cd backend && make lint
cd backend && php vendor/bin/phpunit

# Frontend
cd frontend && npm run build
cd frontend && npx tsc --noEmit

# Integration
# 1. Start Symfony: cd backend && php -S localhost:8000 -t public
# 2. Navigate to /app/admin/fleet-map
# 3. Verify: map renders, vehicles show, SSE connects (Network tab)
# 4. Verify: Twig fleet map at /admin/tracking/map still works
# 5. Verify: /api/me returns user JSON
# 6. Verify: /api/fleet/map-data returns fleet data JSON
```

**Commit:** Any final fixes.

---

## Verification Checklist

- [ ] `cd backend && make lint` — PHP syntax OK
- [ ] `cd backend && php vendor/bin/phpunit` — all tests pass
- [ ] `cd frontend && npm run build` — builds to `backend/public/app/`
- [ ] `cd frontend && npx tsc --noEmit` — TypeScript compiles
- [ ] Fleet map at `/app/admin/fleet-map` renders with MapLibre GL
- [ ] Mercure SSE connects to `/map/vehicles/*/position` topics
- [ ] Vehicle positions update in real-time
- [ ] Twig fleet map at old URL still works with new topics
- [ ] `/api/me` returns current user info
- [ ] `/api/fleet/map-data` returns fleet data
- [ ] Old listeners no longer publish to Mercure (only MapEventProjector does)
- [ ] TopicResolver produces correct topics for all roles
