# Review Socratico: 15 Mejoras de Producto mxo-track

> Fecha: 2026-03-09
> Plan seguido: `PLAN_EJECUCION_WORKTREES.md` (74 commits atomicos, 15 features, 3 olas)
> Branch: `claude/plan-product-improvements-8AeLX`

---

## 1. Plan Seguido

Se siguio el plan definido en `docs/plans/improve-software/PLAN_EJECUCION_WORKTREES.md`, que a su vez implementa las mejoras de `MEJORAS_PRODUCTO.md`. El plan original proponia **18 features** (6 categorias), de las cuales se seleccionaron **15 para implementacion** y se descartaron 3:

| Descartada | Razon |
|---|---|
| 2.4 Turnos y Jornada | Baja prioridad, parcialmente cubierta por F5.1 Disponibilidad |
| 4.2 Integracion eCommerce | Requiere API publica estable primero (F2.3), scope excesivo |
| 5.2 Chat Conductor-Operador | Complejidad media-alta, requiere diseño UX dedicado |
| 6.1 Prediccion Volumen | ML existente ya cubre parte, requiere datos historicos reales |
| 6.3 Fraude POD | EXIF GPS requiere libreria externa + cambios en Driver API |

---

## 2. Estado de Implementacion: 15/15 features

Todas las features tienen sus commits. La tabla resume la calidad real:

| # | Feature | Commits | Plan? | Score | Issues Encontrados |
|---|---------|---------|-------|-------|-------------------|
| F1.1 | Planificador Rutas | 7 | SI | 8/10 | Sin sidebar link en commit (existia previamente), no valida rol DRIVER |
| F1.2 | Navegacion Conductor | 2 | SI | 9/10 | Sin validacion bounds lat/lng |
| F1.3 | Rating Entrega | 4 | SI | **4/10** | **BUG: Endpoint POST /rate no existe en controller** |
| F1.4 | Checklist Pre-Ruta | 5 | SI | 9/10 | Correcto, bloquea inicio ruta |
| F2.1 | Notificacion ETA | 7 | SI | 8/10 | 3 triggers OK, falta trigger approaching integrado con stream |
| F2.2 | Dashboard SLA | 5 | SI | 9/10 | SQL correcto, division-by-zero protegida |
| F2.3 | API Publica | 10 | SI | 7/10 | Sin anotaciones OpenAPI (Swagger vacio), sin DTOs request/response |
| F3.1 | Reoptimizacion | 4 | SI | 8/10 | Mercure publish OK con try-catch, sin bounds check coords |
| F3.2 | Clustering Zonas | 4 | SI | 9/10 | K-means++ correcto, convergencia y empty clusters manejados |
| F3.3 | Driver Scoring | 4 | SI | 7/10 | **BUG: No usa DriverAvailabilityService** |
| F4.1 | Mapa Calor | 4 | SI | 9/10 | N+1 queries en top-10 |
| F4.2 | Plan vs Real | 5 | SI | 8/10 | Edge case sin stops/posiciones |
| F4.3 | Reprogramacion | 4 | SI | 7/10 | **BUG: No envia SMS**, opciones alternativas sin downstream |
| F5.1 | Disponibilidad | 5 | SI | 8/10 | Sin timezone, sin validacion startTime < endTime |
| F5.2 | Export Contable | 3 | SI | 6/10 | **BUG: Sin customer scope**, sin validacion fechas |

**Score medio: 7.7/10**

---

## 3. Bugs Criticos (requieren fix inmediato)

### BUG-1: F1.3 Rating — Endpoint POST /rate no existe

- **Archivo afectado**: `src/Controller/PublicTrackingController.php`
- **Problema**: `DeliveryRatingService::submitRating()` existe en `src/Notification/DeliveryRatingService.php`. El template `templates/tracking/rate.html.twig` existe con formulario de estrellas. Pero **no hay endpoint POST** en `PublicTrackingController` que reciba el formulario y llame al servicio. El formulario envia a una ruta que no existe → 404.
- **Impacto**: Feature de rating completamente rota para el destinatario final.
- **Fix**: Agregar metodo `rate()` en `PublicTrackingController` con `#[Route('/track/{trackingToken}/rate', methods: ['POST'])]`. Debe validar: (1) shipment DELIVERED, (2) no hay rating previo, (3) score 1-5. Llamar a `DeliveryRatingService::submitRating()`.

### BUG-2: F4.3 Reschedule — No envia notificacion SMS tras reprogramar

- **Archivo**: `src/Controller/PublicTrackingController.php`
- **Problema**: `RescheduleConfirmationTemplate` fue creada en `src/Notification/Template/RescheduleConfirmationTemplate.php` pero **nunca se instancia ni envia**. Tras seleccionar slot, se crea `ShipmentEvent(RESCHEDULE_REQUESTED)` pero no se llama a `RecipientNotificationService`.
- **Impacto**: El destinatario no recibe confirmacion de su reprogramacion.
- **Fix**: Inyectar `RecipientNotificationService` en el controller. Tras crear el evento, instanciar `RescheduleConfirmationTemplate` y llamar a `sendAndRecord()`.

### BUG-3: F5.2 Accounting Export — Sin validacion customer scope

- **Archivo**: `src/Controller/Admin/AccountingExportController.php`
- **Problema**: Un operador del Customer A podria exportar datos del Customer B manipulando el publicId en la URL.
- **Impacto**: Fuga de datos entre tenants.
- **Fix**: Verificar que `$customer->getId() === $user->getCustomer()->getId()` o que el usuario es ADMIN.

### BUG-4: F3.3 Driver Scoring — No integra disponibilidad

- **Archivo**: `src/Service/DriverScoringService.php`
- **Problema**: `DriverAvailabilityService::getAvailabilityScore()` existe y retorna un score normalizado, pero `scoreDriversForRoute()` nunca lo invoca. Conductores no disponibles aparecen con score completo.
- **Impacto**: El planificador sugiere conductores que no estan disponibles ese dia.
- **Fix**: Inyectar `DriverAvailabilityService`, agregar dimension "disponibilidad" con peso 20% al scoring compuesto.

---

## 4. Issues Medios

### MED-1: F5.2 Export — Parseo de fechas sin validacion
- Fechas invalidas en query params silenciosamente usan epoch.
- **Fix**: try/catch en `new DateTimeImmutable()` + respuesta 400.

### MED-2: F4.1 Heatmap — N+1 queries en topAddresses
- Cada direccion del top-10 ejecuta query adicional para desglose por tipo.
- **Fix**: Single query con `GROUP BY address, exception_type` y agrupar en PHP.

### MED-3: F4.3 Reschedule — Opciones alternativas sin efecto downstream
- "Dejar en porteria" / "Dejar con vecino" se guardan en payload del evento pero nada las procesa. El conductor no las ve.
- **Fix**: Crear handler que actualice `Shipment.deliveryInstructions` y notifique al conductor via Mercure.

### MED-4: F5.1 Disponibilidad — Sin timezone ni validacion horaria
- `startTime`/`endTime` como strings sin timezone.
- Un slot puede tener `startTime > endTime` (turno nocturno no soportado).
- **Fix**: Validar coherencia horaria en setter/DTO.

### MED-5: F2.3 API — Swagger UI vacio
- NelmioApiDoc instalado pero controllers API sin anotaciones OpenAPI (`#[OA\Tag]`, `#[OA\Response]`, etc.).
- Swagger UI en `/api/v1/doc` mostrara endpoints sin documentacion util.
- **Fix**: Agregar anotaciones OA a los 3 controllers API.

### MED-6: F2.1 Notifications — Trigger "approaching" no integrado con stream
- `notifyApproaching()` existe en `RecipientNotificationService` pero nada lo llama automaticamente.
- El plan decia "cuando el vehiculo esta a N paradas del destino".
- **Fix**: En `TraccarStreamCommand`, tras procesar posicion, verificar distancia a siguiente stop y triggear.

---

## 5. Mejoras Propuestas sobre Features Existentes

### MEJORA-1: F2.3 API — DTOs de request/response tipados
- Controllers API parsean JSON raw sin DTOs ni validacion Symfony.
- Crear `CreateShipmentRequest`, `ShipmentResponse` DTOs con Symfony Validator.
- Mejora consistencia, validacion automatica, y documentacion OpenAPI.

### MEJORA-2: F1.1 Route Planner — CSV import desde modal
- El plan original mencionaba reutilizar `ShipmentCsvImporter` desde el planificador.
- Actualmente el planificador solo lista envios existentes.
- Agregar modal de importacion CSV que llame al importer existente.

### MEJORA-3: F3.1 Reoptimize — Validar coordenadas del conductor
- `currentLat`/`currentLng` se parsean como floats sin bounds check.
- Coordenadas invalidas (999.0) podrian causar errores en VROOM.
- Agregar validacion: lat [-90, 90], lng [-180, 180].

### MEJORA-4: F2.1 Notifications — Validacion formato telefono
- Numeros de telefono se usan tal cual sin validar formato E.164.
- Un telefono invalido causaria error en Twilio pero NullSmsProvider lo ignora.
- Agregar validacion regex `/^\+[1-9]\d{1,14}$/` antes de enviar.

### MEJORA-5: F1.4 Inspection — Verificar allItemsChecked()
- El bloqueo de inicio de ruta depende de `VehicleInspection::allItemsChecked()`.
- Verificar que el metodo realmente comprueba todos los items marcados como "checked".

---

## 6. Nuevas Features Propuestas (Fase 6)

### F6.1 — Dashboard Operador en Tiempo Real (Prioridad: ALTA)
- **Que**: Vista consolidada de todas las rutas ACTIVE con estado actual de cada conductor, progreso (X/Y entregas), siguiente parada, y alertas automaticas (retraso, desvio, inactividad).
- **Construye sobre**: Mercure topics `/operator/fleet`, `VehicleLastPosition`, `EtaService`, `RouteStop` status counters.
- **Estimacion**: 6 commits (controller, template, Mercure listener JS, alertas, sidebar link, tests).

### F6.2 — Notificaciones Push al Conductor (Prioridad: MEDIA)
- **Que**: Firebase Cloud Messaging o WebPush para notificar cambios al conductor cuando no tiene la app activa (reoptimizacion, nuevo pedido, cambio horario).
- **Construye sobre**: `RecipientNotificationService` pattern, Mercure (fallback).
- **Estimacion**: 5 commits (service worker, FCM provider, service, config, integracion con reoptimize).

### F6.3 — Historico de Rendimiento por Zona (Prioridad: MEDIA)
- **Que**: Tendencias semanales/mensuales de tasa de exito, tiempo medio, excepciones por zona geografica. Identifica zonas que mejoran/empeoran.
- **Construye sobre**: `SlaMetricsService` + `DeliveryZoneService` + `ExceptionPatternService`.
- **Estimacion**: 4 commits (service, controller, template Chart.js, sidebar link).

### F6.4 — Plantillas de Ruta Recurrentes (Prioridad: ALTA)
- **Que**: Guardar configuraciones de ruta (vehiculos, zonas, conductores) como plantillas reutilizables. Para clientes con rutas fijas semanales, regenerar con un click.
- **Construye sobre**: `RoutePlannerController`, `RouteBuilder`.
- **Estimacion**: 5 commits (entity RoutePlanTemplate, migration, save/load endpoints, template UI, integracion planificador).

### F6.5 — Multi-idioma Completo i18n (Prioridad: BAJA)
- **Que**: Actualmente mezcla de espanol/ingles en templates. Implementar traducciones Symfony con ICU message format.
- **Construye sobre**: Symfony Translation component (ya configurado).
- **Estimacion**: 3 commits por modulo (extraer strings, crear catalogos es/en, configurar locale switcher).

---

## 7. Plan de Accion Completo (Priorizado)

### Fase A: Bugs criticos (4 fixes)
```
A.1  fix(rating): add POST /track/{token}/rate endpoint to PublicTrackingController
A.2  fix(reschedule): send SMS notification after reschedule selection
A.3  fix(accounting): validate customer scope in AccountingExportController
A.4  fix(driver-scoring): integrate DriverAvailabilityService into scoring
```

### Fase B: Issues medios (6 fixes)
```
B.1  fix(accounting): validate date params with try/catch + 400 response
B.2  fix(heatmap): optimize topAddresses with single GROUP BY query
B.3  fix(reschedule): process alternative options (porteria/vecino) downstream
B.4  fix(availability): add timezone and time range validation
B.5  feat(api): add OpenAPI annotations to API controllers
B.6  feat(notifications): integrate approaching trigger with TraccarStreamCommand
```

### Fase C: Mejoras sobre features existentes (5 mejoras)
```
C.1  feat(api): create request/response DTOs with Symfony Validator
C.2  feat(route-planner): add CSV import modal from ShipmentCsvImporter
C.3  fix(reoptimize): validate lat/lng bounds on reoptimize request
C.4  fix(notifications): add E.164 phone format validation
C.5  fix(inspection): verify allItemsChecked() logic
```

### Fase D: Nuevas features (5 features, ~23 commits)
```
D.1  F6.1: Dashboard operador tiempo real (6 commits)
D.2  F6.4: Plantillas de ruta recurrentes (5 commits)
D.3  F6.3: Historico rendimiento por zona (4 commits)
D.4  F6.2: Notificaciones push al conductor (5 commits)
D.5  F6.5: Multi-idioma i18n (3 commits)
```

### Total estimado: 4 + 6 + 5 + 23 = **38 commits adicionales**

---

## 8. Verificacion por Fase

| Fase | Verificacion |
|------|-------------|
| A | `make lint` + test manual: rating submit, reschedule SMS en log, export scope denied, scoring con availability |
| B | `make lint` + test manual: fechas invalidas → 400, heatmap rapido, Swagger con docs, approaching trigger |
| C | `make lint` + test manual: API DTOs validados, CSV import en planificador, coords bounds |
| D | `make lint` + test manual: dashboard live updates, plantilla save/load, zone trends chart, push notification |
