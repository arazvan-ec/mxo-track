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
| `/api/routes/{publicId}/etas` | GET | ETAs de paradas (snapshot-first, fallback OSRM) |
| `/api/admin/dashboard` | GET | Dashboard admin: health + live + metrics + daily_deliveries + top_drivers |
| `/api/page-layouts/{pageKey}` | GET | Layout de widgets para una página (cascada: customer → global) |
| `/api/me/preferences` | GET | Preferencias del usuario autenticado (auto-crea defaults si no existe) |
| `/api/me/preferences` | PATCH | Actualizar preferencias (`widget_default_mode`: 'expanded'\|'collapsed') |

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

## List Filters

Patrón unificado para filtrado de listados en endpoints admin/customer. El filter
applier parsea query params, valida contra allowlist por controller, y aplica a
Doctrine QueryBuilder de forma composable.

- **Clase central:** `src/Service/List/ListFilterApplier.php`
- **Consumers:** controllers de `Route`, `Shipment`, `Customer`, `Driver`,
  `Vehicle` (admin listings)
- **Valor semántico:** cada filter se modela como `FilterCriterion` value object
  (field + operator + value). Avanzado (`FilterPredicateSet`) compone con AND/OR
- **Tests:** `backend/tests/Service/List/ListFilterApplierTest.php`

Logs representativos: `2026-04-10-advanced-filters-all-views.md`,
`2026-04-12-list-filter-applier-refactor.md`, `2026-04-12-customer-advanced-filters.md`.

## Historial

- 2026-03-11: Creación inicial
- 2026-03-13: Añadir rutas web de Admin, Customer y Driver con detalle de endpoints
- 2026-03-14: Añadir endpoint GET /api/routes/{publicId}/etas (snapshot-first)
