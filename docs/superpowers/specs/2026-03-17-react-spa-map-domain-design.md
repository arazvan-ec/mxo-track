# Design Spec: React SPA + Map Domain DDD

**Date:** 2026-03-17
**Status:** Approved
**Branch:** `claude/map-domain-reactive-routes-iXBRx`

## 1. Problem Statement

The current frontend consists of 73 Twig templates with inline Alpine.js, minimal Turbo/Stimulus, and Leaflet for maps. There is no JS bundler — only Tailwind CLI. The 9 maps contain duplicated inline JS (~1500 LOC total). The business sells saved kilometers and time — maps are the core visualization of value.

**Backend issues:**
- 6 Mercure listeners are scattered, depend on `HubInterface` directly (violating Dependency Inversion)
- `EtaRecalculationListener` and `RouteSnapshotListener` both publish to `/routes/{id}/view/{role}` (duplication)
- `RealtimePublisherInterface` exists but none of the listeners use it
- No unified concept of "what events affect the map view"

**Frontend issues:**
- No component reuse between 9 maps
- No bundler means no tree-shaking, no TypeScript, no modern tooling
- Each map re-implements SSE connections, polyline decoding, marker creation
- Alpine.js data is coupled to Twig template structure

## 2. Goals

1. Create a React SPA at `/app/*` that coexists with Twig during incremental migration
2. Create a `MapView` DDD bounded context that consolidates domain event projection for maps
3. Unify 6 Mercure topic patterns into 3 conceptual topics
4. Deliver a functional Fleet Map as first value milestone
5. Fix SOLID violations in the Mercure publishing layer

## 3. Non-Goals

- Replace all Twig pages in this phase (only Fleet Map)
- Build a separate mobile app for drivers
- Implement CQRS with event sourcing
- Change the authentication system

## 4. Architecture Decisions

### 4.1 Coexistence Strategy: Path Prefix `/app/*`

The React SPA is served at `/app/*` via a Symfony catch-all controller. All existing Twig routes remain untouched. Migration happens link-by-link in the sidebar.

**Security:** `/app/*` is already covered by the catch-all `^/` → `IS_AUTHENTICATED_FULLY` rule in `security.yaml` (line 55).

**Rationale:** Zero conflict with existing routes. No need for Symfony Vite bundles or complex asset integration.

### 4.2 Authentication: Session Cookies (Same-Origin)

The SPA runs on the same domain, so the session cookie works automatically. Zero changes to the auth backend.

**Rationale:** Simplest approach. No JWT token management on the client. CSRF protection via standard Symfony mechanisms.

### 4.3 Map Domain: Bounded Context with Event Consolidation

**SOLID analysis of current state:**
- **S violation:** `EtaRecalculationListener` has 3 responsibilities (recalculate ETAs, detect deviations, publish to Mercure)
- **D violation:** All 6 listeners depend on `HubInterface` (Mercure concrete) instead of `RealtimePublisherInterface` (abstraction that already exists)
- **Duplication:** `EtaRecalculationListener` and `RouteSnapshotListener` both publish to `/routes/{id}/view/{role}`

**Solution:** Create `src/Domain/MapView/` bounded context with a single `MapEventProjector` that:
1. Listens to all 13 domain events
2. Projects them into `MapUpdate` value objects
3. Publishes via `MapPublisherInterface` (implemented by `MercureMapPublisher`)

Persistence logic (RouteSnapshot, RouteEvent, RealtimeEvent) stays in dedicated persistence listeners — only the Mercure publishing responsibility is consolidated.

### 4.4 Mercure Topics: 3 Unified Patterns

**Current (6 patterns):**
```
/vehicles/{id}/position
/customers/{id}/routes
/routes/{id}/view/{role}
/routes/{id}/deviation
/routes/{id}/events
/users/{id}/notifications
```

**New (3 patterns):**
```
/map/vehicles/{publicId}/position    — GPS positions (high frequency, 5-30s)
/map/routes/{publicId}/updates       — All route changes (type field discriminates)
/map/users/{userId}/notifications    — User notifications (unchanged structure)
```

The `/map/routes/{publicId}/updates` topic carries a `MapUpdate` message with a `type` discriminant:
```json
{
  "type": "stop_delivered|eta_changed|deviation_detected|route_started|route_completed|stop_exception|route_snapshot|route_optimized|route_assigned|route_cancelled|routes_built|deviation_ended",
  "routePublicId": "...",
  "data": { "...context-specific..." },
  "occurredAt": "2026-03-17T..."
}
```

**MapUpdate `data` schemas per type:**

| MapUpdateType | `data` keys |
|---------------|-------------|
| `stop_delivered` | `stopPublicId`, `shipmentPublicId`, `podPublicId` |
| `stop_exception` | `stopPublicId`, `reason` |
| `route_started` | (empty) |
| `route_completed` | (empty) |
| `route_cancelled` | (empty) |
| `route_optimized` | `metrics` (distance, duration, etc.) |
| `route_assigned` | `vehiclePublicId`, `driverPublicId` |
| `routes_built` | `routeCount`, `stopCount` |
| `eta_changed` | `stops` (array of {stopPublicId, etaMinutes, etaTime}) |
| `deviation_detected` | `lat`, `lng`, `distanceMeters`, `thresholdMeters` |
| `deviation_ended` | `lat`, `lng` |
| `route_snapshot` | Full `MapViewData.toArray()` (complete route view refresh) |

**Transition:** Twig inline JS is adapted to subscribe to the new topic patterns. No dual publishing.

### 4.5 Map Library: MapLibre GL JS + PMTiles Self-Hosted

MapLibre GL JS (WebGL, vector tiles) replaces Leaflet. React wrapper: `react-map-gl`. Tile source: PMTiles self-hosted (zero third-party dependency).

**Trade-off:** All existing Leaflet map logic (markers, polylines, popups) must be rewritten for MapLibre's API. This is acceptable because we're building a new frontend from scratch.

### 4.6 Build: Vite Standalone

Vite builds to `backend/public/app/`. No Symfony bundle dependency. In development, Vite dev server proxies API calls to Symfony.

### 4.7 State Management: TanStack Query + Zustand

- TanStack Query: All server state (fetching, caching, polling, Mercure-triggered invalidation)
- Zustand: Only client-local state (sidebar open/close, selected vehicle, map viewport)

## 5. Backend Design

### 5.1 Domain Layer: `src/Domain/MapView/`

```php
// src/Domain/MapView/Model/MapUpdateType.php
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

// src/Domain/MapView/Model/MapUpdate.php
final readonly class MapUpdate
{
    public function __construct(
        public MapUpdateType $type,
        public string $routePublicId,
        public array $data,
        public \DateTimeImmutable $occurredAt,
    ) {}
}

// src/Domain/MapView/Model/VehiclePosition.php
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
}

// src/Domain/MapView/Projection/MapProjectableEventInterface.php
// Marker interface for domain events that affect map views
interface MapProjectableEventInterface
{
    public function getRoutePublicId(): string;
    public function getOccurredAt(): \DateTimeImmutable;
}

// src/Domain/MapView/Projection/MapProjectorInterface.php
interface MapProjectorInterface
{
    /** @return list<MapUpdate> */
    public function projectRouteEvent(MapProjectableEventInterface $event): array;

    public function projectVehiclePosition(VehiclePositionReceived $event): VehiclePosition;
}

// src/Domain/MapView/Publisher/MapPublisherInterface.php
interface MapPublisherInterface
{
    public function publishRouteUpdate(MapUpdate $update): void;
    public function publishVehiclePosition(VehiclePosition $position): void;
}
```

### 5.2 Infrastructure Layer: `src/Infrastructure/MapView/`

```php
// MapEventProjector listens to all domain events and delegates to MapPublisherInterface
// It replaces the Mercure-publishing parts of:
// - MercurePositionListener → publishes to /vehicles/{id}/position
// - MercureRouteProgressListener → publishes to /customers/{customerId}/routes (customer-scoped progress)
// - RouteSnapshotListener → publishes to /routes/{id}/view/{role} (role-based MapViewData)
// - EtaRecalculationListener → publishes to /routes/{id}/view/{role} AND /routes/{id}/deviation
// - RouteEventLogListener → publishes to /routes/{id}/events (event history)
// Note: DeviationAlertListener only persists RealtimeEvent, no direct Mercure — stays separate

// MercureMapPublisher implements MapPublisherInterface
// Uses RealtimePublisherInterface (already exists, currently unused)
// Publishes to 3 topic patterns:
//   /map/vehicles/{publicId}/position
//   /map/routes/{publicId}/updates
//   /map/users/{userId}/notifications
```

### 5.3 New API Endpoints

All endpoints use `ApiErrorResponder` for error responses (project convention).

```
GET /api/me
  → { id, email, role, customerId?, customerName? }

GET /api/fleet/map-data
  → { vehicles: [...], routes: [...], mercureUrl }
  (Reuses FleetOverviewService::getFleetMapData())

GET /api/routes/{publicId}/map
  → MapViewData.toArray()
  (Reuses RouteViewService::buildSingleRouteView())
```

**CSRF strategy:** Phase 1 only has GET endpoints (no CSRF needed). When future mutation endpoints are added for the SPA (e.g., stop delivery, route actions), CSRF protection will use `SameSite=Lax` cookies (already the default in Symfony) combined with checking the `Origin` header. This avoids the complexity of a CSRF token endpoint while maintaining security for same-origin requests.

### 5.4 TopicResolver Update

```php
// ROLE_ADMIN: wildcard (all topics)
return ['*'];

// ROLE_OPERATOR: same as admin (inherits via role hierarchy)
// Falls through to ROLE_CUSTOMER case currently — needs explicit handling

// ROLE_CUSTOMER:
$topics = [
    sprintf('/map/users/%s/notifications', $user->getId()),
];
// Vehicle position topics for allowed vehicles
foreach ($allowedVehiclePublicIds as $publicId) {
    $topics[] = sprintf('/map/vehicles/%s/position', $publicId);
}
// Route update topics: customer needs route-specific topics
// Resolved by querying active routes for the customer
foreach ($customerRoutePublicIds as $routePublicId) {
    $topics[] = sprintf('/map/routes/%s/updates', $routePublicId);
}
return $topics;

// ROLE_DRIVER:
$topics = [
    sprintf('/map/users/%s/notifications', $user->getId()),
];
// Vehicle position for assigned vehicle(s)
foreach ($allowedVehiclePublicIds as $publicId) {
    $topics[] = sprintf('/map/vehicles/%s/position', $publicId);
}
// Route updates for assigned routes
foreach ($assignedRoutePublicIds as $routePublicId) {
    $topics[] = sprintf('/map/routes/%s/updates', $routePublicId);
}
return $topics;
```

**Note:** TopicResolver needs a new dependency to resolve active route public IDs per customer/driver. This can be a lightweight query method on `RouteRepository`.

**Note:** `/api/mercure-token` already exists (`MercureTokenController.php`) and does NOT need creation — only the topic patterns in the JWT payload need updating to match the new `/map/*` patterns.

## 6. Frontend Design

### 6.1 Tech Stack

| Library | Version | Purpose |
|---------|---------|---------|
| React | 19.x | UI framework |
| React Router | 7.x | Client-side routing |
| Vite | 6.x | Build + HMR |
| TypeScript | 5.8+ | Type safety |
| TanStack Query | 5.x | Server state |
| Zustand | 5.x | Client state |
| MapLibre GL JS | 5.x | Map engine (WebGL) |
| react-map-gl | 7.x | React MapLibre wrapper |
| pmtiles | 4.x | Self-hosted tile protocol |
| Tailwind CSS | 4.x | Styling |
| zod | 3.x | Response validation |

### 6.2 Project Structure

```
frontend/
  src/
    main.tsx                        # Entry + providers + router
    api/
      client.ts                     # Fetch wrapper (session cookies, CSRF)
      types.ts                      # TypeScript types matching backend DTOs
      hooks/
        useMercure.ts               # Generic SSE hook (any topic)
        useMercurePositions.ts      # /map/vehicles/*/position subscription
        useMercureRouteUpdates.ts   # /map/routes/*/updates subscription
        useFleetMapData.ts          # Fleet data + live position merge
        useRouteMapData.ts          # Route data + live update merge
        useMe.ts                    # Current user info
    components/
      layout/
        AppShell.tsx                # Sidebar + topbar + content area
        Sidebar.tsx                 # Role-aware navigation
      maps/
        FleetMap.tsx                # Full fleet map component
        RouteMap.tsx                # Route detail map component
        shared/
          VehicleMarker.tsx         # Vehicle icon with course rotation
          StopMarker.tsx            # Numbered stop with status color
          RouteLayer.tsx            # GeoJSON polyline with route color
          OriginMarker.tsx          # Green origin marker
          MapControls.tsx           # Reset bounds, layer toggles
          useMapBounds.ts           # Auto-fit bounds hook
          polyline.ts               # Decode encoded polylines → GeoJSON
          colors.ts                 # Shared color constants
      ui/
        Toast.tsx, Modal.tsx, Badge.tsx, Loading.tsx
    pages/
      admin/
        FleetMapPage.tsx
        RouteDetailPage.tsx
    stores/
      uiStore.ts                    # Sidebar, selections, viewport
    router.tsx                      # Role-based route definitions
    auth.tsx                        # Auth context from /api/me
```

### 6.3 TypeScript Types (matching backend DTOs)

```typescript
// Match MapViewData.toArray() — src/View/MapViewData.php
interface MapData {
  routes: RouteData[];
  origin?: { lat: number; lng: number; address?: string };
  globalMetrics?: Record<string, unknown>;
  mercureTopic?: string;
  mercureUrl?: string;
  vehiclePublicId?: string;
  vehiclePosition?: { lat: number; lng: number; speed?: number; course?: number };
}

// Match RouteViewData.toArray() — src/View/RouteViewData.php
interface RouteData {
  publicId: string;
  name: string;
  color: string;
  vehicleName?: string;
  driverName?: string;
  status?: string;
  stops: StopData[];
  polyline?: string;
  metrics?: Record<string, unknown>;
  timing?: Record<string, unknown>;
  validation?: Record<string, unknown>;
  originalStops?: StopData[];
  comparisonPolyline?: string;
}

// Match StopViewData.toArray() — src/View/StopViewData.php
interface StopData {
  sequence: number;
  address: string;
  recipientName?: string;
  recipientPhone?: string;
  lat?: number;
  lng?: number;
  status: string;
  isOrigin: boolean;
  deliveredAt?: string;
  exceptionCode?: string;
  exceptionNotes?: string;
  etaMinutes?: number;
  etaTime?: string;
  etaDistanceKm?: number;
}

// Match MapUpdate VO — src/Domain/MapView/Model/MapUpdate.php
interface MapUpdate {
  type: 'stop_delivered' | 'eta_changed' | 'deviation_detected' | 'route_started'
    | 'route_completed' | 'stop_exception' | 'route_snapshot' | 'route_optimized'
    | 'route_assigned' | 'route_cancelled' | 'routes_built' | 'deviation_ended';
  routePublicId: string;
  data: Record<string, unknown>;
  occurredAt: string;
}

// Match VehiclePosition VO — src/Domain/MapView/Model/VehiclePosition.php
interface VehiclePosition {
  vehiclePublicId: string;
  lat: number;
  lng: number;
  speed?: number;
  course?: number;
  deviceTime: string;
}
```

### 6.4 Data Flow Pattern

```
Initial Load:
  GET /api/fleet/map-data → TanStack Query cache → FleetMap renders

Real-time Updates:
  Mercure SSE (/map/vehicles/*/position) → useMercurePositions → merge into query cache → FleetMap re-renders
  Mercure SSE (/map/routes/*/updates) → useMercureRouteUpdates → invalidate query → refetch → re-render

Merge Strategy:
  Vehicle positions: Direct merge (replace position in vehicle array)
  Route updates: Query invalidation (refetch route data for consistency)
```

### 6.5 Mercure SSE Hook

```typescript
function useMercure<T>(topics: string[], onMessage: (data: T) => void) {
  // 1. Fetch JWT from /api/mercure-token (TanStack Query, staleTime: 5min)
  // 2. Build EventSource URL with topics
  // 3. Parse JSON messages, call onMessage
  // 4. Cleanup on unmount
  // 5. Reconnect on error: exponential backoff (1s, 2s, 4s, 8s, max 30s ceiling)
  //    Max retries: 10, then show "connection lost" UI toast
  // 6. JWT expiration: TanStack Query auto-refetches when staleTime expires;
  //    on 401 from EventSource, invalidate token query and reconnect with new JWT
}
```

### 6.6 MapLibre + PMTiles Integration

```typescript
import { Map, Source, Layer, Marker } from 'react-map-gl/maplibre';
import { Protocol } from 'pmtiles';

// Register PMTiles protocol once at app startup
const protocol = new Protocol();
maplibregl.addProtocol('pmtiles', protocol.tile);

// Map component uses style pointing to self-hosted PMTiles
<Map mapStyle={mapStyle} /* style JSON referencing pmtiles:// source */>
  <Source type="geojson" data={routeGeoJSON}>
    <Layer type="line" paint={{ 'line-color': route.color, 'line-width': 4 }} />
  </Source>
  {vehicles.map(v => (
    <Marker key={v.publicId} longitude={v.lng} latitude={v.lat}>
      <VehicleIcon course={v.course} />
    </Marker>
  ))}
</Map>
```

## 7. Coexistence Details

### 7.1 Symfony Catch-All Controller

```php
#[Route('/app/{path}', name: 'spa_entrypoint', requirements: ['path' => '.*'], methods: ['GET'])]
public function __invoke(): Response
{
    return new Response(
        file_get_contents($this->getParameter('kernel.project_dir') . '/public/app/index.html')
    );
}
```

### 7.2 Development Workflow

- `cd frontend && npm run dev` — Vite dev server on port 5173
- `vite.config.ts` proxies `/api/*`, `/login`, `/logout` to Symfony on port 8000
- Both servers run simultaneously during development

### 7.3 Production Build

```bash
cd frontend && npm run build
# Output: backend/public/app/index.html + assets
```

### 7.4 Sidebar Link Migration

During coexistence, the Twig sidebar shows both old and new links:
- Map pages → `/app/admin/fleet-map` (new)
- CRUD pages → `/admin/vehicles` (old, until migrated)

## 8. Implementation Phases

### Phase 1: Infrastructure + Fleet Map (this spec)
- MapView bounded context + Event Consolidation
- React SPA scaffold + Fleet Map + Mercure SSE
- First functional page at `/app/admin/fleet-map`

### Phase 2: Route Detail Map
### Phase 3: Route Planner + Remaining Maps
### Phase 4: Admin CRUD Pages
### Phase 5: Customer + Driver Portals
### Phase 6: Reports + Admin Tools
### Phase 7: Remove Twig

## 9. Files to Modify/Create

**Create (Backend):**
- `src/Domain/MapView/Model/MapUpdate.php`
- `src/Domain/MapView/Model/MapUpdateType.php`
- `src/Domain/MapView/Model/VehiclePosition.php`
- `src/Domain/MapView/Projection/MapProjectableEventInterface.php`
- `src/Domain/MapView/Projection/MapProjectorInterface.php`
- `src/Domain/MapView/Publisher/MapPublisherInterface.php`
- `src/Infrastructure/MapView/Projection/MapEventProjector.php`
- `src/Infrastructure/MapView/Publisher/MercureMapPublisher.php`
- `src/Infrastructure/MapView/Controller/FleetMapDataController.php`
- `src/Infrastructure/MapView/Controller/RouteMapDataController.php`
- `src/Infrastructure/MapView/Controller/MeController.php`
- `src/Controller/SpaController.php`

**Modify (Backend):**
- `src/Security/TopicResolver.php` — new topic patterns
- `src/EventListener/Domain/MercurePositionListener.php` — remove (replaced by MapEventProjector)
- `src/EventListener/Domain/MercureRouteProgressListener.php` — remove (replaced)
- `src/EventListener/Domain/RouteSnapshotListener.php` — extract persistence, remove Mercure publishing
- `src/EventListener/Domain/EtaRecalculationListener.php` — extract persistence, remove Mercure publishing
- `src/EventListener/Domain/RouteEventLogListener.php` — extract persistence, remove Mercure publishing
- `src/EventListener/Domain/DeviationAlertListener.php` — keep persistence only

**Modify (Twig JS):**
- `templates/tracking/map.html.twig` — update SSE topic subscriptions
- `templates/components/route/_map_js.html.twig` — update SSE topic subscriptions

**Create (Frontend):**
- Entire `frontend/` directory (see Section 6.2 for structure)

## 10. Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| PMTiles file size for global coverage | Start with regional extract; expand later |
| MapLibre learning curve vs Leaflet | Well-documented API; react-map-gl abstracts complexity |
| Mercure topic change breaks Twig | Update Twig JS inline before removing old listeners |
| Session cookie not sent to SPA | Same-origin, automatic. `credentials: 'same-origin'` in fetch |
| Large refactor of 6 listeners | Persistence logic stays in place; only publishing consolidates |
