# Codebase Manifest

> **Auto-generated** by `make manifest` (`backend/bin/generate-manifest.sh`).
> Do not edit manually — regenerate with `make manifest`.

**Generated:** 2026-03-22 22:39
**Regenerate:** `make manifest`

## Project Overview

| Area | Path | Files | Tech |
|------|------|------:|------|
| Backend | `backend/` | 501 PHP | Symfony 7.4, PHP 8.4 |
| Frontend | `frontend/` | 60 JS/TS | React |
| ML Service | `ml-service/` | 17 Python | FastAPI |
| Docker/Infra | `docker/` + `scripts/` | 9 + 5 | Docker, OSRM, VROOM, Traccar |
| OpenSpec | `openspec/` | 28 specs | YAML specs |
| Docs | `docs/` | — | Knowledge modules, analysis |

---

## Backend Metrics

| Category | Count |
|----------|------:|
| Entities (src/Entity/) | 34 |
| Domain Models (src/Domain/*/Model/) | 16 |
| Enums — core (src/Enum/) | 17 |
| Enums — provider | 4 |
| **Enums total** | **21** |
| Controllers | 61 |
| Application Services (src/Application/) | 23 |
| Domain/Infra Services (src/Service/) | 73 |
| Repositories | 18 |
| Console Commands | 18 |
| DTOs | 17 |
| Event Listeners | 11 |
| Messenger Messages | 4 |
| Message Handlers | 4 |
| Tests | 125 |
| Migrations | 33 |

## Entity List

- AddressRisk
- ApiKey
- AuditLog
- CsvImportRun
- Customer
- CustomerIntegration
- CustomerLocation
- CustomerScopedEntityInterface
- CustomerVehicle
- DeliveryRating
- DeliverySlot
- DeliveryZone
- DriverAction
- DriverAvailability
- DriverFeedback
- Notification
- NotificationLog
- NotificationPreference
- OptimizationStrategyComparison
- PushSubscription
- RealtimeEvent
- RecipientAction
- RecipientNotification
- RouteOptimizationLog
- RoutePerformanceMetric
- RoutePlanTemplate
- SoftDeletableInterface
- User
- Vehicle
- VehicleCheckpoint
- VehicleInspection
- VehicleLastPosition
- VehiclePosition
- WebhookEndpoint

## Domain Models

- **MapView/** MapUpdate
- **MapView/** MapUpdateType
- **MapView/** VehiclePosition
- **Route/** Route
- **Route/** RouteEvent
- **Route/** RouteMapMetrics
- **Route/** RouteMapOptions
- **Route/** RouteMapTiming
- **Route/** RouteMapView
- **Route/** RouteSnapshot
- **Route/** RouteStop
- **Route/** StopMapView
- **Shipment/** Parcel
- **Shipment/** Pod
- **Shipment/** Shipment
- **Shipment/** ShipmentEvent

## Enum List

### Core (src/Enum/)

- ClientFrequency
- ExceptionCode
- NotificationChannel
- NotificationLogStatus
- NotificationTriggerType
- OptimizationOperation
- OptimizationStepCategory
- ParcelStatus
- RecipientActionType
- RouteEventType
- RouteStatus
- RouteStopStatus
- ServiceType
- ShipmentEventType
- ShipmentPriority
- UserRole
- VehicleSkill

### Provider

- SmsNotifierProviderType (`Provider/Enum/SmsNotifierProviderType.php`)
- GpsProviderType (`Provider/Gps/GpsProviderType.php`)
- RealtimeProviderType (`Provider/Realtime/RealtimeProviderType.php`)
- ServiceType (`Provider/ServiceType.php`)

## Bounded Contexts (src/Domain/)

- **Event/** — 
- **MapView/** — Model,Projection,Publisher
- **Route/** — Model,Repository,Service
- **Shipment/** — Model,Repository

## Backend Directory Tree (2 levels)

```
Ai
Application
Application/Delivery
Application/Fleet
Application/Route
Application/Tracking
Command
Controller
Controller/Admin
Controller/Api
Controller/Customer
Controller/Operator
DataFixtures
DataFixtures/data
Doctrine
Doctrine/Dql
Doctrine/Types
Domain
Domain/Event
Domain/MapView
Domain/Route
Domain/Shipment
Dto
Dto/Api
Dto/Driver
Entity
Entity/Concerns
Enum
EventListener
EventListener/Domain
EventSubscriber
Form
Geocoding
Http
Infrastructure
Infrastructure/MapView
Infrastructure/Route
Infrastructure/Security
Infrastructure/Shipment
Logging
Message
MessageHandler
Notification
Notification/Gate
Notification/Message
Notification/Transport
Prediction
Provider
Provider/Enum
Provider/Factory
Provider/Gps
Provider/Realtime
Provider/RouteOptimizer
Provider/Routing
Realtime
Repository
RouteOptimization
Routing
Security
Security/Voter
Service
Tracking
Validator
View
```

---

## Frontend

| Category | Count |
|----------|------:|
| JS/TS files total | 60 |
| Pages | 9 |

### Directory Tree

```
api
api/hooks
assets
components
components/fleet
components/layout
components/maps
components/panels
hooks
pages
pages/admin
pages/customer
pages/driver
```

---

## ML Service

| Category | Count |
|----------|------:|
| Python files | 17 |
| API Routers | 7 |
| Models | 7 |

### Directory Tree

```
app
app/models
app/routers
```

---

## Docker & Infrastructure

### Docker configs (`docker/`)

- `nginx-railway.conf`
- `osrm/data/.gitkeep`
- `osrm/prepare-map.sh`
- `php/Dockerfile`
- `traccar-local/traccar.xml`
- `traccar-railway/traccar.xml`
- `vroom/config-railway.yml`
- `vroom/config.yml`
- `vroom/entrypoint.sh`

### Scripts (`scripts/`)

- `osrm-start.sh`
- `railway-setup-vars.sh`
- `railway-start.sh`
- `railway-worker-start.sh`
- `traccar-start.sh`

---

## OpenSpec

| Category | Count |
|----------|------:|
| Total spec files | 28 |
| Entity specs | 12 |
| Business rules | 5 |
| API contracts | 5 |

### Spec files

- `specs/api-architecture-diagnostic.yaml`
- `specs/api-consumers/traccar.yaml`
- `specs/api-contracts/admin-web.yaml`
- `specs/api-contracts/driver-api.yaml`
- `specs/api-contracts/mercure-token-api.yaml`
- `specs/api-contracts/shipment-api.yaml`
- `specs/api-contracts/vehicle-api.yaml`
- `specs/architectural-constraints/layer-dependencies.yaml`
- `specs/architecture-profile.yaml`
- `specs/business-rules/delivery.yaml`
- `specs/business-rules/multi-tenant.yaml`
- `specs/business-rules/security.yaml`
- `specs/business-rules/shipment.yaml`
- `specs/business-rules/vehicle-tracking.yaml`
- `specs/constitution.md`
- `specs/entities/audit-log.yaml`
- `specs/entities/customer.yaml`
- `specs/entities/driver-action.yaml`
- `specs/entities/pod.yaml`
- `specs/entities/route-stop.yaml`
- `specs/entities/route.yaml`
- `specs/entities/shipment-event.yaml`
- `specs/entities/shipment.yaml`
- `specs/entities/user.yaml`
- `specs/entities/vehicle-last-position.yaml`
- `specs/entities/vehicle-position.yaml`
- `specs/entities/vehicle.yaml`
- `specs/spec-manifest.yaml`

---

## Service Map

Services with the interfaces they implement (auto-extracted from source):

| Service | Implements | Path |
|---------|-----------|------|
| CachedProviderResolver | ProviderResolverInterface | `Provider/CachedProviderResolver.php` |
| DatabasePodStorage | PodStorageInterface | `Service/DatabasePodStorage.php` |
| DeviationDetected | MapProjectableEventInterface | `Domain/Event/DeviationDetected.php` |
| DeviationEnded | MapProjectableEventInterface | `Domain/Event/DeviationEnded.php` |
| DoctrinePodRepository | PodRepositoryInterface | `Infrastructure/Shipment/Doctrine/DoctrinePodRepository.php` |
| DoctrineRouteEventRepository | RouteEventRepositoryInterface | `Infrastructure/Route/Doctrine/DoctrineRouteEventRepository.php` |
| DoctrineRouteRepository | RouteRepositoryInterface | `Infrastructure/Route/Doctrine/DoctrineRouteRepository.php` |
| DoctrineRouteSnapshotRepository | RouteSnapshotRepositoryInterface | `Infrastructure/Route/Doctrine/DoctrineRouteSnapshotRepository.php` |
| DoctrineRouteStopRepository | RouteStopRepositoryInterface | `Infrastructure/Route/Doctrine/DoctrineRouteStopRepository.php` |
| DoctrineShipmentRepository | ShipmentRepositoryInterface | `Infrastructure/Shipment/Doctrine/DoctrineShipmentRepository.php` |
| EtaChanged | MapProjectableEventInterface | `Domain/Event/EtaChanged.php` |
| GoogleDirectionsEngine | RoutingEngineInterface | `Provider/Routing/GoogleDirectionsEngine.php` |
| GoogleDirectionsFactory | ProviderFactoryInterface | `Provider/Routing/GoogleDirectionsFactory.php` |
| GreedyOptimizer | RouteOptimizerInterface | `Provider/RouteOptimizer/GreedyOptimizer.php` |
| GreedyOptimizerFactory | ProviderFactoryInterface | `Provider/RouteOptimizer/GreedyOptimizerFactory.php` |
| HttpPollingFactory | ProviderFactoryInterface | `Provider/Realtime/HttpPollingFactory.php` |
| HttpPollingPublisher | RealtimePublisherInterface | `Provider/Realtime/HttpPollingPublisher.php` |
| MapEventProjector | MapProjectorInterface | `Infrastructure/MapView/Projection/MapEventProjector.php` |
| MercureFactory | ProviderFactoryInterface | `Provider/Realtime/MercureFactory.php` |
| MercureMapPublisher | MapPublisherInterface | `Infrastructure/MapView/Publisher/MercureMapPublisher.php` |
| NullRouteOptimizer | RouteOptimizerInterface | `RouteOptimization/NullRouteOptimizer.php` |
| NullSmsTransportFactory | ProviderFactoryInterface | `Provider/Factory/NullSmsTransportFactory.php` |
| OsrmFactory | ProviderFactoryInterface | `Provider/Routing/OsrmFactory.php` |
| ProviderResolver | ProviderResolverInterface | `Provider/ProviderResolver.php` |
| Route | SoftDeletableInterface | `Domain/Route/Model/Route.php` |
| RouteAssigned | MapProjectableEventInterface | `Domain/Event/RouteAssigned.php` |
| RouteCancelled | MapProjectableEventInterface | `Domain/Event/RouteCancelled.php` |
| RouteCompleted | MapProjectableEventInterface | `Domain/Event/RouteCompleted.php` |
| RouteOptimized | MapProjectableEventInterface | `Domain/Event/RouteOptimized.php` |
| RouteReoptimized | MapProjectableEventInterface | `Domain/Event/RouteReoptimized.php` |
| RouteStarted | MapProjectableEventInterface | `Domain/Event/RouteStarted.php` |
| Shipment | CustomerScopedEntityInterface, SoftDeletableInterface | `Domain/Shipment/Model/Shipment.php` |
| StopDelivered | MapProjectableEventInterface | `Domain/Event/StopDelivered.php` |
| StopExceptionReported | MapProjectableEventInterface | `Domain/Event/StopExceptionReported.php` |
| StopSkipped | MapProjectableEventInterface | `Domain/Event/StopSkipped.php` |
| StopsReordered | MapProjectableEventInterface | `Domain/Event/StopsReordered.php` |
| TenantAwareGpsPositionProvider | GpsPositionProviderInterface | `Provider/Gps/TenantAwareGpsPositionProvider.php` |
| TenantAwareRealtimePublisher | RealtimePublisherInterface | `Provider/Realtime/TenantAwareRealtimePublisher.php` |
| TenantAwareRouteOptimizer | RouteOptimizerInterface | `Provider/RouteOptimizer/TenantAwareRouteOptimizer.php` |
| TenantAwareRoutingEngine | RoutingEngineInterface | `Provider/Routing/TenantAwareRoutingEngine.php` |
| TraccarFactory | ProviderFactoryInterface | `Provider/Gps/TraccarFactory.php` |
| TwilioSmsTransportFactory | ProviderFactoryInterface | `Provider/Factory/TwilioSmsTransportFactory.php` |
| VroomFactory | ProviderFactoryInterface | `Provider/RouteOptimizer/VroomFactory.php` |
| VroomRouteOptimizer | RouteOptimizerInterface | `RouteOptimization/VroomRouteOptimizer.php` |
| WebhookGpsFactory | ProviderFactoryInterface | `Provider/Gps/WebhookGpsFactory.php` |
| WebhookGpsProvider | GpsPositionProviderInterface | `Provider/Gps/WebhookGpsProvider.php` |

---

## Entity Relationships

Key Doctrine relationships (auto-extracted from entity attributes):

- **ApiKey** → Customer
- **AuditLog** → User
- **CsvImportRun** → Customer
- **CustomerIntegration** → Customer
- **CustomerLocation** → Customer
- **CustomerVehicle** → Customer, Vehicle
- **DeliveryRating** → Shipment
- **DeliverySlot** → Shipment
- **DeliveryZone** → Customer
- **DriverAction** → RouteStop, User
- **DriverAvailability** → User
- **DriverFeedback** → RouteStop, User
- **Notification** → User
- **NotificationLog** → Customer, Shipment
- **NotificationPreference** → Customer
- **OptimizationStrategyComparison** → Customer, Route
- **PushSubscription** → User
- **RealtimeEvent** → Customer
- **RecipientAction** → Shipment
- **RecipientNotification** → Shipment
- **RouteOptimizationLog** → Customer, Route
- **RoutePerformanceMetric** → Customer, Route
- **RoutePlanTemplate** → Customer
- **User** → Customer
- **VehicleCheckpoint** → Vehicle
- **VehicleInspection** → Route, User
- **VehicleLastPosition** → Vehicle
- **VehiclePosition** → Route, Vehicle
- **WebhookEndpoint** → Customer

---

## Route Map (API Endpoints)

Controller endpoints (auto-extracted from `#[Route]` attributes):

| Method | Path | Controller | Action |
|--------|------|-----------|--------|
| GET | `/` | DashboardController | index |
| GET | `/admin/health/live` | AdminController | healthLive |
| GET | `/admin/health` | AdminController | health |
| GET | `/admin` | AdminController | dashboard |
| GET | `/api/fleet/map-data` | FleetMapDataController | __invoke |
| GET | `/api/me` | MeController | __invoke |
| GET | `/api/mercure-token` | MercureTokenController | __invoke |
| GET | `/api/notification-preferences/logs` | NotificationPreferenceController | logs |
| DELETE | `/api/notification-preferences/{publicId}` | NotificationPreferenceController | delete |
| GET | `/api/notification-preferences` | NotificationPreferenceController | list |
| POST | `/api/notification-preferences` | NotificationPreferenceController | create |
| GET | `/api/notifications/unread-count` | NotificationController | unreadCount |
| GET | `/api/routes/{publicId}/analysis` | RouteAnalysisController | analysis |
| GET | `/api/routes/{publicId}/etas` | RouteEtaApiController | etas |
| GET | `/api/search` | SearchController | apiSearch |
| GET | `/api/v1/routes/{publicId}` | RouteApiController | detail |
| GET | `/api/v1/routes` | RouteApiController | list |
| GET | `/api/v1/shipments/{publicId}/tracking` | ShipmentApiController | tracking |
| GET | `/api/v1/shipments/{publicId}` | ShipmentApiController | detail |
| GET | `/api/v1/shipments` | ShipmentApiController | list |
| POST | `/api/v1/shipments` | ShipmentApiController | create |
| DELETE | `/api/v1/webhooks/{publicId}` | WebhookApiController | delete |
| GET | `/api/v1/webhooks` | WebhookApiController | list |
| POST | `/api/v1/webhooks` | WebhookApiController | create |
| GET | `/api/vehicles/{publicId}/last-position` | VehicleApiController | lastPosition |
| GET | `/api/vehicles/{publicId}/positions.csv` | VehicleApiController | positionsExportCsv |
| GET | `/api/vehicles/{publicId}/positions` | VehicleApiController | positions |
| GET | `/api/vehicles` | VehicleApiController | list |
| GET | `/branches` | CommitStoryController | branches |
| POST | `/create` | ApiKeyAdminController | create |
| GET | `/customer` | AccountingExportController | export |
| GET | `/customers` | ReportController | customers |
| GET | `/dashboard/kpis` | OperatorDashboardController | kpis |
| GET | `/dashboard/live` | OperatorDashboardController | live |
| GET | `/data` | ExceptionMapController | data |
| GET | `/data` | SlaReportController | data |
| GET | `/data` | ZonePerformanceController | data |
| GET | `/deliveries` | ReportController | deliveries |
| GET | `/drivers` | ReportController | drivers |
| GET | `/events` | EventPollingController | poll |
| GET | `/export.csv` | BillingController | exportCsv |
| GET | `/export.csv` | CustomerReportController | export |
| GET | `/export/deliveries.csv` | ReportController | exportDeliveries |
| GET | `/export/drivers.csv` | ReportController | exportDrivers |
| GET | `/export` | SlaReportController | export |
| POST | `/load` | DemoFixtureController | load |
| POST | `/locale/{locale}` | LocaleController | switchLocale |
| GET | `/login` | SecurityController | login |
| GET | `/logout` | SecurityController | logout |
| POST | `/message` | AiAssistantController | message |
| POST | `/notifications/{publicId}/read` | NotificationController | markAsRead |
| GET | `/notifications` | NotificationController | index |
| POST | `/push-position` | AdminDevPushPositionController | __invoke |
| GET | `/routes/{routePublicId}/briefing` | DriverApiController | briefing |
| GET | `/routes/{routePublicId}/etas` | DriverApiController | etas |
| POST | `/routes/{routePublicId}/finish` | DriverApiController | finish |
| GET | `/routes/{routePublicId}/inspection` | DriverApiController | getInspection |
| POST | `/routes/{routePublicId}/inspection` | DriverApiController | submitInspection |
| POST | `/routes/{routePublicId}/start` | DriverApiController | start |
| POST | `/routes/{routePublicId}/stops/{stopPublicId}/feedback` | DriverApiController | stopFeedback |
| GET | `/routes/{routePublicId}/stops` | DriverApiController | stops |
| GET | `/routes` | DriverApiController | routes |
| GET | `/routing` | DebugRoutingController | diagnostics |
| GET | `/search` | SearchController | index |
| POST | `/stops/{stopPublicId}/deliver` | DriverApiController | deliver |
| POST | `/stops/{stopPublicId}/exception` | DriverApiController | exception |
| GET | `/stops/{stopPublicId}/pod/download` | DriverApiController | podDownload |
| GET | `/stops/{stopPublicId}/pod` | DriverApiController | podMetadata |
| GET | `/top-addresses` | ExceptionMapController | topAddresses |
| POST | `/track/{trackingToken}/alternative` | PublicTrackingController | alternative |
| POST | `/track/{trackingToken}/confirm-presence` | PublicTrackingController | confirmPresence |
| GET | `/track/{trackingToken}/rate` | PublicTrackingController | ratePage |
| POST | `/track/{trackingToken}/rate` | PublicTrackingController | rate |
| GET | `/track/{trackingToken}/reschedule` | PublicTrackingController | reschedule |
| POST | `/track/{trackingToken}/reschedule` | PublicTrackingController | rescheduleSubmit |
| GET | `/track/{trackingToken}` | PublicTrackingController | track |
| GET | `/vapid-key` | DriverPushSubscriptionController | vapidKey |
| GET | `/{publicId}/availability` | DriverAvailabilityController | show |
| POST | `/{publicId}/availability` | DriverAvailabilityController | save |
| POST | `/{publicId}/delete` | ApiKeyAdminController | delete |
| POST | `/{publicId}/delete` | CustomerAdminController | delete |
| POST | `/{publicId}/delete` | CustomerIntegrationAdminController | delete |
| POST | `/{publicId}/delete` | CustomerLocationAdminController | delete |
| POST | `/{publicId}/delete` | DriverAdminController | delete |
| POST | `/{publicId}/delete` | ShipmentAdminController | delete |
| POST | `/{publicId}/delete` | UserAdminController | delete |
| POST | `/{publicId}/delete` | VehicleAdminController | delete |
| GET | `/{publicId}/events` | RouteEventApiController | events |
| POST | `/{publicId}/toggle` | ApiKeyAdminController | toggle |
| GET | `/{publicId}` | CustomerShipmentController | show |
| DELETE | `` | DriverPushSubscriptionController | unsubscribe |
| GET | `` | AiAssistantController | index |
| GET | `` | ApiKeyAdminController | index |
| GET | `` | BillingController | index |
| GET | `` | CommitStoryController | index |
| GET | `` | CustomerAdminController | index |
| GET | `` | CustomerIntegrationAdminController | index |
| GET | `` | CustomerLocationAdminController | index |
| GET | `` | CustomerReportController | index |
| GET | `` | CustomerShipmentController | index |
| GET | `` | DemoFixtureController | index |
| GET | `` | DriverAdminController | index |
| GET | `` | ExceptionMapController | index |
| GET | `` | OperatorDashboardController | dashboard |
| GET | `` | ReportController | index |
| GET | `` | ShipmentAdminController | index |
| GET | `` | SlaReportController | index |
| GET | `` | UserAdminController | index |
| GET | `` | VehicleAdminController | index |
| GET | `` | ZonePerformanceController | index |
| POST | `` | DriverPushSubscriptionController | subscribe |

---

## Twig Template Map

| Directory | Count | Purpose |
|-----------|------:|---------|
| `admin/` | 37 | Admin panel pages (CRUD, dashboards) |
| `components/` | 4 | Reusable UI components (partials) |
| `customer/` | 5 | Customer portal pages |
| `driver/` | 1 | Driver portal pages |
| `email/` | 3 | Email templates |
| `export/` | 1 | Export/download templates |
| `notification/` | 1 | Notification templates |
| `operator/` | 1 | Operator portal pages |
| `search/` | 1 | Search-related templates |
| `security/` | 1 | Login, registration, auth pages |
| `tracking/` | 3 | Public tracking pages |
| _(root)_ | 2 | Base layout, sidebar |

---

## Factory Registry

Provider factories (critical for Constructor Signature Changes pattern):

| Factory | Interface | Path |
|---------|-----------|------|
| NullSmsTransportFactory | ProviderFactoryInterface | `Provider/Factory/NullSmsTransportFactory.php` |
| TwilioSmsTransportFactory | ProviderFactoryInterface | `Provider/Factory/TwilioSmsTransportFactory.php` |
| TraccarFactory | ProviderFactoryInterface | `Provider/Gps/TraccarFactory.php` |
| WebhookGpsFactory | ProviderFactoryInterface | `Provider/Gps/WebhookGpsFactory.php` |
| HttpPollingFactory | ProviderFactoryInterface | `Provider/Realtime/HttpPollingFactory.php` |
| MercureFactory | ProviderFactoryInterface | `Provider/Realtime/MercureFactory.php` |
| GreedyOptimizerFactory | ProviderFactoryInterface | `Provider/RouteOptimizer/GreedyOptimizerFactory.php` |
| VroomFactory | ProviderFactoryInterface | `Provider/RouteOptimizer/VroomFactory.php` |
| GoogleDirectionsFactory | ProviderFactoryInterface | `Provider/Routing/GoogleDirectionsFactory.php` |
| OsrmFactory | ProviderFactoryInterface | `Provider/Routing/OsrmFactory.php` |

---

## Test Breakdown

| Type | Count |
|------|------:|
| Unit | 109 |
| Functional | 8 |
| Domain | 6 |
| Factory (test factories) | 1 |
| **Total** | **125** |

---

## Knowledge Module Status

Freshness of knowledge modules (parsed from `docs/knowledge/index.md`):

| Module | Last Verified | Fresh? |
|--------|--------------|--------|
| Modelo de Dominio | 2026-03-19 | 2026-03-19 |
| Provider Framework | -- | Not verified |
| API Surface | -- | Not verified |
| Deployment | -- | Not verified |
| Testing | -- | Not verified |
| Realtime | -- | Not verified |
| GPS Tracking | -- | Not verified |
| Notifications | -- | Not verified |
| AI/ML | -- | Not verified |
| Route Optimization | -- | Not verified |
| Architecture DDD/SOLID | 2026-03-19 | 2026-03-19 |
| Design Patterns | -- | Not verified |
| Security | -- | Not verified |
| Superpowers Skills | -- | Not verified |
| Feedback & Learning | -- | Not verified |
| UI & Frontend | 2026-03-22 | 2026-03-22 |

---

## Deep Reference

| Topic | Document |
|-------|----------|
| Entity details, relations, traits | `docs/knowledge/domain-model.md` |
| Full feature inventory | `docs/FEATURES.md` |
| Architecture, bounded contexts | `docs/knowledge/architecture-ddd.md` |
| API endpoints, controllers | `docs/knowledge/api-surface.md` |
| Design patterns in use | `docs/knowledge/design-patterns.md` |
| All knowledge modules | `docs/knowledge/index.md` |
| Deployment, Docker, Railway | `docs/knowledge/deployment.md` |
| GPS tracking, Traccar | `docs/knowledge/gps-tracking.md` |
| Route optimization, VROOM/OSRM | `docs/knowledge/route-optimization.md` |
