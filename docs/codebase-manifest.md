# Codebase Manifest

> **Auto-generated** by `make manifest` (`backend/bin/generate-manifest.sh`).
> Do not edit manually — regenerate with `make manifest`.

**Generated:** 2026-03-20 00:05
**Regenerate:** `make manifest`

## Project Overview

| Area | Path | Files | Tech |
|------|------|------:|------|
| Backend | `backend/` | 476 PHP | Symfony 7.4, PHP 8.4 |
| Frontend | `frontend/` | 59 JS/TS | React |
| ML Service | `ml-service/` | 17 Python | FastAPI |
| Docker/Infra | `docker/` + `scripts/` | 9 + 5 | Docker, OSRM, VROOM, Traccar |
| OpenSpec | `openspec/` | 28 specs | YAML specs |
| Docs | `docs/` | — | Knowledge modules, analysis |

---

## Backend Metrics

| Category | Count |
|----------|------:|
| Entities (src/Entity/) | 41 |
| Domain Models (src/Domain/*/Model/) | 9 |
| Enums — core (src/Enum/) | 17 |
| Enums — provider | 4 |
| **Enums total** | **21** |
| Controllers | 61 |
| Application Services (src/Application/) | 21 |
| Domain/Infra Services (src/Service/) | 72 |
| Repositories | 18 |
| Console Commands | 17 |
| DTOs | 17 |
| Event Listeners | 11 |
| Messenger Messages | 4 |
| Message Handlers | 4 |
| Tests | 107 |
| Migrations | 29 |

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
- Parcel
- Pod
- PushSubscription
- RealtimeEvent
- RecipientAction
- RecipientNotification
- Route
- RouteEvent
- RouteOptimizationLog
- RoutePerformanceMetric
- RoutePlanTemplate
- RouteStop
- Shipment
- ShipmentEvent
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
- **Route/** RouteMapMetrics
- **Route/** RouteMapOptions
- **Route/** RouteMapTiming
- **Route/** RouteMapView
- **Route/** RouteSnapshot
- **Route/** StopMapView

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
Domain
Domain/Event
Domain/MapView
Domain/Route
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
| JS/TS files total | 59 |
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
