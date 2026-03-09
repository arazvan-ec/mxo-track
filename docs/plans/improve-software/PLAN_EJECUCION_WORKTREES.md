# Plan de Ejecucion: Worktrees Atomicos para 15 Features

## Contexto

El plan de implementación completo (`PLAN_IMPLEMENTACION_COMPLETO.md`) ya está commiteado en el repo. Este documento define la **estrategia de ejecución** usando git worktrees para aislar cada feature y commits ultra-atómicos para trazabilidad.

## Estrategia General

- **1 worktree por feature** → aislamiento total, permite trabajo en paralelo
- **Branch naming**: `feature/F{fase}.{num}-{slug}` (ej: `feature/F1.1-route-planner`)
- **Todas las branches parten de**: `claude/plan-product-improvements-8AeLX` (branch actual con el plan)
- **Merge**: al completar cada feature, merge a la branch principal via PR
- **Commits atómicos**: cada commit hace UNA sola cosa (entity, migration, service, controller, template, sidebar link, etc.)

---

## Mapa de Worktrees y Branches

| Worktree | Branch | Feature | Depende de | Commits estimados |
|---|---|---|---|---|
| `wt-F1.1` | `feature/F1.1-route-planner` | Planificador Rutas | — | 8 |
| `wt-F1.2` | `feature/F1.2-driver-navigation` | Navegacion Conductor | — | 2 |
| `wt-F1.3` | `feature/F1.3-delivery-rating` | Rating Entrega | — | 4 |
| `wt-F1.4` | `feature/F1.4-vehicle-inspection` | Checklist Pre-Ruta | — | 5 |
| `wt-F2.1` | `feature/F2.1-eta-notifications` | Notificacion ETA | F1.3 | 7 |
| `wt-F2.2` | `feature/F2.2-sla-dashboard` | Dashboard SLA | — | 5 |
| `wt-F2.3` | `feature/F2.3-public-api` | API Publica | — | 10 |
| `wt-F3.1` | `feature/F3.1-dynamic-reoptimize` | Reoptimizacion | F1.1 | 4 |
| `wt-F3.2` | `feature/F3.2-zone-clustering` | Clustering Zonas | F1.1 | 4 |
| `wt-F3.3` | `feature/F3.3-driver-scoring` | Asignacion Inteligente | F1.1 | 4 |
| `wt-F4.1` | `feature/F4.1-exception-heatmap` | Mapa Calor | — | 4 |
| `wt-F4.2` | `feature/F4.2-route-comparison` | Plan vs Real | — | 5 |
| `wt-F4.3` | `feature/F4.3-reschedule` | Reprogramacion | F2.1 | 4 |
| `wt-F5.1` | `feature/F5.1-driver-availability` | Disponibilidad | — | 5 |
| `wt-F5.2` | `feature/F5.2-accounting-export` | Export Contable | — | 3 |

---

## Commits Atomicos por Feature

### F1.1 — Planificador de Rutas (8 commits)

```
1. feat(route-planner): add RoutePlannerController with empty endpoints
   → src/Controller/Admin/RoutePlannerController.php (4 rutas vacias con stubs)

2. feat(route-planner): implement shipments endpoint with unassigned filter
   → RoutePlannerController::shipments() — query sin RouteStop + serializar JSON

3. feat(route-planner): implement preview endpoint using RouteBuilder
   → RoutePlannerController::preview() — RouteBuilder.buildRoutes() sin flush

4. feat(route-planner): implement confirm endpoint with persistence
   → RoutePlannerController::confirm() — flush + redirect

5. feat(route-planner): add base template with Leaflet map layout
   → templates/admin/route_planner/index.html.twig — grid 2 cols, mapa, contenedor Alpine

6. feat(route-planner): implement step 1 - customer and shipment selection
   → Template: dropdown cliente, fetch envios, tabla checkboxes, markers mapa

7. feat(route-planner): implement steps 2-3 - vehicle config and preview
   → Template: tarjetas vehiculos, POST preview, polylines coloreadas, confirmar

8. feat(route-planner): add sidebar link for route planner
   → templates/_sidebar_content.html.twig — link en seccion Rutas
```

### F1.2 — Navegacion Conductor (2 commits)

```
1. feat(driver-nav): add buildNavigationUrls helper to DriverApiController
   → Metodo privado que genera URLs Google Maps + Waze

2. feat(driver-nav): include navigation URLs in stops API response
   → Agregar navigationUrl y wazeUrl en JSON de cada stop
```

### F1.3 — Rating Entrega (4 commits)

```
1. feat(rating): create PublicTrackingController with track endpoint
   → src/Controller/PublicTrackingController.php — GET /track/{token}

2. feat(rating): add POST rate endpoint using DeliveryRatingService
   → POST /track/{token}/rate — validacion DELIVERED + submitRating()

3. feat(rating): create rating form template with star picker
   → templates/tracking/rate.html.twig — estrellas Alpine.js, tags, comentario

4. feat(rating): add rating section to tracking show template
   → Enlace/seccion rating post-entrega en show.html.twig
```

### F1.4 — Checklist Pre-Ruta (5 commits)

```
1. feat(inspection): create VehicleInspection entity
   → src/Entity/VehicleInspection.php con PublicIdTrait

2. feat(inspection): add database migration for vehicle_inspection table
   → Migration con tabla vehicle_inspection

3. feat(inspection): create VehicleInspectionDto with validation
   → src/Dto/VehicleInspectionDto.php con Symfony Validator

4. feat(inspection): add inspection GET/POST endpoints to DriverApiController
   → GET + POST /api/driver/routes/{id}/inspection

5. feat(inspection): require completed inspection before route start
   → Modificar POST /api/driver/routes/{id}/start — 422 si no hay inspeccion
```

### F2.1 — Notificacion ETA (7 commits)

```
1. feat(notifications): create RecipientNotification entity and migration
   → Entity + migration tabla recipient_notification

2. feat(notifications): create NullSmsProvider for development
   → src/Notification/Provider/NullSmsProvider.php — log only

3. feat(notifications): create TwilioSmsProvider implementation
   → src/Notification/Provider/TwilioSmsProvider.php + composer require twilio/sdk

4. feat(notifications): configure SMS provider binding in services.yaml
   → services.yaml: SmsProviderInterface → Twilio/Null segun env

5. feat(notifications): create RecipientNotificationService orchestrator
   → notifyRouteStarted(), notifyApproaching(), notifyDelivered()

6. feat(notifications): create DeliveryCompletedTemplate with rating link
   → src/Notification/Template/DeliveryCompletedTemplate.php

7. feat(notifications): add RouteActivatedNotificationSubscriber
   → Listener: ruta activada → SMS a todos los destinatarios
```

### F2.2 — Dashboard SLA (5 commits)

```
1. feat(sla): create SlaMetricsService with SQL queries
   → calculateSla() — OTIF, on-time, first-attempt, trends

2. feat(sla): create SlaReportController with view and data endpoints
   → GET /admin/reports/sla + /sla/data (JSON)

3. feat(sla): create SLA dashboard template with Chart.js
   → KPI cards, line chart tendencia, tabla ranking

4. feat(sla): add PDF export endpoint using DomPDF
   → GET /admin/reports/sla/export + composer require dompdf/dompdf

5. feat(sla): add sidebar link for SLA reports
   → Link en seccion Reports
```

### F2.3 — API Publica (10 commits)

```
1. feat(api): create ApiKey entity and migration
   → Entity con hash, customer FK, rate limit

2. feat(api): create WebhookEndpoint entity and migration
   → Entity con url, events JSON, secret

3. feat(api): implement ApiKeyAuthenticator
   → Custom authenticator: X-Api-Key header → customer user

4. feat(api): configure api_v1 firewall in security.yaml
   → Firewall pattern ^/api/v1 con custom authenticator

5. feat(api): create ShipmentApiController with CRUD endpoints
   → POST/GET /api/v1/shipments, GET .../tracking

6. feat(api): create RouteApiController with query endpoints
   → GET /api/v1/routes, GET .../stops

7. feat(api): create WebhookApiController and WebhookDispatcher
   → POST/GET/DELETE /api/v1/webhooks + async dispatch

8. feat(api): add ApiRateLimitSubscriber with Redis counter
   → Rate limit por API key, 60 req/min default

9. feat(api): create ApiKeyAdminController with admin UI
   → CRUD API keys por cliente + template

10. feat(api): configure NelmioApiDoc for Swagger UI
    → config + composer require nelmio/api-doc-bundle → /api/v1/doc
```

### F3.1 — Reoptimizacion Dinamica (4 commits)

```
1. feat(reoptimize): add auto_reoptimize field to Route entity + migration
   → Campo bool, default false

2. feat(reoptimize): add reoptimizePendingStops to RouteOptimizationService
   → Filtra PENDING, usa posicion actual como origen

3. feat(reoptimize): add reoptimize API endpoint
   → POST /api/routes/{publicId}/reoptimize + Mercure push

4. feat(reoptimize): add ExceptionReoptimizationSubscriber
   → Auto-trigger tras excepcion si autoReoptimize=true
```

### F3.2 — Clustering Zonas (4 commits)

```
1. feat(clustering): create ShipmentClusteringService with k-means
   → Algoritmo k-means simplificado en PHP

2. feat(clustering): add cluster endpoint to RoutePlannerController
   → POST /admin/route-planner/cluster

3. feat(clustering): add zone grouping UI to planner step 1
   → Boton "Agrupar por zona", markers coloreados

4. feat(clustering): pass cluster hints to RouteBuilder
   → Mejorar asignacion vehiculo-zona con hints
```

### F3.3 — Asignacion Inteligente (4 commits)

```
1. feat(driver-scoring): create DriverScoringService
   → Scoring multi-criterio: zona, rating, carga, skills

2. feat(driver-scoring): add suggest-drivers endpoint
   → GET /admin/route-planner/suggest-drivers

3. feat(driver-scoring): add driver suggestion UI to planner step 3
   → Dropdown con scores, badges justificacion

4. feat(driver-scoring): integrate scoring with route assignment
   → Pre-seleccionar mejor conductor por defecto
```

### F4.1 — Mapa Calor Excepciones (4 commits)

```
1. feat(heatmap): create ExceptionMapController with data endpoint
   → GET /admin/reports/exception-map + /data (JSON)

2. feat(heatmap): create heatmap template with Leaflet + leaflet-heat
   → Mapa con heatmap layer, filtros

3. feat(heatmap): add click-to-detail and top-10 panel
   → Popup detalle + panel lateral

4. feat(heatmap): add sidebar link for exception map
   → Link en seccion Reports
```

### F4.2 — Plan vs Real (5 commits)

```
1. feat(route-analysis): create RouteComparisonService
   → compare() — polylines, metricas desviacion

2. feat(route-analysis): create RouteAnalysisController
   → GET /admin/routes/{publicId}/analysis

3. feat(route-analysis): create analysis template with dual polylines
   → Leaflet: azul=planificada, roja=real

4. feat(route-analysis): add metrics panel with PostRouteAnalyzer data
   → Panel lateral: desviacion + ai_analysis

5. feat(route-analysis): add analysis button to completed routes list
   → Boton en lista de rutas status DONE
```

### F4.3 — Reprogramacion (4 commits)

```
1. feat(reschedule): add RESCHEDULE_REQUESTED to ShipmentEventType
   → Nuevo valor en enum (si no existe)

2. feat(reschedule): add reschedule endpoints to PublicTrackingController
   → GET + POST /track/{token}/reschedule

3. feat(reschedule): create reschedule template with slot cards
   → Radio buttons de slots, opcion porteria/vecino

4. feat(reschedule): create RescheduleConfirmationTemplate for SMS
   → Template SMS confirmacion
```

### F5.1 — Disponibilidad Conductores (5 commits)

```
1. feat(availability): create DriverAvailability entity and migration
   → Entity con PublicIdTrait + tabla

2. feat(availability): create DriverAvailabilityService
   → getAvailableDrivers(), setWeeklySchedule()

3. feat(availability): create admin controller and template
   → CRUD + grid semanal con toggles

4. feat(availability): integrate with route planner
   → Filtrar conductores no disponibles en planificador

5. feat(availability): integrate with DriverScoringService
   → Penalizar conductores no disponibles en scoring
```

### F5.2 — Exportacion Contable (3 commits)

```
1. feat(accounting): create AccountingExportService
   → exportCsv(), exportSummary()

2. feat(accounting): create AccountingExportController
   → GET /admin/billing/export con StreamedResponse

3. feat(accounting): add export button to billing template
   → Boton "Exportar CSV" en billing/index.html.twig
```

---

## Orden de Ejecucion con Worktrees

### Ola 1 — Features independientes (en paralelo)

Todas parten de la branch principal. Se pueden trabajar en paralelo con Agent worktrees.

```
┌─────────────────────────────────────────────────────────────┐
│  PARALELO (sin dependencias):                               │
│                                                             │
│  wt-F1.1  Planificador Rutas      (8 commits)              │
│  wt-F1.2  Navegacion Conductor    (2 commits)              │
│  wt-F1.3  Rating Entrega          (4 commits)              │
│  wt-F1.4  Checklist Pre-Ruta      (5 commits)              │
│  wt-F2.2  Dashboard SLA           (5 commits)              │
│  wt-F4.1  Mapa Calor              (4 commits)              │
│  wt-F4.2  Plan vs Real            (5 commits)              │
│  wt-F5.1  Disponibilidad          (5 commits)              │
│  wt-F5.2  Export Contable          (3 commits)              │
│                                                             │
│  Total: 9 worktrees, 41 commits                            │
└─────────────────────────────────────────────────────────────┘
                           │
                     merge a main
                           │
                           ▼
```

### Ola 2 — Features con dependencias de Ola 1

Requieren que Ola 1 esté mergeada primero.

```
┌─────────────────────────────────────────────────────────────┐
│  PARALELO (dependen de Ola 1):                              │
│                                                             │
│  wt-F2.1  Notificacion ETA        (7 commits) ← F1.3      │
│  wt-F2.3  API Publica             (10 commits) ← ninguna  │
│  wt-F3.1  Reoptimizacion          (4 commits) ← F1.1      │
│  wt-F3.2  Clustering              (4 commits) ← F1.1      │
│  wt-F3.3  Asignacion Inteligente  (4 commits) ← F1.1      │
│                                                             │
│  Total: 5 worktrees, 29 commits                            │
└─────────────────────────────────────────────────────────────┘
                           │
                     merge a main
                           │
                           ▼
```

### Ola 3 — Features con dependencias de Ola 2

```
┌─────────────────────────────────────────────────────────────┐
│  wt-F4.3  Reprogramacion          (4 commits) ← F2.1      │
│                                                             │
│  Total: 1 worktree, 4 commits                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Proceso de Ejecucion por Worktree

Para cada feature, el agente ejecutará:

```bash
# 1. Crear worktree desde la branch principal
git worktree add ../wt-F{x}.{y} -b feature/F{x}.{y}-{slug} claude/plan-product-improvements-8AeLX

# 2. Trabajar en el worktree con commits atómicos
cd ../wt-F{x}.{y}/backend
# ... implementar paso a paso, 1 commit por paso ...

# 3. Verificar
make lint
# (en el futuro: php bin/console doctrine:schema:validate)

# 4. Push
git push -u origin feature/F{x}.{y}-{slug}

# 5. Limpiar worktree
cd /home/user/mxo-track
git worktree remove ../wt-F{x}.{y}
```

## Resumen

| Metrica | Valor |
|---|---|
| Total features | 15 |
| Total commits atomicos | 74 |
| Olas de ejecucion | 3 |
| Max worktrees paralelos | 9 (Ola 1) |
| Features sin dependencias | 9 |
| Features con dependencias | 6 |

## Verificacion Global

1. **Por commit**: `make lint` sin errores
2. **Por feature**: flujo funcional end-to-end
3. **Por ola**: merge sin conflictos a branch principal
4. **Final**: test integracion completo CSV → planificador → ruta → conductor → entrega → notificacion → rating
