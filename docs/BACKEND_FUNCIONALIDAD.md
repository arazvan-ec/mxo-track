# Backend — Documentación Funcional

## 1. Resumen del sistema

Portal logístico de tracking de vehículos y entregas construido sobre **Symfony 7.4 LTS** (strict lock, sin componentes 8.x). Monorepo con `backend/` (Symfony), `infra/` (provisioning) y `docs/`.

Integra **Traccar** (GPS tracking), **Mercure** (realtime SSE), **Redis** (sesiones) y **PostgreSQL 16**. Arquitectura multi-tenant por `customer_id` mediante Doctrine SQL filter.

**Stack**: PHP 8.4, Doctrine ORM 3.x (attribute mapping), Twig + Turbo (UX Turbo) para frontend.

---

## 2. Modelo de dominio (15 entidades)

### Patrón de identidad

Todas las entidades (excepto `CustomerVehicle`) usan `PublicIdTrait` (src/Entity/Concerns/PublicIdTrait.php):

- **PK interna**: `id` BIGINT auto-increment — joins, procesamiento interno
- **ID público**: `publicId` ULID — APIs, URLs, topics Mercure

El ULID se auto-genera en `PrePersist`. Nunca se expone el `id` interno en endpoints públicos.

### Tabla de entidades

| Entidad | Tabla | PublicIdTrait | CustomerScoped | Propósito |
|---------|-------|:---:|:---:|-----------|
| Customer | `customer` | ✓ | — | Tenant raíz |
| User | `user_account` | ✓ | — | Autenticación (ManyToOne Customer nullable) |
| Vehicle | `vehicle` | ✓ | — | Vehículo GPS (`traccarDeviceId`) |
| VehiclePosition | `vehicle_positions` | ✓ | — | Historial posiciones GPS |
| VehicleLastPosition | `vehicle_last_position` | ✓ | — | Última posición (OneToOne Vehicle) |
| VehicleCheckpoint | `vehicle_checkpoint` | ✓ | — | Último punto de sincronización Traccar |
| CustomerVehicle | `customer_vehicle` | — | ✓ | Relación N:M Customer↔Vehicle |
| Route | `route_plan` | ✓ | — | Ruta de entrega (driver + vehicle) |
| RouteStop | `route_stop` | ✓ | — | Parada individual con secuencia |
| Shipment | `shipment` | ✓ | ✓ | Envío con referencia única |
| ShipmentEvent | `shipment_event` | ✓ | — | Timeline de eventos del envío |
| Pod | `pod` | ✓ | — | Prueba de entrega (OneToOne RouteStop) |
| DriverAction | `driver_action` | ✓ | — | Idempotencia acciones driver |
| AuditLog | `audit_log` | ✓ | — | Auditoría de operaciones |
| CsvImportRun | `csv_import_run` | ✓ | ✓ | Registro de importaciones CSV |

### Relaciones clave

```
Customer ←── User (ManyToOne nullable)
Customer ←── Shipment (ManyToOne required)
Customer ←── CustomerVehicle ──→ Vehicle
Vehicle ←── VehiclePosition (ManyToOne)
Vehicle ←── VehicleLastPosition (OneToOne)
Vehicle ←── VehicleCheckpoint (OneToOne)
Route ──→ User (driver), Vehicle
Route ←── RouteStop (OneToMany cascade)
RouteStop ←── Pod (OneToOne)
RouteStop ←── DriverAction (ManyToOne)
Shipment ←── ShipmentEvent (ManyToOne cascade)
```

### Enums

| Enum | Valores |
|------|---------|
| `UserRole` | `ADMIN`, `OPERATOR`, `CUSTOMER`, `DRIVER` |
| `RouteStatus` | `PLANNED` → `ACTIVE` → `DONE` \| `CANCELLED` |
| `RouteStopStatus` | `PENDING` → `DELIVERED` \| `EXCEPTION` \| `SKIPPED` |
| `ShipmentEventType` | `CREATED`, `PICKED_UP`, `IN_HUB`, `IN_TRANSIT`, `OUT_FOR_DELIVERY`, `DELIVERED`, `EXCEPTION` |
| `ExceptionCode` | `ABSENT`, `WRONG_ADDRESS`, `REFUSED`, `DAMAGED`, `OTHER` |

---

## 3. Endpoints y controladores (10 controllers)

### SecurityController — Autenticación

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/login` | `app_login` | Formulario login con CSRF |
| GET | `/logout` | `app_logout` | Cierre de sesión (interceptado por firewall) |

### DashboardController — Landing

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/` | `app_dashboard` | Dashboard principal (requiere `IS_AUTHENTICATED_FULLY`) |

### AdminController — Panel administración

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/admin` | `admin_dashboard` | Dashboard KPIs (rutas activas, paradas pendientes, imports, posiciones) |
| GET | `/admin/health` | `admin_health` | Health check JSON (Traccar + Mercure) |
| GET | `/admin/health/live` | `admin_health_live` | Health ligero con latencias |

### AdminShipmentController — Importación CSV

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET/POST | `/admin/shipments/import` | `admin_shipments_import` | Formulario importación CSV + historial últimos 10 |

### AdminDevPushPositionController — Herramienta dev

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| POST | `/admin/dev/push-position` | `admin_dev_push_position` | Push manual de posición a Mercure (solo `ROLE_ADMIN`) |

### FleetMapController — Mapa realtime

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/fleet/map` | `fleet_map` | Mapa Leaflet.js + Mercure SSE |

### VehicleApiController — API vehículos

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/api/vehicles` | `api_vehicles` | Lista vehículos con última posición (scope por rol) |
| GET | `/api/vehicles/{publicId}/last-position` | `api_vehicle_last` | Última posición de un vehículo |
| GET | `/api/vehicles/{publicId}/positions` | `api_vehicle_positions` | Historial con paginación (`from`/`to`/`limit`/`offset`/`order`) |
| GET | `/api/vehicles/{publicId}/positions.csv` | `api_vehicle_positions_csv` | Export CSV posiciones |

### ShipmentApiController — API envíos

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/api/shipments` | `api_shipments_` | Lista envíos (`CUSTOMER`: solo los suyos) |
| GET | `/api/shipments/{publicId}` | — | Detalle con timeline de eventos |

### MercureTokenController — Token Mercure

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/api/mercure-token` | `api_mercure_token` | Genera JWT subscriber; acepta `vehicle_ids[]` como query params; setea cookie `mercureAuthorization` HttpOnly |

### DriverApiController — API driver

| Método | Ruta | Nombre | Descripción |
|--------|------|--------|-------------|
| GET | `/api/driver/routes` | — | Rutas asignadas al driver autenticado |
| POST | `/api/driver/routes/{routePublicId}/start` | — | Iniciar ruta (`PLANNED` → `ACTIVE`) |
| POST | `/api/driver/routes/{routePublicId}/finish` | — | Finalizar ruta (`ACTIVE` → `DONE`) |
| GET | `/api/driver/routes/{routePublicId}/stops` | — | Paradas de una ruta (orden secuencia) |
| POST | `/api/driver/stops/{stopPublicId}/deliver` | — | Entrega con POD (idempotente vía `clientActionId`) |
| POST | `/api/driver/stops/{stopPublicId}/exception` | — | Excepción en parada (idempotente vía `clientActionId`) |
| GET | `/api/driver/stops/{stopPublicId}/pod` | — | Metadata del POD |
| GET | `/api/driver/stops/{stopPublicId}/pod/download` | — | Datos del POD |

---

## 4. Servicios (13 servicios)

| Servicio | Propósito |
|----------|-----------|
| `TraccarApiClient` | Cliente HTTP para Traccar REST API (login, devices, positions) |
| `TraccarIngestionService` | Ingesta posiciones GPS → DB + publica a Mercure |
| `SystemHealthService` | Health check de Traccar y Mercure con latencia (`check()`, `checkLive()`) |
| `ShipmentCsvImporter` | Parseo CSV → creación Shipments + ShipmentEvent(CREATED). Retorna `['created' => int, 'skipped' => int]` |
| `ImportRunTracker` | Registro estadísticas de importación CSV |
| `DriverActionService` | Idempotencia de acciones driver vía `clientActionId`. `register()` retorna bool indicando si es nueva |
| `AuditLogger` | Log estructurado de acciones sensibles (actor, action, entityType, entityId, payload) |
| `MercureJwtFactory` | Genera JWT HS256 para suscripción Mercure |
| `TopicResolver` | Resuelve topics Mercure por rol (admin→fleet, customer→vehicles+routes+shipments, driver→vehicle asignado) |
| `VisibilityScopeService` | Determina visibilidad de vehículos/rutas por rol y customer |
| `AdminMetricsService` | Métricas sistema vía DBAL (rutas activas, paradas pendientes, imports del día, posiciones última hora) |
| `DeliveryEvidenceFactory` | Construye evidencia de entrega con `recipient_id_encoded`, `confirmation_mode` y fingerprint SHA256 |
| `DatabasePodStorage` | Almacenamiento POD en DB (implementa `PodStorageInterface`) |

---

## 5. Comandos CLI (6 comandos)

| Comando | Clase | Propósito | Estado |
|---------|-------|-----------|--------|
| `app:traccar:stream` | `TraccarStreamCommand` | Polling posiciones Traccar + backfill por checkpoint (`--once`, `--sleep=5`) | Activo |
| `app:traccar:sync-devices` | `TraccarSyncDevicesCommand` | Sincroniza dispositivos Traccar ↔ Vehicle (`--apply` para persistir) | Activo |
| `app:smoke:traccar-once` | `SmokeTraccarOnceCommand` | Smoke test conectividad Traccar (métricas: vehicles, devices, positions) | Activo |
| `app:smoke:csv-import` | `SmokeCsvImportCommand` | Smoke test importación CSV (`--customer-id`, `--file`) | Activo |
| `app:positions:downsample` | `PositionsDownsampleCommand` | Consolidación historial posiciones por ventana temporal (cron nocturno) | Placeholder |
| `app:positions:purge` | `PositionsPurgeCommand` | Purga historial por política de retención | Placeholder |

---

## 6. Seguridad y multi-tenancy

### Roles y jerarquía

Definidos en `security.yaml`:

```
ROLE_ADMIN: [ROLE_OPERATOR, ROLE_CUSTOMER, ROLE_DRIVER]
ROLE_OPERATOR: [ROLE_CUSTOMER]
```

`ROLE_ADMIN` hereda todos los roles. `ROLE_OPERATOR` hereda `ROLE_CUSTOMER`.

### Control de acceso

| Ruta | Roles requeridos |
|------|-----------------|
| `/login`, `/logout` | `PUBLIC_ACCESS` |
| `/admin/*` | `ROLE_ADMIN` o `ROLE_OPERATOR` |
| `/driver/*` | `ROLE_ADMIN` o `ROLE_DRIVER` |
| `/api/driver/*` | `ROLE_ADMIN` o `ROLE_DRIVER` |
| Todo lo demás (`/`) | `IS_AUTHENTICATED_FULLY` |

### Multi-tenancy

- `CustomerTenantFilter` (Doctrine SQLFilter) añade `WHERE customer_id = ?` automáticamente a entidades que implementan `CustomerScopedEntityInterface`
- `DoctrineCustomerFilterSubscriber` (prioridad 50 en `KernelEvents::REQUEST`) habilita el filtro para usuarios con `ROLE_CUSTOMER` o `ROLE_DRIVER` que tengan customer asignado
- Admins y Operators NO están afectados por el filtro

### Autenticación

- **Provider**: Entity provider sobre `User` (property: `email`)
- **Hasher**: `auto` (selección automática de algoritmo)
- **Form login**: `app_login` con CSRF habilitado
- **Rate limiting**: 5 intentos/minuto
- **UserChecker**: Valida que el usuario está activo antes de autenticación (`checkPreAuth`)

### Headers de seguridad

`SecurityHeadersSubscriber` añade en cada response:

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Content-Security-Policy: default-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'`

### Sesiones

- Almacenadas en Redis con prefix `sess:transporte:`
- Cookies: HttpOnly, Secure=auto, SameSite=lax

### Voters

| Voter | Atributos | Lógica |
|-------|-----------|--------|
| `BaseVoter` (abstracto) | — | Auto-grant para `ROLE_ADMIN`; rechaza usuarios inactivos |
| `UserVoter` | `user.view`, `user.manage` | `view`: propio perfil o `ROLE_OPERATOR`; `manage`: `ROLE_OPERATOR` |

---

## 7. Integraciones externas

### Traccar (GPS tracking)

- **Autenticación**: Por sesión/cookie
- **Polling**: `TraccarStreamCommand` consulta posiciones cada 5 segundos (configurable)
- **Sincronización**: `TraccarSyncDevicesCommand` mapea dispositivos Traccar a entidades Vehicle
- **Checkpoints**: `VehicleCheckpoint` evita re-procesamiento de posiciones ya ingeridas
- **Flujo**: Traccar → `TraccarApiClient` → `TraccarIngestionService` → DB (`VehiclePosition` + `VehicleLastPosition`) + Mercure publish

### Mercure (realtime SSE)

- **Topics por rol**:
  - Admin/Operator: `/operator/fleet`
  - Customer: `/vehicles/{publicId}/position`, `/customers/{customerId}/routes`, `/customers/{customerId}/shipments`
  - Driver: `/vehicles/{publicId}/position` (vehículos asignados)
- **JWT**: `MercureJwtFactory` genera tokens HS256 con TTL configurable
- **Auth**: Cookie `mercureAuthorization` HttpOnly (sin prefijo "Bearer") seteada por `MercureTokenController`
- **Publicación**: Configurada con `publish: ['*']` en `mercure.yaml` para autorizar al publisher a todos los topics
- **CORS**: En desarrollo, el hub Mercure usa `cors_origins http://localhost:8000` (no `*`, incompatible con `withCredentials`)

---

## 8. DTOs y validación

### DeliverStopInput

Payload para entrega de parada con POD.

| Campo | Tipo | Validación |
|-------|------|------------|
| `clientActionId` | string | `NotBlank`, `Uuid` |
| `signedByName` | string | `NotBlank`, max 120 |
| `recipientIdEncoded` | string | `NotBlank`, min 6, max 512 |
| `confirmedByDriver` | bool | `Type: bool` |
| `shipmentPublicId` | ?string | `Ulid`, nullable |

### ExceptionStopInput

Payload para reportar excepción en parada.

| Campo | Tipo | Validación |
|-------|------|------------|
| `clientActionId` | string | `NotBlank`, `Uuid` |
| `reason` | string | `Choice`: ABSENT, WRONG_ADDRESS, REFUSED, DAMAGED, OTHER |
| `comment` | string | max 4000 |
| `shipmentPublicId` | ?string | `Ulid`, nullable |

Ambos DTOs exponen factory `fromArray(array $payload): self` + validación vía Symfony Validator constraints.

---

## 9. Templates (frontend)

| Template | Propósito |
|----------|-----------|
| `base.html.twig` | Layout base HTML (idioma español) |
| `security/login.html.twig` | Formulario login con CSRF |
| `dashboard/index.html.twig` | Dashboard placeholder |
| `admin/dashboard.html.twig` | Panel KPIs admin |
| `admin/shipments_import.html.twig` | Import CSV + historial |
| `tracking/map.html.twig` | Mapa Leaflet.js con polyline histórica + tracking en vivo por Mercure SSE (centrado España) |
| `driver/route_execution.html.twig` | UI ejecución de ruta para driver |
