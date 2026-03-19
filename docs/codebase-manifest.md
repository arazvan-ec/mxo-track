# Codebase Manifest

> **Auto-generated** by `make manifest` (`backend/bin/generate-manifest.sh`).
> Do not edit manually — regenerate after adding/removing entities, enums, services, or controllers.

**Generated:** 2026-03-19 22:28
**Regenerate:** `make manifest`

## Metrics

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
| Tests | 105 |
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

## src/ Directory Tree (2 levels)

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

## Deep Reference

For detailed information beyond this manifest, consult:

| Topic | Document |
|-------|----------|
| Entity details, relations, traits | `docs/knowledge/domain-model.md` |
| Full feature inventory | `docs/FEATURES.md` |
| Architecture, bounded contexts | `docs/knowledge/architecture-ddd.md` |
| API endpoints, controllers | `docs/knowledge/api-surface.md` |
| Design patterns in use | `docs/knowledge/design-patterns.md` |
| All knowledge modules | `docs/knowledge/index.md` |
