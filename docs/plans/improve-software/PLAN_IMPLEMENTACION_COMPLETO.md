# Plan de Implementacion: Mejoras de Producto mxo-track

## Estado: COMPLETADO (2026-03-09)

**15/15 features implementadas** + review socratico + todos los fixes aplicados.

| Fase | Features | Estado |
|------|----------|--------|
| Fase 1: Core & Quick Wins | F1.1, F1.2, F1.3, F1.4 | COMPLETADO |
| Fase 2: Valor al Cliente | F2.1, F2.2, F2.3 | COMPLETADO |
| Fase 3: Optimizacion Avanzada | F3.1, F3.2, F3.3 | COMPLETADO |
| Fase 4: Analytics & UX | F4.1, F4.2, F4.3 | COMPLETADO |
| Fase 5: Extras | F5.1, F5.2 | COMPLETADO |
| Review Socratico — Fase A: Bugs criticos | A.1–A.4 | COMPLETADO |
| Review Socratico — Fase B: Issues medios | B.1–B.6 | COMPLETADO |
| Review Socratico — Fase C: Mejoras | C.1–C.5 | COMPLETADO |
| Review Socratico — Fase D: Nuevas features | D.1–D.5 | COMPLETADO |
| Review Socratico — Fase E: Code review PR#21 | E.1–E.2 | COMPLETADO |

**Branch**: `claude/plan-product-improvements-8AeLX`
**Ultimo commit**: `f515c55` — fix: close remaining gaps from review

---

## Contexto

mxo-track es una plataforma de logistica de ultima milla (Symfony 7.4, PostgreSQL, VROOM+OSRM, Traccar, Mercure). El backend esta maduro (route optimization, CSV import, GPS tracking, multi-tenant, driver API con POD), pero faltan interfaces visuales clave y capas de valor anadido. Este plan detalla la implementacion de 15 features priorizadas en 5 fases, tras podar 4 features del plan original (chat conductor, eCommerce, prediccion volumen, fraude POD).

## Features Descartadas

| # | Feature | Motivo |
|---|---------|--------|
| 5.2 | Chat Conductor-Operador | WhatsApp/Telegram ya resuelve esto |
| 4.2 | Integracion eCommerce | Clientes no son tiendas online, API publica es suficiente |
| 6.1 | Prediccion de Volumen | Sin datos historicos suficientes aun |
| 6.3 | Deteccion Fraude POD | Sin datos historicos suficientes aun |

## Hallazgos Clave del Codebase (ya existe)

Servicios que reducen significativamente el esfuerzo:

| Servicio existente | Ruta | Relevancia |
|---|---|---|
| `SmsChannel` + `SmsProviderInterface` | `src/Notification/Channel/SmsChannel.php` | Infraestructura SMS lista, solo falta implementar proveedor real |
| `PreDeliveryTemplate` | `src/Notification/Template/PreDeliveryTemplate.php` | Template SMS/WhatsApp para notificacion pre-entrega |
| `DeliveryRatingService` | `src/Notification/DeliveryRatingService.php` | `submitRating()`, `getAverageRatingForDriver()` completos |
| `DeliverySlotService` | `src/Notification/DeliverySlotService.php` | `proposeSlots()`, `selectSlot()`, `confirmSlot()` completos |
| `DeliverySlot` entity | `src/Entity/DeliverySlot.php` | Lifecycle: proposed → selected → confirmed / expired |
| `DeliveryRating` entity | `src/Entity/DeliveryRating.php` | Score 1-5, comment, tags, recipientPhone |
| `PublicTrackingService` | `src/Application/Tracking/PublicTrackingService.php` | Base para pagina publica de tracking |
| `EtaService` | `src/Service/EtaService.php` | `calculateEtas()` con OSRM, `estimateArrival()` |
| `ReportingService` | `src/Service/ReportingService.php` | `getDeliveryReport()`, `getDriverPerformance()`, `getTrendData()`, `getDriverRanking()` |
| `ExceptionPatternService` | `src/Service/ExceptionPatternService.php` | `analyzePatterns()` por codigo, conductor, direccion |
| `AddressRiskService` | `src/Service/AddressRiskService.php` | `checkAddress()` con tasa de excepcion |
| `PostRouteAnalyzer` | `src/Service/PostRouteAnalyzer.php` | Analisis AI planificada vs real |
| `DriverAffinityService` | `src/Service/DriverAffinityService.php` | Afinidad conductor-zona, skills VROOM |
| `DeliveryZoneService` | `src/Service/DeliveryZoneService.php` | K-means clustering de zonas |
| `BillingService` | `src/Service/BillingService.php` | `getCustomerSummary()` |
| `NotificationChannelInterface` | `src/Notification/Channel/NotificationChannelInterface.php` | Interface `send(recipient, template)` |
| `RouteBuilder` | `src/Service/RouteBuilder.php` | Build routes via VROOM VRP solver |
| `RoutePlanningService` | `src/Application/Route/RoutePlanningService.php` | `buildRoutes()`, `optimizeRoute()`, `reorderStops()`, `addStop()` |

---

## FASE 1: Core & Quick Wins — COMPLETADO

### F1.1 — Planificador de Rutas con Preview (ALTA, Grande) — COMPLETADO

**Plan aprobado**: Implementar segun `docs/plans/improve-software/PLAN_PLANIFICADOR_RUTAS.md`

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Controller/Admin/RoutePlannerController.php` | Controller con 4 endpoints |
| `templates/admin/route_planner/index.html.twig` | Vista principal: wizard 3 pasos + mapa Leaflet |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `templates/_sidebar_content.html.twig` | Agregar link "Planificador" en seccion Rutas |

**Servicios a REUTILIZAR (sin modificar):**
- `RouteBuilder::buildRoutes()` — optimizacion VROOM
- `RouteCapacityValidator::validate()` — validacion capacidad
- `ShipmentCsvImporter` — import CSV desde modal
- `RoutePlanningService` — capa aplicacion

**Endpoints:**
```
GET  /admin/route-planner              → Vista Twig (wizard Alpine.js)
GET  /admin/route-planner/shipments    → JSON: envios sin ruta (filtro customer)
POST /admin/route-planner/preview      → JSON: rutas propuestas (sin persistir)
POST /admin/route-planner/confirm      → Persiste rutas, redirect a listado
```

**Pasos de implementacion:**
1. Crear `RoutePlannerController` con las 4 rutas
2. Endpoint `shipments`: query Shipments WHERE no RouteStop exists + filtro customer
3. Endpoint `preview`: RouteBuilder.buildRoutes() sin flush, serializar a JSON
4. Endpoint `confirm`: buildRoutes() + flush() + redirect
5. Template: grid 2 columnas (panel 400px + mapa flex-1), Leaflet centrado Madrid
6. Alpine.js component `routePlanner()`: wizard 3 pasos
7. Paso 1: dropdown cliente → fetch envios → tabla checkboxes + markers mapa
8. Paso 2: tarjetas vehiculos con capacidad + selector origen + max paradas
9. Paso 3: POST preview → polylines coloreadas + tarjetas resumen + confirmar/volver
10. Link en sidebar admin

**Verificacion:**
- Cargar fixtures → navegar `/admin/route-planner`
- Seleccionar cliente → ver envios en mapa
- Seleccionar vehiculos → optimizar → ver preview con rutas coloreadas
- Confirmar → verificar rutas en `/admin/routes`
- `make lint` sin errores

---

### F1.2 — Navegacion Integrada en App del Conductor (ALTA, Pequeno) — COMPLETADO

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `src/Controller/DriverApiController.php` | Agregar campo `navigationUrl` en response de stops |

**Reutilizar:** `RouteStop` (ya tiene lat/lng)

**Pasos:**
1. En `DriverApiController`, metodo que lista stops: agregar al JSON de cada stop:
   ```json
   {
     "navigationUrl": "https://www.google.com/maps/dir/?api=1&destination={lat},{lng}",
     "wazeUrl": "https://waze.com/ul?ll={lat},{lng}&navigate=yes"
   }
   ```
2. Crear helper privado `buildNavigationUrls(float $lat, float $lng): array` en el controller
3. Incluir URLs en respuesta de `GET /api/driver/routes/{id}/stops`

**Verificacion:**
- `GET /api/driver/routes/{routeId}/stops` → verificar que cada stop tiene `navigationUrl` y `wazeUrl`
- Abrir URL en navegador → debe abrir Google Maps/Waze con las coordenadas

---

### F1.3 — Rating de Entrega por Destinatario (BAJA, Pequeno) — COMPLETADO

**Ya existe:** `DeliveryRating` entity, `DeliveryRatingService.submitRating()`

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Controller/PublicTrackingController.php` | Controller publico (si no existe) |
| `templates/tracking/rate.html.twig` | Formulario de rating (estrellas 1-5 + comentario + tags) |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `templates/tracking/show.html.twig` (o equivalente) | Agregar link/seccion rating post-entrega |

**Reutilizar:**
- `DeliveryRatingService::submitRating()` — logica completa
- `DeliveryRatingService::getRatingForShipment()` — verificar si ya tiene rating
- `PublicTrackingService` — obtener info de tracking

**Pasos:**
1. Verificar si `PublicTrackingController` existe; si no, crear con ruta `GET /track/{token}`
2. Agregar endpoint `POST /track/{token}/rate` que recibe `{score, comment, tags}`
3. Validar que el shipment tiene status DELIVERED antes de permitir rating
4. Llamar a `DeliveryRatingService::submitRating()`
5. Template: 5 estrellas clickables (Alpine.js), textarea comentario, tags predefinidos ("Puntual", "Amable", "Rapido", "Cuidadoso")
6. Si ya tiene rating → mostrar rating existente (solo lectura)

**Verificacion:**
- Navegar a `/track/{token}` de un envio DELIVERED
- Seleccionar 4 estrellas + comentario → submit
- Verificar en DB que `delivery_rating` tiene el registro
- Intentar re-submit → debe mostrar "Ya valorado"

---

### F1.4 — Checklist Pre-Ruta del Vehiculo (BAJA, Pequeno) — COMPLETADO

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Entity/VehicleInspection.php` | Entity: route, driver, items (JSON), completedAt, notes |
| `src/Dto/VehicleInspectionDto.php` | DTO para API: array de items con checked boolean |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `src/Controller/DriverApiController.php` | Agregar `POST /api/driver/routes/{id}/inspection` y `GET .../inspection` |
| Una nueva migration | Tabla `vehicle_inspection` |

**DB: Nueva tabla `vehicle_inspection`:**
```
id (BIGINT PK), public_id (ULID), route_id (FK), driver_id (FK),
items (JSON: [{name, checked, note?}]), completed_at (TIMESTAMP NULL),
created_at (TIMESTAMP)
```

**Items predefinidos (configurables por array en servicio):**
- Neumaticos en buen estado
- Luces funcionan correctamente
- Carga asegurada
- Documentacion del vehiculo
- Nivel de combustible/carga

**Pasos:**
1. Crear entity `VehicleInspection` con PublicIdTrait
2. Crear migration
3. En `DriverApiController`:
   - `GET /api/driver/routes/{id}/inspection` → devuelve items con estado (o template vacio si no existe)
   - `POST /api/driver/routes/{id}/inspection` → persiste la inspeccion
4. Modificar `POST /api/driver/routes/{id}/start`: verificar que existe inspeccion completada (todos items checked). Si no → error 422 "Debe completar la inspeccion del vehiculo"
5. DTO con validacion Symfony Validator

**Verificacion:**
- `POST /api/driver/routes/{id}/start` sin inspeccion → 422
- `POST /api/driver/routes/{id}/inspection` con items → 201
- `POST /api/driver/routes/{id}/start` → 200 OK
- `make lint`

---

## FASE 2: Valor al Cliente — COMPLETADO

### F2.1 — Notificacion ETA al Destinatario (ALTA, Medio) — COMPLETADO

**Ya existe:** `SmsChannel`, `SmsProviderInterface`, `PreDeliveryTemplate`, `EtaService`, `NotificationChannelInterface`

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Notification/Provider/TwilioSmsProvider.php` | Implementacion de `SmsProviderInterface` con Twilio SDK |
| `src/Notification/Provider/NullSmsProvider.php` | Provider noop para desarrollo (log only) |
| `src/Notification/RecipientNotificationService.php` | Orquestador: calcula ETA → decide canal → envia |
| `src/Entity/RecipientNotification.php` | Entity para tracking de notificaciones enviadas |
| `src/EventSubscriber/RouteActivatedNotificationSubscriber.php` | Listener: al activar ruta, notifica destinatarios |
| `src/Notification/Template/DeliveryCompletedTemplate.php` | Template post-entrega con link de rating |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `config/services.yaml` | Registrar Twilio provider con env vars |
| `composer.json` | Agregar `twilio/sdk` |
| Una nueva migration | Tabla `recipient_notification` |

**DB: Nueva tabla `recipient_notification`:**
```
id (BIGINT PK), public_id (ULID), shipment_id (FK), channel (VARCHAR 20: sms/whatsapp/email),
template_name (VARCHAR 50), recipient (VARCHAR 100), status (VARCHAR 20: sent/failed/delivered),
sent_at (TIMESTAMP), error_message (TEXT NULL), created_at (TIMESTAMP)
```

**Reutilizar:**
- `EtaService::calculateEtas()` — calculo de ETAs con OSRM
- `SmsChannel::send()` — envio SMS
- `PreDeliveryTemplate` — template ya creado
- `NotificationChannelInterface` — interfaz de canales

**Triggers de notificacion:**
1. Ruta activada (PLANNED → ACTIVE): SMS inicial "Su entrega llega hoy entre X-Y"
2. Vehiculo a N paradas del destino (configurable, default 3): SMS "Su entrega llega en ~30 min"
3. Entrega completada: SMS con link de rating

**Pasos:**
1. `composer require twilio/sdk`
2. Crear `NullSmsProvider` (log only) para dev, `TwilioSmsProvider` para prod
3. Config en services.yaml: bind `SmsProviderInterface` a Twilio o Null segun env
4. Crear entity `RecipientNotification` con tracking de envio
5. Crear `RecipientNotificationService`:
   - `notifyRouteStarted(Route $route)`: para cada stop con shipment que tiene recipientPhone, calcular ETA y enviar SMS
   - `notifyApproaching(RouteStop $stop)`: enviar cuando esta a N paradas
   - `notifyDelivered(RouteStop $stop)`: enviar confirmacion + link rating
6. Crear subscriber para evento `RoutesBuilt` / cambio de status de ruta
7. Integrar trigger "approaching" en `TraccarStreamCommand` o en un command dedicado que monitorea progreso

**Verificacion:**
- Con `NullSmsProvider`: activar ruta → verificar en logs que se "enviaron" SMS
- Verificar registros en tabla `recipient_notification`
- Con Twilio (staging): verificar recepcion de SMS real
- `make lint`

---

### F2.2 — Dashboard de SLA y Cumplimiento (ALTA, Medio) — COMPLETADO

**Ya existe:** `ReportingService` con `getDeliveryReport()`, `getDriverPerformance()`, `getTrendData()`, `getDriverRanking()`, `getStopStatusDistribution()`

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Service/SlaMetricsService.php` | Metricas especificas de SLA: OTIF, % dentro de ventana, tiempo medio |
| `src/Controller/Admin/SlaReportController.php` | Controller para vista SLA |
| `templates/admin/reports/sla.html.twig` | Dashboard con graficos Chart.js |

**Reutilizar:**
- `ReportingService::getDeliveryReport()` — base de datos
- `ReportingService::getTrendData()` — tendencias
- `ReportingService::getDriverRanking()` — ranking conductores
- `ExceptionPatternService::analyzePatterns()` — analisis excepciones

**Metricas SLA a calcular en `SlaMetricsService`:**
```php
public function calculateSla(
    ?Customer $customer,
    DateTimeInterface $from,
    DateTimeInterface $to
): array
```
Retorna:
- `otif_rate`: % entregas On Time In Full (entregado dentro de ventana Y sin excepcion)
- `on_time_rate`: % entregas dentro de ventana de entrega
- `first_attempt_rate`: % entregas exitosas en primer intento
- `avg_delivery_time_minutes`: tiempo medio desde inicio ruta hasta entrega por parada
- `avg_stops_per_hour`: paradas por hora por conductor
- `exception_rate_by_type`: desglose por tipo de excepcion
- `sla_trend`: serie temporal (diaria/semanal) de OTIF

**Pasos:**
1. Crear `SlaMetricsService` con queries SQL optimizadas
2. Crear `SlaReportController`:
   - `GET /admin/reports/sla` → vista Twig
   - `GET /admin/reports/sla/data` → JSON para charts (Ajax)
   - `GET /admin/reports/sla/export` → PDF via wkhtmltopdf o DomPDF
3. Template con:
   - KPI cards arriba: OTIF, On-Time, First Attempt, Avg Time
   - Grafico de tendencia (Chart.js line chart)
   - Tabla ranking conductores (reutilizar `getDriverRanking()`)
   - Filtros: cliente, periodo (semana/mes/custom), conductor
4. Agregar `composer require dompdf/dompdf` para export PDF
5. Link en sidebar: seccion Reports → "SLA & Cumplimiento"

**Verificacion:**
- Navegar `/admin/reports/sla` → ver KPIs y graficos con datos de fixtures
- Filtrar por cliente → datos cambian
- Export PDF → archivo descargable con graficos
- `make lint`

---

### F2.3 — API Publica para Clientes (ALTA, Grande) — COMPLETADO

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Entity/ApiKey.php` | Entity: customer, key (hash), name, isActive, rateLimit, lastUsedAt |
| `src/Security/ApiKeyAuthenticator.php` | Custom authenticator Symfony Security |
| `src/Controller/Api/V1/ShipmentApiController.php` | CRUD envios |
| `src/Controller/Api/V1/RouteApiController.php` | Consulta rutas |
| `src/Controller/Api/V1/WebhookApiController.php` | Gestion webhooks |
| `src/Entity/WebhookEndpoint.php` | Entity: customer, url, events[], secret, isActive |
| `src/Service/WebhookDispatcher.php` | Envia eventos a endpoints registrados |
| `src/Dto/Api/V1/CreateShipmentRequest.php` | DTO validado |
| `src/Dto/Api/V1/ShipmentResponse.php` | DTO de respuesta |
| `src/EventSubscriber/ApiRateLimitSubscriber.php` | Rate limiting por API key |
| `config/packages/nelmio_api_doc.yaml` | Config OpenAPI |
| `templates/admin/api_keys/index.html.twig` | Admin: gestionar API keys por cliente |
| `src/Controller/Admin/ApiKeyAdminController.php` | CRUD API keys |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `config/packages/security.yaml` | Agregar firewall `api_v1` con custom authenticator |
| `composer.json` | Agregar `nelmio/api-doc-bundle` |
| `templates/_sidebar_content.html.twig` | Link "API Keys" en admin |
| Nuevas migrations | Tablas `api_key`, `webhook_endpoint` |

**DB: Tabla `api_key`:**
```
id (BIGINT PK), public_id (ULID), customer_id (FK), key_hash (VARCHAR 128 UNIQUE),
name (VARCHAR 100), is_active (BOOL), rate_limit_per_minute (INT DEFAULT 60),
last_used_at (TIMESTAMP NULL), created_at (TIMESTAMP)
```

**DB: Tabla `webhook_endpoint`:**
```
id (BIGINT PK), public_id (ULID), customer_id (FK), url (VARCHAR 500),
events (JSON: ["shipment.delivered", "route.started"]), secret (VARCHAR 128),
is_active (BOOL), created_at (TIMESTAMP)
```

**Endpoints API v1:**
```
# Autenticacion: Header X-Api-Key
# Base: /api/v1

POST   /shipments              → Crear envio(s) (bulk: array)
GET    /shipments              → Listar envios (paginado, filtros)
GET    /shipments/{publicId}   → Detalle envio
GET    /shipments/{publicId}/tracking → Estado + historial eventos

GET    /routes                 → Listar rutas del cliente
GET    /routes/{publicId}      → Detalle ruta con paradas

POST   /webhooks               → Registrar endpoint
GET    /webhooks               → Listar endpoints
DELETE /webhooks/{publicId}    → Eliminar endpoint
```

**Pasos:**
1. Crear entity `ApiKey` con hash bcrypt de la key
2. `ApiKeyAuthenticator` que lee `X-Api-Key`, busca por hash, devuelve usuario del Customer
3. Firewall en security.yaml: `api_v1` pattern `^/api/v1`, custom_authenticators
4. Rate limit: Redis counter por key, 60 req/min default
5. Controllers con `#[OA\Tag]` annotations para OpenAPI
6. `composer require nelmio/api-doc-bundle` → Swagger UI en `/api/v1/doc`
7. Admin UI para crear/revocar API keys por cliente
8. `WebhookDispatcher` con async dispatch (Symfony Messenger) y retry 3x
9. Documentacion OpenAPI autogenerada

**Verificacion:**
- Crear API key desde admin → copiar key
- `curl -H "X-Api-Key: {key}" /api/v1/shipments` → 200 con envios del cliente
- `curl` sin key → 401
- `curl` con key de otro cliente → no ve envios ajenos (multi-tenant)
- Swagger UI accesible en `/api/v1/doc`
- Rate limit: 61 requests en 1 minuto → 429
- `make lint`

---

## FASE 3: Optimizacion Avanzada — COMPLETADO

### F3.1 — Reoptimizacion Dinamica en Ruta (MEDIA, Medio) — COMPLETADO

**Ya existe:** `RouteOptimizationService.optimizeStopOrder()`, Mercure para notificaciones

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/EventSubscriber/ExceptionReoptimizationSubscriber.php` | Listener: tras excepcion, trigger reoptimizacion |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `src/Controller/RouteOptimizationApiController.php` | Nuevo endpoint `POST /api/routes/{id}/reoptimize` para paradas PENDING |
| `src/Service/RouteOptimizationService.php` | Metodo `reoptimizePendingStops()` que filtra solo stops PENDING |
| `src/Controller/DriverApiController.php` | Notificar via Mercure al driver cuando se reordena |

**Reutilizar:**
- `RouteOptimizationService::optimizeStopOrder()` — base de la reoptimizacion
- `EtaService::calculateEtas()` — nuevas ETAs tras reoptimizacion
- Mercure topics `/routes/{id}` para push al conductor

**Pasos:**
1. Agregar metodo `reoptimizePendingStops(Route $route, ?float $currentLat, ?float $currentLng)` en `RouteOptimizationService`
2. Filtra solo paradas con status PENDING, usa posicion actual del conductor como origen
3. Endpoint API: `POST /api/routes/{publicId}/reoptimize` con body `{currentLat, currentLng}`
4. Subscriber escucha evento de excepcion (`ShipmentEvent::EXCEPTION`) → auto-trigger si route.autoReoptimize = true
5. Tras reoptimizar: publicar via Mercure a topic `/routes/{publicId}/updates` con nuevo orden
6. Agregar campo `auto_reoptimize` (bool, default false) en entity Route

**Verificacion:**
- Ruta activa con 5 paradas PENDING → reportar excepcion en parada 2
- Verificar que paradas 3-5 se reordenan automaticamente
- Driver API: verificar que recibe push Mercure con nuevo orden
- `make lint`

---

### F3.2 — Agrupacion de Envios por Zona (Clustering) (MEDIA, Medio) — COMPLETADO

**Ya existe:** `DeliveryZoneService.computeZones()` con k-means

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Service/ShipmentClusteringService.php` | Clustering local (sin ML sidecar) por coordenadas |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `templates/admin/route_planner/index.html.twig` | Paso 1: boton "Agrupar por zona" que colorea markers |
| `src/Controller/Admin/RoutePlannerController.php` | Endpoint `POST /admin/route-planner/cluster` |

**Reutilizar:**
- `DeliveryZoneService` — clustering existente (usa ML sidecar)

**Pasos:**
1. Crear `ShipmentClusteringService` con clustering simple por coordenadas:
   - Input: array de shipments con lat/lng, numero de clusters (= numero de vehiculos)
   - Algoritmo: k-means simplificado (no necesita ML sidecar): centroide iterativo en PHP
   - Output: array de clusters `[{centroid: {lat, lng}, shipmentIds: [...]}]`
2. Alternativa: reutilizar `DeliveryZoneService` si ML sidecar esta disponible
3. Endpoint `POST /admin/route-planner/cluster`:
   - Input: `{shipment_ids: [...], num_clusters: N}`
   - Output: clusters con colores asignados
4. En template del planificador (Paso 1): boton "Agrupar por zona"
   - Markers se colorean por cluster
   - Panel muestra resumen por cluster (num envios, peso total)
5. Los clusters se pasan como hint al RouteBuilder para mejor asignacion vehiculo-zona

**Verificacion:**
- Planificador: seleccionar 20 envios → click "Agrupar" → markers coloreados por zona
- Verificar que clusters son geograficamente coherentes
- `make lint`

---

### F3.3 — Asignacion Inteligente de Conductores (MEDIA, Medio) — COMPLETADO

**Ya existe:** `DriverAffinityService` con afinidad por zona, `ReportingService.getDriverPerformance()`

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Service/DriverScoringService.php` | Scoring multi-criterio para asignacion |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `src/Controller/Admin/RoutePlannerController.php` | Sugerencia de conductor al asignar ruta |
| `templates/admin/route_planner/index.html.twig` | UI de sugerencia en paso 3 |

**Reutilizar:**
- `DriverAffinityService::getAffinityScores()` — afinidad zona
- `ReportingService::getDriverPerformance()` — KPIs del conductor
- `DeliveryRatingService::getAverageRatingForDriver()` — rating medio

**Scoring (reglas simples, sin ML):**
```php
class DriverScoringService {
    public function scoreDriversForRoute(Route $route): array
    // Retorna [{driver, score, breakdown: {zone: 0.3, rating: 0.25, workload: 0.2, skills: 0.25}}]
}
```

Criterios (pesos configurables):
- **Afinidad zona** (30%): de DriverAffinityService
- **Rating medio** (25%): de DeliveryRatingService
- **Carga de trabajo** (20%): rutas activas esta semana (menos = mejor)
- **Skills match** (25%): % de skills requeridos que tiene el vehiculo del conductor

**Pasos:**
1. Crear `DriverScoringService` con metodo `scoreDriversForRoute(Route $route)`
2. Query conductores activos del customer
3. Calcular score multi-criterio normalizado (0-100)
4. En planificador paso 3: al mostrar cada ruta generada, incluir "Conductor sugerido" con score
5. UI: dropdown de conductor con scores, badge con justificacion (ej: "Conoce la zona: 85%")
6. Endpoint: `GET /admin/route-planner/suggest-drivers?route_data={...}`

**Verificacion:**
- Planificador paso 3 → ver sugerencia de conductor con score
- Conductor con mas entregas en la zona debe tener score mas alto
- `make lint`

---

## FASE 4: Analytics & UX — COMPLETADO

### F4.1 — Mapa de Calor de Excepciones (MEDIA, Medio) — COMPLETADO

**Ya existe:** `ExceptionPatternService.analyzePatterns()`, `AddressRiskService`

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Controller/Admin/ExceptionMapController.php` | Controller para mapa de calor |
| `templates/admin/reports/exception_map.html.twig` | Mapa Leaflet con heatmap layer |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `templates/_sidebar_content.html.twig` | Link "Mapa Excepciones" en Reports |

**Reutilizar:**
- `ExceptionPatternService::analyzePatterns()` — datos de excepciones por direccion
- `AddressRiskService::checkAddress()` — risk scores

**Pasos:**
1. Crear `ExceptionMapController`:
   - `GET /admin/reports/exception-map` → vista Twig
   - `GET /admin/reports/exception-map/data` → JSON con puntos `[{lat, lng, weight, exceptionCode, address}]`
2. Query: RouteStops con status EXCEPTION, agrupados por lat/lng redondeado a 4 decimales
3. Template: Leaflet + plugin `leaflet-heat` (CDN)
4. Filtros: tipo excepcion (dropdown ExceptionCode), periodo, cliente
5. Click en zona caliente → popup con detalle: direcciones, tipos, frecuencia
6. Panel lateral con top 10 direcciones problematicas

**Verificacion:**
- Navegar `/admin/reports/exception-map` → ver mapa con zonas de calor
- Filtrar por "ABSENT" → solo zonas con ese tipo
- Click en zona → ver detalle
- `make lint`

---

### F4.2 — Comparativa Rutas Planificadas vs Ejecutadas (MEDIA, Medio) — COMPLETADO

**Ya existe:** `PostRouteAnalyzer` (ai_analysis JSON), `VehiclePosition` entity

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Controller/Admin/RouteAnalysisController.php` | Controller para vista analisis |
| `templates/admin/route/analysis.html.twig` | Mapa con overlay planificada vs real |
| `src/Service/RouteComparisonService.php` | Calcula metricas de desviacion |

**Reutilizar:**
- `PostRouteAnalyzer::analyze()` — analisis AI
- `VehiclePosition` — posiciones GPS reales

**Pasos:**
1. Crear `RouteComparisonService`:
   ```php
   public function compare(Route $route): RouteComparison
   // Retorna: {plannedPolyline, actualPolyline, deviationKm, extraTimeMinutes,
   //           unplannedStops, missedStops, plannedDistanceKm, actualDistanceKm}
   ```
2. Obtener polyline planificada: conectar RouteStops en secuencia
3. Obtener polyline real: `VehiclePosition` filtrado por Route.vehicle + Route.startAt/endAt
4. Calcular desviaciones: distancia total extra, tiempo extra, paradas fuera de secuencia
5. Controller:
   - `GET /admin/routes/{publicId}/analysis` → vista con mapa
6. Template: Leaflet con dos polylines (azul=planificada, roja=real), markers para paradas
7. Panel lateral: metricas de desviacion + `ai_analysis` del PostRouteAnalyzer
8. Agregar boton "Analisis" en lista de rutas completadas (status DONE)

**Verificacion:**
- Ruta DONE con posiciones GPS → ver overlay en mapa
- Verificar que polyline real corresponde a posiciones GPS
- Metricas de desviacion coherentes
- `make lint`

---

### F4.3 — Reprogramacion por el Destinatario (MEDIA, Medio) — COMPLETADO

**Ya existe:** `DeliverySlotService` (completo), `DeliverySlot` entity (lifecycle completo)

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `templates/tracking/reschedule.html.twig` | Formulario de seleccion de slot |
| `src/Notification/Template/RescheduleConfirmationTemplate.php` | SMS confirmacion de reprogramacion |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `PublicTrackingController` | Endpoints `GET /track/{token}/reschedule` y `POST /track/{token}/reschedule` |
| `src/Enum/ShipmentEventType.php` | Agregar `RESCHEDULE_REQUESTED` si no existe |

**Reutilizar:**
- `DeliverySlotService::proposeSlots()` — crear opciones
- `DeliverySlotService::selectSlot()` — seleccion por destinatario
- `DeliverySlotService::confirmSlot()` — confirmacion por operador
- `RecipientNotificationService` (F2.1) — notificar confirmacion

**Pasos:**
1. En `PublicTrackingController`:
   - `GET /track/{token}/reschedule`: mostrar slots disponibles (propuestos por el sistema)
   - `POST /track/{token}/reschedule`: el destinatario selecciona un slot
2. Generar slots automaticos: manana, pasado manana, proxima semana (9-13h y 14-18h)
3. Template: tarjetas de slots con fecha/hora, seleccion con radio buttons
4. Opcion "Dejar en porteria / Dejar con vecino" como alternativa a reprogramar
5. Tras seleccion: crear `ShipmentEvent(RESCHEDULE_REQUESTED)`, notificar operador
6. Operador confirma slot → `confirmSlot()` → SMS confirmacion al destinatario

**Verificacion:**
- Navegar `/track/{token}/reschedule` → ver opciones de horario
- Seleccionar slot → verificar ShipmentEvent RESCHEDULE_REQUESTED
- Operador confirma → verificar DeliverySlot status = confirmed
- `make lint`

---

## FASE 5: Extras — COMPLETADO

### F5.1 — Disponibilidad y Horarios de Conductores (BAJA, Medio) — COMPLETADO

**Nota:** Conductores son autonomos/mixto — no control de jornada legal, sino gestion de disponibilidad.

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Entity/DriverAvailability.php` | Entity: driver, dayOfWeek, startTime, endTime, isAvailable |
| `src/Service/DriverAvailabilityService.php` | Gestionar disponibilidad, query disponibles |
| `src/Controller/Admin/DriverAvailabilityController.php` | Admin UI |
| `templates/admin/driver/availability.html.twig` | Calendario semanal de disponibilidad |

**DB: Tabla `driver_availability`:**
```
id (BIGINT PK), public_id (ULID), driver_id (FK User), day_of_week (SMALLINT 0-6),
start_time (TIME), end_time (TIME), is_available (BOOL DEFAULT true),
notes (TEXT NULL), created_at (TIMESTAMP)
```

**Pasos:**
1. Entity `DriverAvailability` con PublicIdTrait
2. `DriverAvailabilityService`:
   - `getAvailableDrivers(DateTimeInterface $date, TimeImmutable $startTime)`: drivers disponibles
   - `setWeeklySchedule(User $driver, array $schedule)`: configurar horario semanal
3. Controller admin con CRUD
4. Template: grid semanal (lunes-domingo x horas) con toggles de disponibilidad
5. Integrar con planificador (F1.1): al asignar conductor, mostrar solo disponibles
6. Integrar con `DriverScoringService` (F3.3): penalizar conductores no disponibles

**Verificacion:**
- Configurar disponibilidad de conductor: L-V 8-16h
- En planificador (sabado) → conductor no aparece como disponible
- `make lint`

---

### F5.2 — Exportacion Contable (BAJA, Pequeno) — COMPLETADO

**Ya existe:** `BillingService.getCustomerSummary()`

**Archivos a CREAR:**

| Archivo | Descripcion |
|---|---|
| `src/Service/AccountingExportService.php` | Genera CSV/XLSX para contabilidad |
| `src/Controller/Admin/AccountingExportController.php` | Endpoint de descarga |

**Archivos a MODIFICAR:**

| Archivo | Cambio |
|---|---|
| `templates/admin/billing/index.html.twig` | Boton "Exportar" con selector formato |

**Reutilizar:**
- `BillingService::getCustomerSummary()` — datos base

**Pasos:**
1. Crear `AccountingExportService`:
   - `exportCsv(Customer $customer, DateTimeInterface $from, DateTimeInterface $to): string` → CSV con: fecha, referencia envio, destinatario, tipo servicio, estado, peso
   - `exportSummary(...)`: resumen por cliente para facturacion
2. Controller: `GET /admin/billing/export?customer={id}&from={date}&to={date}&format=csv`
3. Response: `StreamedResponse` con headers Content-Disposition
4. Boton en vista de billing existente

**Verificacion:**
- Navegar a billing → click "Exportar CSV" → descarga archivo
- Abrir CSV → datos correctos y formateados
- `make lint`

---

## Orden de Implementacion y Dependencias

```
FASE 1 (paralelo):
  F1.1 Planificador ──────────────────┐
  F1.2 Navegacion (independiente)     │
  F1.3 Rating (independiente)         │
  F1.4 Checklist (independiente)      │
                                      │
FASE 2 (secuencial parcial):          │
  F2.1 Notificacion ETA ─────────────┤ (necesita F1.3 para link rating)
  F2.2 Dashboard SLA (independiente)  │
  F2.3 API Publica (independiente)    │
                                      │
FASE 3 (depende de F1.1):            │
  F3.1 Reoptimizacion ←──────────────┘
  F3.2 Clustering → mejora F1.1
  F3.3 Asignacion Inteligente → mejora F1.1

FASE 4 (independiente):
  F4.1 Mapa Calor (independiente)
  F4.2 Planificada vs Ejecutada (independiente)
  F4.3 Reprogramacion ← depende de F2.1

FASE 5 (independiente):
  F5.1 Disponibilidad Conductores → mejora F1.1, F3.3
  F5.2 Exportacion Contable (independiente)
```

## Verificacion Global

1. **Por cada feature**: `make lint` sin errores
2. **Integracion**: test manual del flujo completo CSV → planificador → ruta → conductor → entrega → notificacion → rating
3. **Multi-tenant**: verificar que API publica y dashboards respetan aislamiento por customer
4. **Performance**: dashboard SLA y mapa de calor con >1000 registros deben cargar en <3s
