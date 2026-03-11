# Análisis Completo del Codebase — mxo-track

**Fecha:** 2026-03-11
**Última actualización:** 2026-03-11
**Estado:** Vigente
**Contexto:** Análisis exhaustivo de arquitectura, dominio, servicios, API, providers, tests e infraestructura para construir una base de conocimiento reutilizable entre sesiones.

---

## 1. Métricas del Proyecto

| Métrica | Valor |
|---------|-------|
| Archivos PHP (src/) | 383 |
| Archivos de test | 44 (39 Unit, 3 Functional, 2 Factory) |
| Tests totales | 249 |
| Assertions | 701 |
| Entidades | 35 |
| Controllers | 48 |
| Services | 64 |
| Commands | 11 |
| Migraciones | 21 |
| Enums | 10 |
| Providers configurables | 8 (2 routing, 2 optimizer, 2 GPS, 2 realtime) |

---

## 2. Modelo de Dominio

### 2.1 Entidades Core

| Entidad | Propósito | Scoped? | Soft Delete? | PublicId? |
|---------|-----------|---------|-------------|-----------|
| **Customer** | Tenant raíz | — | Sí | Sí |
| **User** | Usuarios (admin, operator, customer, driver) | customer_id (nullable) | Sí | Sí |
| **Vehicle** | Vehículos de flota (capacidad, skills, GPS device) | No (global) | Sí | Sí |
| **Shipment** | Envíos/entregas (destinatario, dirección, peso, volumen) | customer_id | Sí | Sí |
| **Route** | Rutas de entrega (vehículo, conductor, paradas) | customer_id (nullable) | Sí | Sí |
| **RouteStop** | Parada en ruta (secuencia, estado, POD) | Indirecto (vía Route) | No | Sí |
| **Pod** | Prueba de entrega (firma, destinatario) | Indirecto | No | Sí |

### 2.2 Entidades de Soporte

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
| **AuditLog** | Auditoría de seguridad |
| **ApiKey** | API key (hash SHA-256, nunca plain) |

### 2.3 Enums

| Enum | Valores | Uso |
|------|---------|-----|
| **UserRole** | ADMIN, OPERATOR, CUSTOMER, DRIVER | Roles de usuario |
| **RouteStatus** | PLANNED, ACTIVE, DONE, CANCELLED | Estado de ruta |
| **RouteStopStatus** | PENDING, DELIVERED, EXCEPTION, SKIPPED | Estado de parada |
| **ExceptionCode** | ABSENT, WRONG_ADDRESS, REFUSED, DAMAGED, OTHER | Razón de excepción |
| **ShipmentEventType** | CREATED, PICKED_UP, IN_HUB, IN_TRANSIT, OUT_FOR_DELIVERY, DELIVERED, EXCEPTION, RESCHEDULE_REQUESTED | Ciclo de vida |
| **ShipmentPriority** | LOW(0), NORMAL(25), HIGH(50), URGENT(75), CRITICAL(100) | Prioridad int-backed |
| **ServiceType** | ROUTING, ROUTE_OPTIMIZER, GPS, REALTIME | Tipos de provider |
| **VehicleSkill** | REFRIGERATED, HEAVY_LOAD, FRAGILE, etc. | Skills de vehículo (int-backed, JSON array) |
| **ParcelStatus** | REGISTERED → IN_WAREHOUSE → LOADED → IN_TRANSIT → DELIVERED | Estado de bulto |
| **ClientFrequency** | NOT_FREQUENT, FREQUENT, VERY_FREQUENT, SUPER_FREQUENT | Frecuencia de cliente |

### 2.4 Patrones de Identidad

- **Internal PK**: BIGINT auto-increment (`id`) — joins internos
- **Public ID**: ULID (`public_id`) via `PublicIdTrait` — APIs, URLs, Mercure topics
- **NUNCA exponer `id` interno** en APIs públicas

### 2.5 Multi-Tenancy

- `CustomerTenantFilter`: Doctrine SQL filter que añade `WHERE customer_id = ?`
- `CustomerScopedEntityInterface`: 9 entidades opt-in al filtro
- `DoctrineCustomerFilterSubscriber`: Activa el filtro para ROLE_CUSTOMER y ROLE_DRIVER
- Admin/Operator bypasean el filtro

### 2.6 Árbol de Relaciones (Agregado Principal)

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

---

## 3. Arquitectura de Capas

```
┌─────────────────────────────────────────────────┐
│  HTTP (Controllers, DTOs, Forms)                │
│  48 controllers (Admin, API v1, Driver, Customer)│
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│  Application Services                           │
│  RoutePlanningService, DeliveryService,          │
│  FleetTrackingService, etc.                      │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│  Domain Events                                   │
│  VehiclePositionReceived, StopDelivered,          │
│  RouteStarted, RouteCompleted, etc.               │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│  Ports (Interfaces)                              │
│  RoutingEngineInterface, RouteOptimizerInterface, │
│  GpsDeviceProviderInterface, RealtimePublisher    │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│  Provider Resolution (TenantAware Proxies)       │
│  TenantContext → ProviderResolver → Factory      │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│  Adapters (Implementations)                      │
│  OSRM, GoogleDirections, VROOM, Greedy,           │
│  Traccar, Webhook, Mercure, HttpPolling            │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│  External Systems                                │
│  OSRM API, Google Maps, Traccar, Mercure Hub,     │
│  Claude AI, Twilio, PostgreSQL, Redis              │
└─────────────────────────────────────────────────┘
```

---

## 4. Framework de Providers

### 4.1 Componentes Core

| Componente | Responsabilidad |
|------------|----------------|
| `ServiceType` (enum) | 4 tipos: RoutingEngine, RouteOptimizer, GpsProvider, RealtimePublisher |
| `ProviderFactoryInterface` | Contrato para factories (create, getProviderType, getServiceType) |
| `ProviderFactoryRegistry` | Registro central de factories, auto-discovered via tags |
| `ProviderResolver` | Consulta CustomerIntegration para resolver provider del tenant |
| `CachedProviderResolver` | Decorator con caché per-request (in-memory) |
| `TenantContext` | Extrae Customer del SecurityContext |
| `FallbackChain` | Cadena de proveedores por prioridad |
| `ProviderUnavailableException` | Excepción para fallos transitorios |

### 4.2 Providers Disponibles

| Servicio | Provider | Factory | Config Necesaria | Infra? |
|----------|----------|---------|-----------------|--------|
| Routing | OSRM | OsrmFactory | URL (opcional, env) | Sí |
| Routing | Google Directions | GoogleDirectionsFactory | API key | No |
| Optimizer | VROOM | VroomFactory | URL (opcional, env) | Sí |
| Optimizer | Greedy | GreedyOptimizerFactory | Ninguna | No |
| GPS | Traccar | TraccarFactory | Credenciales (opcional, env) | Sí |
| GPS | Webhook | WebhookGpsFactory | Ninguna | No |
| Realtime | Mercure | MercureFactory | Ninguna (global) | Sí |
| Realtime | HTTP Polling | HttpPollingFactory | Ninguna | No |

### 4.3 Flujo de Resolución

```
Código llama proxy.method() →
  TenantAwareProxy extrae Customer →
    CachedProviderResolver (caché per-request) →
      ProviderResolver consulta CustomerIntegration DB →
        ProviderFactoryRegistry.create(integration) →
          Factory.create(config) → Provider concreto
```

### 4.4 Cómo Añadir un Nuevo Provider

1. Añadir case al enum (`RoutingProvider::GraphHopper`)
2. Crear Config DTO (opcional) con `fromArray()`
3. Crear Engine implementando `RoutingEngineInterface`
4. Crear Factory implementando `ProviderFactoryInterface`
5. Wiring en services.yaml (si necesita env vars)
6. Auto-discovered vía `#[AutoconfigureTag]`

---

## 5. Servicios Principales

### 5.1 Operaciones Core

| Servicio | Responsabilidad |
|----------|----------------|
| `RouteBuilder` | Construye rutas optimizadas via VROOM (multi-vehículo) |
| `RoutePlanningService` | Planificación y optimización de rutas |
| `RouteLifecycleService` | Transiciones de estado (PLANNED → ACTIVE → DONE) |
| `RouteOptimizationService` | Re-optimización de rutas existentes |
| `DeliveryService` | Confirmación de entrega, excepciones, POD |
| `TraccarIngestionService` | Ingesta de posiciones GPS desde Traccar |
| `ShipmentCsvImporter` | Importación masiva de envíos desde CSV |

### 5.2 Analytics & Insights

| Servicio | Responsabilidad |
|----------|----------------|
| `AdminMetricsService` | KPIs del dashboard admin |
| `SlaMetricsService` | Tracking de cumplimiento SLA |
| `RouteAnalysisService` | Análisis post-ruta |
| `PostRouteAnalyzer` | Eficiencia de ruta completada |
| `RouteComparisonService` | Comparación planificado vs real |
| `ExceptionPatternService` | Patrones en entregas fallidas |
| `FleetAnomalyService` | Detección de anomalías en flota |

### 5.3 Driver-Facing

| Servicio | Responsabilidad |
|----------|----------------|
| `DriverBriefingService` | Generación de briefing para conductor |
| `DriverScoringService` | Scoring de rendimiento |
| `DriverActionService` | Acciones idempotentes (clientActionId) |
| `DriverAvailabilityService` | Disponibilidad semanal |
| `DriverAffinityService` | Afinidad conductor-cliente |

### 5.4 AI/ML

| Servicio | Responsabilidad |
|----------|----------------|
| `AiAssistantService` | Integración Claude API (tool loops) |
| `DeliveryNoteAiEnricher` | Enriquecimiento AI de notas |
| `ExceptionClassifierService` | Clasificación NLP de excepciones |
| `ShipmentClusteringService` | Clustering ML de envíos |

### 5.5 Mensajería Async (Symfony Messenger)

| Message | Handler | Queue | Propósito |
|---------|---------|-------|-----------|
| `EnrichRouteNotesMessage` | EnrichRouteNotesHandler | async | AI notes para paradas |
| `NlpClassificationMessage` | NlpClassificationHandler | ml | Clasificar excepciones |
| `PostRouteAnalysisMessage` | PostRouteAnalysisHandler | async | Análisis post-ruta |
| `FleetAnomalyCheckMessage` | FleetAnomalyCheckHandler | async | Detección anomalías |

---

## 6. API Surface

### 6.1 Driver API (Stateless JSON)

| Endpoint | Method | Propósito |
|----------|--------|-----------|
| `/api/driver/routes` | GET | Listar rutas del conductor |
| `/api/driver/routes/{id}/start` | POST | Iniciar ruta (requiere inspección) |
| `/api/driver/routes/{id}/finish` | POST | Finalizar ruta |
| `/api/driver/routes/{id}/stops` | GET | Listar paradas con navegación |
| `/api/driver/stops/{id}/deliver` | POST | Marcar entrega + POD |
| `/api/driver/stops/{id}/exception` | POST | Reportar excepción |
| `/api/driver/routes/{id}/briefing` | GET | Briefing de ruta |
| `/api/driver/routes/{id}/etas` | GET | ETAs de paradas |
| `/api/driver/routes/{id}/inspection` | GET/POST | Inspección vehicular |
| `/api/driver/stops/{id}/feedback` | POST | Feedback del conductor |

### 6.2 API v1 (API Key)

| Endpoint | Method | Propósito |
|----------|--------|-----------|
| `/api/v1/routes` | GET/POST | CRUD de rutas |
| `/api/v1/shipments` | GET/POST | CRUD de envíos |
| `/api/v1/webhooks` | GET/POST/DELETE | Gestión de webhooks |
| `/api/v1/events` | GET | Polling de eventos |

### 6.3 Internal APIs (Session Auth)

| Endpoint | Method | Propósito |
|----------|--------|-----------|
| `/api/routes/build` | POST | Construir rutas con VROOM |
| `/api/routes/{id}/optimize` | POST | Re-optimizar ruta |
| `/api/routes/{id}/validate-capacity` | GET | Validar capacidad |
| `/api/routes/{id}/timing` | GET | Estimación timing |
| `/api/vehicles` | GET | Listar vehículos |
| `/api/vehicles/{id}/positions` | GET | Historial posiciones |
| `/api/mercure-token` | GET | Token JWT para SSE |
| `/api/fleet/summary` | GET | Resumen de flota |
| `/api/search` | GET | Búsqueda full-text |

### 6.4 Public (Sin Auth)

| Endpoint | Method | Propósito |
|----------|--------|-----------|
| `/track/{token}` | GET | Tracking público de envío |
| `/track/{token}/rate` | GET/POST | Valoración post-entrega |
| `/track/{token}/reschedule` | GET/POST | Reprogramar entrega |

### 6.5 Admin Web (ROLE_ADMIN / ROLE_OPERATOR)

19 controllers cubriendo: customers, users, drivers, vehicles, routes, shipments, integrations, API keys, reports, billing, AI assistant, fleet map, route planner, templates, zones, SLA.

---

## 7. Event Subscribers & Listeners

### 7.1 HTTP Lifecycle (EventSubscriber/)

| Subscriber | Evento | Propósito |
|------------|--------|-----------|
| `DoctrineCustomerFilterSubscriber` | REQUEST | Multi-tenancy |
| `SecurityHeadersSubscriber` | RESPONSE | CSP, X-Frame-Options |
| `ApiRateLimitSubscriber` | REQUEST | Rate limiting por API key |
| `CsrfApiSubscriber` | REQUEST | CSRF en APIs |
| `LocaleSubscriber` | REQUEST | i18n |
| `LoginAuditSubscriber` | Security | Auditoría de login |
| `ApproachingNotificationSubscriber` | VehiclePositionReceived | SMS cuando conductor cerca (500m) |
| `RouteActivatedNotificationSubscriber` | — | Notificar activación de ruta |
| `ExceptionReoptimizationSubscriber` | — | Re-optimizar tras excepción |

### 7.2 Domain Events (EventListener/Domain/)

| Listener | Evento | Propósito |
|----------|--------|-----------|
| `MercurePositionListener` | VehiclePositionReceived | Publicar posición a Mercure |
| `MercureRouteProgressListener` | RouteStarted/StopDelivered/RouteCompleted | Publicar progreso |
| `NotifyDeliveryListener` | StopDelivered/StopExceptionReported | Notificaciones |
| `AuditDeliveryListener` | StopDelivered/StopExceptionReported | Auditoría |
| `AiEnrichmentListener` | RouteStarted | Dispatch async AI enrichment |
| `PostRouteAnalysisListener` | RouteCompleted | Dispatch async analysis |
| `FleetAnomalyCheckListener` | VehiclePositionReceived | Dispatch async anomaly check |
| `ShipmentEmbeddingListener` | ShipmentCreated | Dispatch async embedding |

---

## 8. Seguridad

### 8.1 Autenticación

- **Form Login**: `/login` con CSRF y rate limiting (5 intentos/min)
- **API Key**: Header `X-Api-Key`, stateless, SHA-256 hash verificado
- **Sesiones**: Redis con prefijo `sess:transporte:`, TTL 12h

### 8.2 Roles y Access Control

```
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER
```

| Path | Roles Requeridos |
|------|-----------------|
| `/admin/*` | ADMIN, OPERATOR |
| `/operator/*` | ADMIN, OPERATOR |
| `/driver/*`, `/api/driver/*` | ADMIN, DRIVER |
| `/api/v1/*` | API key o sesión |
| `/track/*` | Público |

### 8.3 Security Headers

CSP, X-Frame-Options: DENY, X-Content-Type-Options: nosniff, Referrer-Policy: strict-origin-when-cross-origin.

---

## 9. Infraestructura

### 9.1 Servicios Docker (Local)

| Servicio | Puerto | Propósito |
|----------|--------|-----------|
| app (PHP 8.4) | 8000 | Symfony backend |
| db (PostgreSQL 16) | 5432 | Base de datos principal |
| redis (Redis 7) | 6379 | Sesiones y caché |
| mercure | 3000 | SSE realtime |
| traccar | 8082 | GPS tracking |
| traccar_db (PostgreSQL) | 5433 | BD dedicada Traccar |
| osrm | 5000 | Routing engine |
| vroom | 5100 | VRP optimizer |

### 9.2 Railway (Producción)

Deploy mínimo: app-mxo + bbdd-mxo + redis-mxo + mercure-mxo (4 servicios).
Deploy completo: + traccar, traccar_db, osrm, vroom, worker (9 servicios).

### 9.3 Migrations

21 migraciones cubriendo: schema inicial → entidades de tracking → providers → notifications → analytics → AI/ML.

### 9.4 Tests

- **Unit**: 39 archivos (Provider framework: 17, Services: 8, Validation: 3, DTOs: 5+)
- **Functional**: 3 archivos (CustomerTenantFilter, RouteLifecycle, integration)
- **Factory**: TestEntityFactory para crear entidades de test
- **Coverage**: Provider framework bien cubierto, controllers con coverage parcial

---

## 10. Deuda Técnica Conocida

1. **GpsDeviceProviderInterface con métodos Traccar-específicos** — `login()` y `getSessionCookie()` no aplican a providers genéricos. Stubs como workaround.

2. **Mercure listeners usan HubInterface directamente** — Bypasean `RealtimePublisherInterface`. No funciona con HttpPollingPublisher.

3. **Sin encriptación de credenciales** — API keys en CustomerIntegration.config almacenadas en JSON plano.

4. **Sin circuit breaker** — Si VROOM/OSRM/Google falla, no hay mecanismo automático de circuit breaking.

5. **Ratio tests/código bajo** — 44 test files para 383 src files. Controllers y servicios de analytics poco cubiertos.

---

## Historial de actualizaciones

- 2026-03-11: Creación inicial — análisis completo del codebase (5 agentes paralelos)
