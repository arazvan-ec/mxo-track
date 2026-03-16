# Modelo de Dominio

**Última actualización:** 2026-03-16
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
| **RouteSnapshot** | Cache de métricas de optimización y progreso (1:1 con Route) |
| **AuditLog** | Auditoría de seguridad |
| **ApiKey** | API key (hash SHA-256, nunca plain) |

## Enums

| Enum | Valores | Uso |
|------|---------|-----|
| **UserRole** | ADMIN, OPERATOR, CUSTOMER, DRIVER | Roles de usuario |
| **RouteStatus** | PLANNED, ACTIVE, DONE, CANCELLED | Estado de ruta |
| **RouteStopStatus** | PENDING, DELIVERED, EXCEPTION, SKIPPED | Estado de parada |
| **ExceptionCode** | ABSENT, WRONG_ADDRESS, REFUSED, DAMAGED, OTHER | Razón de excepción |
| **ShipmentEventType** | CREATED, PICKED_UP, IN_HUB, IN_TRANSIT, OUT_FOR_DELIVERY, DELIVERED, EXCEPTION, RESCHEDULE_REQUESTED | Ciclo de vida |
| **ShipmentPriority** | LOW(0), NORMAL(25), HIGH(50), URGENT(75), CRITICAL(100) | Prioridad int-backed |
| **RouteEventType** | CREATED, OPTIMIZED, ASSIGNED, STARTED, COMPLETED, CANCELLED, STOP_DELIVERED, STOP_EXCEPTION, STOP_SKIPPED, REOPTIMIZED, STOPS_REORDERED, DEVIATION_DETECTED, DEVIATION_ENDED, ETA_CHANGED, NOTE_ADDED | Tipo de evento de ruta |
| **ServiceType** | ROUTING, ROUTE_OPTIMIZER, GPS, REALTIME | Tipos de provider |
| **VehicleSkill** | REFRIGERATED, HEAVY_LOAD, FRAGILE, etc. | Skills de vehículo (int-backed, JSON array) |
| **ParcelStatus** | REGISTERED → IN_WAREHOUSE → LOADED → IN_TRANSIT → DELIVERED | Estado de bulto |
| **ClientFrequency** | NOT_FREQUENT, FREQUENT, VERY_FREQUENT, SUPER_FREQUENT | Frecuencia de cliente |

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

El dominio de rutas usa un modelo de 4 capas que preserva la inmutabilidad del plan original mientras permite que el estado operativo cambie:

1. **RouteSnapshot (inmutable)** — Captura el plan original: orden de paradas, polyline, métricas de ahorro, validación de capacidad
2. **Route + RouteStop (mutable)** — Estado operativo: status de ruta/paradas, vehículo y driver asignados
3. **RouteEvent (append-only)** — Historial inmutable de 15 tipos de evento (entregas, excepciones, desviaciones, re-optimizaciones)
4. **Estado en tiempo real** — stopStates, ETAs, actualPolyline actualizados via Mercure SSE

Para diagrama completo, flujo end-to-end, constraints y gaps: ver `docs/knowledge/route-optimization.md` > "Arquitectura del Dominio de Rutas".

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

## Historial

- 2026-03-11: Creación inicial
- 2026-03-13: Añadir RouteEvent, RouteSnapshot, RouteEventType, RouteCancelled, RouteAssigned, RouteEventLogListener
- 2026-03-16: Añadir sección "Arquitectura de Capas de Rutas" con cross-reference a route-optimization.md
- 2026-03-14: Añadir DeviationDetected, DeviationEnded, DEVIATION_ENDED, EtaChanged domain events; EtaRecalculationListener con throttle 30s y detección de desvío
