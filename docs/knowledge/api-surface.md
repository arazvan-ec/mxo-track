# API Surface

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Resumen

48 controllers organizados en 5 áreas: Admin Web, Driver API, API v1, Internal APIs, Public.

## Driver API (Stateless JSON, ROLE_DRIVER)

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

Notas:
- Usa `{publicId}` en route parameters, nunca ID interno
- Payloads usan `shipment_public_id` (no `shipment_id`)
- `DriverAction` con `clientActionId` para idempotencia

## API v1 (API Key via `X-Api-Key` header)

| Endpoint | Method | Propósito |
|----------|--------|-----------|
| `/api/v1/routes` | GET/POST | CRUD de rutas |
| `/api/v1/shipments` | GET/POST | CRUD de envíos |
| `/api/v1/webhooks` | GET/POST/DELETE | Gestión de webhooks |
| `/api/v1/events` | GET | Polling de eventos |

API keys almacenadas como SHA-256 hash en `ApiKey` entity.

## Internal APIs (Session Auth, ROLE_ADMIN/OPERATOR)

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
| `/api/routes/{publicId}/events` | GET | Historial de eventos de ruta |

## Public (Sin Auth)

| Endpoint | Method | Propósito |
|----------|--------|-----------|
| `/track/{token}` | GET | Tracking público de envío |
| `/track/{token}/rate` | GET/POST | Valoración post-entrega |
| `/track/{token}/reschedule` | GET/POST | Reprogramar entrega |

## Admin Web (19 controllers)

Área `/admin/*` con session auth. Cubre: customers, users, drivers, vehicles, routes, shipments, integrations, API keys, reports, billing, AI assistant, fleet map, route planner, templates, zones, SLA.

### Rutas Admin (RouteAdminController)

| Ruta | Method | Nombre | Propósito |
|------|--------|--------|-----------|
| `/admin/routes` | GET | `admin_routes_index` | Listado con filtros (estado, fecha, conductor, cliente) |
| `/admin/routes/new` | GET/POST | `admin_routes_new` | Crear ruta |
| `/admin/routes/{publicId}/show` | GET | `admin_routes_show` | Detalle de ruta con mapa en vivo (Mercure), lista de paradas reactiva, métricas de optimización |
| `/admin/routes/{publicId}/edit` | GET/POST | `admin_routes_edit` | Editar ruta y paradas |
| `/admin/routes/{publicId}/delete` | POST | `admin_routes_delete` | Cancelar ruta |
| `/admin/routes/{publicId}/analysis` | GET | `admin_routes_analysis` | Análisis post-ejecución (planificada vs ejecutada) |

### Rutas Customer (CustomerRouteController)

| Ruta | Method | Nombre | Propósito |
|------|--------|--------|-----------|
| `/customer/routes` | GET | `customer_routes_index` | Listado de rutas del cliente con paginación y filtro por estado |
| `/customer/routes/{publicId}` | GET | `customer_routes_show` | Detalle de ruta con mapa en vivo (Mercure) y lista de paradas reactiva |

### Rutas Driver (DriverWebController)

| Ruta | Method | Nombre | Propósito |
|------|--------|--------|-----------|
| `/driver/routes` | GET | `driver_routes_index` | Listado de rutas del conductor con contadores de paradas |
| `/driver/routes/{publicId}` | GET | `driver_routes_show` | Detalle de ruta con mapa en vivo (Mercure), ETAs, acciones de entrega/excepción |

## Patrones

- **Error responses**: `ApiErrorResponder` (formato consistente JSON)
- **DTOs**: `src/Dto/` con `fromArray()` factory + Symfony Validator constraints
- **Rate limiting**: `ApiRateLimitSubscriber` por API key
- **CSRF**: `CsrfApiSubscriber` para APIs con sesión

## Historial

- 2026-03-11: Creación inicial
- 2026-03-13: Añadir rutas web de Admin, Customer y Driver con detalle de endpoints
