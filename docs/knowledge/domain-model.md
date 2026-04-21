# Modelo de Dominio

**Última actualización:** 2026-03-19
**Estado:** Vigente

## Entidades Core

| Entidad | Propósito | Scoped? | Soft Delete? | PublicId? |
|---------|-----------|---------|-------------|-----------|
| **Customer** | Tenant raíz | — | Sí | Sí |
| **User** | Usuarios (admin, operator, customer, driver) | customer_id (nullable) | Sí | Sí |
| **Vehicle** | Vehículos de flota (capacidad, skills, GPS device) | No (global) | Sí | Sí |
| **Shipment** | Envíos/entregas (destinatario, dirección, peso, volumen) | customer_id | Sí | Sí |
| **Route** | Rutas de entrega (vehículo, conductor, paradas) | customer_id (nullable) | Sí | Sí |
| **RouteStop** | Parada en ruta (secuencia, estado, POD) | Indirecto (vía Route) | No | Sí |
| **Pod** | Prueba de entrega (firma, destinatario) | Indirecto | No | Sí |

## Entidades de Soporte

| Entidad | Propósito |
|---------|-----------|
| **Parcel** | Bulto individual dentro de Shipment (EAN, peso, volumen) |
| **ShipmentEvent** | Evento de ciclo de vida (CREATED → DELIVERED/EXCEPTION) |
| **VehiclePosition** | Posición GPS histórica |
| **VehicleLastPosition** | Cache desnormalizada de última posición |
| **VehicleCheckpoint** | Checkpoint de ingesta Traccar |
| **VehicleInspection** | Inspección pre-ruta |
| **CustomerIntegration** | Config de providers por tenant |
| **CustomerLocation** | Hub/depósito |
| **CustomerVehicle** | Bridge Customer↔Vehicle (sin ULID) |
| **DriverAction** | Idempotencia de acciones driver (clientActionId) |
| **DriverAvailability** | Disponibilidad semanal |
| **DriverFeedback** | Feedback del conductor por parada |
| **DeliverySlot** | Ventana horaria para entrega |
| **DeliveryRating** | Valoración post-entrega (1-5 estrellas) |
| **DeliveryZone** | Zona geográfica de entrega |
| **AddressRisk** | Score de riesgo por dirección |
| **Notification** | Notificación in-app |
| **RecipientNotification** | SMS/WhatsApp al destinatario |
| **PushSubscription** | Web Push subscription |
| **WebhookEndpoint** | Endpoint webhook configurado |
| **RealtimeEvent** | Evento para polling HTTP |
| **RoutePlanTemplate** | Plantilla de ruta reutilizable |
| **CsvImportRun** | Metadata de importación CSV |
| **RouteEvent** | Histórico inmutable de eventos de ruta (append-only) |
| **RouteSnapshot** | Cache de métricas de optimización y progreso (1:1 con Route). Domain POPO en `src/Domain/Route/Model/`, persistido via XML mapping externo |
| **AuditLog** | Auditoría de seguridad |
| **ApiKey** | API key (hash SHA-256, nunca plain) |

## Enums (24 total)

**Enums de dominio** (`src/Enum/`, 17):

| Enum | Valores | Uso |
|------|---------|-----|
| **UserRole** | ADMIN, OPERATOR, CUSTOMER, DRIVER | Roles de usuario |
| **RouteStatus** | PLANNED, ACTIVE, DONE, CANCELLED | Estado de ruta |
| **RouteStopStatus** | PENDING, DELIVERED, EXCEPTION, SKIPPED | Estado de parada |
| **RouteEventType** | CREATED, OPTIMIZED, ASSIGNED, STARTED, COMPLETED, CANCELLED, STOP_DELIVERED, STOP_EXCEPTION, STOP_SKIPPED, REOPTIMIZED, STOPS_REORDERED, DEVIATION_DETECTED, DEVIATION_ENDED, ETA_CHANGED, NOTE_ADDED | Tipo de evento de ruta |
| **ExceptionCode** | ABSENT, WRONG_ADDRESS, REFUSED, DAMAGED, OTHER | Razón de excepción |
| **ShipmentEventType** | CREATED, PICKED_UP, IN_HUB, IN_TRANSIT, OUT_FOR_DELIVERY, DELIVERED, EXCEPTION, RESCHEDULE_REQUESTED | Ciclo de vida |
| **ShipmentPriority** | LOW(0), NORMAL(25), HIGH(50), URGENT(75), CRITICAL(100) | Prioridad int-backed |
| **ServiceType** | DELIVERY, DELIVERY_AND_PICKUP, RETURN | Tipos de servicio de envío |
| **ParcelStatus** | REGISTERED → IN_WAREHOUSE → LOADED → IN_TRANSIT → DELIVERED | Estado de bulto |
| **VehicleSkill** | REFRIGERATED, HEAVY_LOAD, FRAGILE, PEDESTRIAN_ACCESS, HAZMAT | Skills de vehículo (int-backed, JSON array) |
| **ClientFrequency** | NOT_FREQUENT, FREQUENT, VERY_FREQUENT, SUPER_FREQUENT | Frecuencia de cliente |
| **NotificationChannel** | Canales de notificación | Canal de envío |
| **NotificationLogStatus** | Estados de log de notificaciones | Estado de entrega de notificación |
| **NotificationTriggerType** | Tipos de trigger de notificaciones | Disparador de notificación |
| **OptimizationOperation** | Operaciones de optimización | Tipo de operación en log de optimización |
| **OptimizationStepCategory** | Categorías de pasos de optimización | Categoría en log de optimización |
| **RecipientActionType** | Tipos de acción de destinatario | Acción del receptor de notificación |

**Enums de providers** (`src/Provider/`, 6):

| Enum | Valores | Uso |
|------|---------|-----|
| **ServiceType** | ROUTING, ROUTE_OPTIMIZER, GPS, REALTIME | Tipos de provider (no confundir con `Enum\ServiceType`) |
| **RoutingProvider** | OSRM, GOOGLE_DIRECTIONS | Proveedor de routing |
| **RouteOptimizerProvider** | VROOM, GREEDY | Proveedor de optimización |
| **GpsProviderType** | TRACCAR, WEBHOOK | Proveedor GPS |
| **RealtimeProviderType** | MERCURE, HTTP_POLLING | Proveedor realtime |
| **SmsNotifierProviderType** | Tipos de provider SMS | Proveedor de SMS |

**Enums de dominio DDD** (`src/Domain/`, 1):

| Enum | Valores | Uso |
|------|---------|-----|
| **MapUpdateType** | Tipos de actualización de mapa | MapView bounded context |

## Repositories (18 en `src/Repository/` + 1 interface en Domain)

| Repository | Entidad | Notas |
|-----------|---------|-------|
| **RouteRepository** | Route | Queries de rutas por customer, status, fecha |
| **RouteStopRepository** | RouteStop | Queries de paradas por ruta, status |
| **RouteEventRepository** | RouteEvent | Historial de eventos por ruta |
| **ShipmentRepository** | Shipment | Queries por tracking number, customer |
| **UserRepository** | User | Auth, búsqueda por email/role |
| **VehicleRepository** | Vehicle | Flota, búsqueda por matrícula |
| **PodRepository** | Pod | Proof of delivery por shipment |
| **NotificationRepository** | Notification | Notificaciones por destinatario |
| **NotificationLogRepository** | NotificationLog | Logs de envío de notificaciones |
| **NotificationPreferenceRepository** | NotificationPreference | Preferencias de notificación por usuario |
| **RecipientActionRepository** | RecipientAction | Acciones de destinatarios |
| **RealtimeEventRepository** | RealtimeEvent | Eventos SSE |
| **CustomerIntegrationRepository** | CustomerIntegration | Configuración de providers por tenant |
| **RouteOptimizationLogRepository** | RouteOptimizationLog | Logs de optimización |
| **RoutePerformanceMetricRepository** | RoutePerformanceMetric | Métricas de rendimiento de rutas |
| **OptimizationStrategyComparisonRepository** | OptimizationStrategyComparison | Comparación de estrategias |
| **DriverFeedbackRepository** | DriverFeedback | Feedback de conductores |
| **AddressRiskRepository** | AddressRisk | Riesgo por dirección |

**Domain interface:** `RouteSnapshotRepositoryInterface` en `src/Domain/Route/Repository/` — único repository interface en Domain layer. Ver `docs/knowledge/architecture-ddd.md` para el patrón objetivo.

## Patrón de Identidad (PublicIdTrait)

- **Internal PK**: BIGINT auto-increment (`id`) — joins internos, nunca en APIs
- **Public ID**: ULID (`public_id`) via `PublicIdTrait` — APIs, URLs, Mercure topics
- **NUNCA exponer `id` interno** en APIs públicas
- `{publicId}` en route parameters, `shipment_public_id` en payloads Driver API
- Excepción: `CustomerVehicle` no usa PublicIdTrait

## Multi-Tenancy

- `CustomerTenantFilter`: Doctrine SQL filter que añade `WHERE customer_id = ?`
- `CustomerScopedEntityInterface`: Las entidades opt-in implementan esta interfaz
- `DoctrineCustomerFilterSubscriber`: Activa el filtro para ROLE_CUSTOMER y ROLE_DRIVER
- Admin/Operator bypasean el filtro
- Entidades scoped: Shipment, Route, CustomerIntegration, ApiKey, WebhookEndpoint, CsvImportRun, RealtimeEvent, RoutePlanTemplate, DeliveryZone

## Árbol de Relaciones

```
Customer (root)
├── User (ManyToOne nullable SET NULL)
├── Shipment (ManyToOne required) → Parcel, ShipmentEvent, DeliverySlot, DeliveryRating, RecipientNotification
├── Route (ManyToOne nullable SET NULL) → RouteStop → Pod, DriverFeedback
├── CustomerLocation (ManyToOne required CASCADE)
├── CustomerVehicle (bridge) → Vehicle
├── CustomerIntegration (ManyToOne required)
├── ApiKey (ManyToOne required CASCADE)
├── WebhookEndpoint (ManyToOne required CASCADE)
├── RoutePlanTemplate, RealtimeEvent, CsvImportRun
└── DeliveryZone (nullable)

Vehicle (global, no scoped)
├── VehiclePosition (OneToMany CASCADE)
├── VehicleLastPosition (OneToOne CASCADE)
├── VehicleCheckpoint (OneToOne CASCADE)
└── CustomerVehicle (bridge a Customer)
```

## Arquitectura de Capas de Rutas

**[PARTIAL]** El dominio de rutas usa un modelo de 4 capas que preserva la inmutabilidad del plan original mientras permite que el estado operativo cambie. Solo RouteSnapshot es un Domain POPO (`src/Domain/Route/Model/`); Route, RouteStop y RouteEvent siguen siendo entidades ORM en `src/Entity/` con atributos Doctrine directos:

1. **RouteSnapshot (inmutable)** — Captura el plan original: orden de paradas, polyline, métricas de ahorro, validación de capacidad. Domain POPO con persistencia via XML mapping externo.
2. **Route + RouteStop (mutable)** — Estado operativo: status de ruta/paradas, vehículo y driver asignados. Entidades ORM en `src/Entity/`.
3. **RouteEvent (append-only)** — Historial inmutable de 15 tipos de evento (entregas, excepciones, desviaciones, re-optimizaciones). Entidad ORM en `src/Entity/`.
4. **Estado en tiempo real** — stopStates, ETAs, actualPolyline actualizados via Mercure SSE

Para diagrama completo, flujo end-to-end, constraints y gaps: ver `docs/knowledge/route-optimization.md` > "Arquitectura del Dominio de Rutas".

## MapView Bounded Context (`src/Domain/MapView/`)

Motor de proyección de eventos para actualización de mapa en tiempo real. DDD puro — todos los archivos son POPOs sin dependencias de framework.

| Capa | Archivo | Responsabilidad |
|------|---------|-----------------|
| **Model** | `MapUpdate` | Dato de proyección: actualización de mapa para un evento |
| **Model** | `MapUpdateType` (enum) | Tipos de actualización |
| **Model** | `VehiclePosition` | Posición de vehículo (distinto de `Entity\VehiclePosition`) |
| **Projection** | `MapProjectableEventInterface` | Contrato: domain events que se proyectan en mapa |
| **Projection** | `MapProjectorInterface` | Contrato: transforma evento → MapUpdate |
| **Publisher** | `MapPublisherInterface` | Contrato: publica actualizaciones (impl. via Mercure) |

13 domain events implementan `MapProjectableEventInterface`. Cuando se disparan, el projector los transforma en `MapUpdate` y el publisher los envía por SSE.

## Domain Events

| Evento | Disparado por | Listeners |
|--------|--------------|-----------|
| `VehiclePositionReceived` | TraccarIngestionService | MercurePositionListener, EtaRecalculationListener, ApproachingNotificationSubscriber, FleetAnomalyCheckListener |
| `RoutesBuilt` | RoutePlanningService | RouteEventLogListener |
| `RouteOptimized` | RoutePlanningService | RouteEventLogListener |
| `RouteStarted` | RouteLifecycleService | MercureRouteProgressListener, AiEnrichmentListener, RouteSnapshotListener, RouteEventLogListener |
| `RouteCompleted` | RouteLifecycleService | MercureRouteProgressListener, PostRouteAnalysisListener, RouteSnapshotListener, RouteEventLogListener |
| `StopDelivered` | DeliveryService | MercureRouteProgressListener, NotifyDeliveryListener, AuditDeliveryListener, RouteSnapshotListener, RouteEventLogListener |
| `StopExceptionReported` | DeliveryService | NotifyDeliveryListener, AuditDeliveryListener, RouteSnapshotListener, RouteEventLogListener |
| `RouteCancelled` | RouteAdminController | RouteEventLogListener |
| `RouteAssigned` | RouteAdminController | RouteEventLogListener |
| `EtaChanged` | EtaRecalculationListener | RouteEventLogListener |
| `DeviationDetected` | EtaRecalculationListener | RouteEventLogListener, DeviationAlertListener |
| `DeviationEnded` | EtaRecalculationListener | RouteEventLogListener |
| `ShipmentCreated` | ShipmentService | ShipmentEmbeddingListener |

## Stops and Delivery Points

Una "stop" representa un punto de entrega/recogida en una ruta. Se modela como
`RouteStep` asociado a un `Shipment` (cuando es delivery/pickup) o a waypoint.

- **Entidad:** `src/Entity/RouteStep.php`
- **Relaciones:** `ManyToOne` a `Route`, opcional `ManyToOne` a `Shipment`
- **Orden:** campo `sequenceIndex` define secuencia; VROOM/optimizer lo reordena
- **Estado de entrega:** `ShipmentStatus` transiciona vía POD (proof-of-delivery)
- **Domain events:** `ShipmentCreated`, `DeviationDetected` impactan stops

Logs representativos: `2026-03-25-route-step-reorder.md`,
`2026-04-12-customer-advanced-filters.md` (filtros por stop status).

## Historial

- 2026-03-11: Creación inicial
- 2026-03-13: Añadir RouteEvent, RouteSnapshot, RouteEventType, RouteCancelled, RouteAssigned, RouteEventLogListener
- 2026-03-16: Añadir sección "Arquitectura de Capas de Rutas" con cross-reference a route-optimization.md
- 2026-03-14: Añadir DeviationDetected, DeviationEnded, DEVIATION_ENDED, EtaChanged domain events; EtaRecalculationListener con throttle 30s y detección de desvío
