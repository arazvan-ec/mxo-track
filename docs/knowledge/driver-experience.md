# Driver Experience

**Última actualización:** 2026-04-19
**Estado:** Vigente

**Consultar antes de:** tocar endpoints `/api/driver/*` o `/driver/*`, cambios en la UI
del conductor (`DriverRoutePage`), push notifications para drivers, captura de POD
(Proof-of-Delivery), briefings generados por IA, feedback del conductor o manifiesto
de carga.

Cross-references:
- Widgets renderizados en `DriverRoutePage` → `widget-system.md`
- POD schema y entidades → `domain-model.md`
- ETAs / Mercure topics → `realtime.md`
- Rutas y paradas (Route, RouteStop) → `route-optimization.md`

---

## Driver Journey (flujo típico)

1. **Login** como `ROLE_DRIVER` → `/driver/routes` (listado Twig con rutas asignadas).
2. **Seleccionar ruta** → redirige a `/app/driver/routes/{publicId}` (SPA React).
3. **Inspección de vehículo** → checklist obligatorio antes de `start`.
4. **Briefing opcional** → resumen IA (paradas, riesgo, duración, notas).
5. **Start route** → `POST /api/driver/routes/{id}/start` (requiere inspección completa).
6. **Por cada parada:** ver stop actual → navegar (Google Maps/Waze) → llegar →
   `deliver` (POD con recipient ID) o `exception` (motivo) → feedback opcional.
7. **Finish route** → `POST /api/driver/routes/{id}/finish`.

---

## API Endpoints — Driver

Todos bajo `#[IsGranted('ROLE_DRIVER')]`. Public IDs = ULID; nunca IDs internos.

### `/api/driver/*` (DriverApiController)

| Método + Path | Propósito | Notas |
|---|---|---|
| `GET /routes` | Listado de rutas del driver autenticado | Filtra por `driver = currentUser` |
| `POST /routes/{id}/start` | Iniciar ruta | 422 si inspección no completada |
| `POST /routes/{id}/finish` | Finalizar ruta | Idempotente sobre status |
| `GET /routes/{id}/stops` | Paradas de la ruta | Incluye `navigationUrl` (Google) y `wazeUrl` |
| `GET /routes/{id}/etas` | ETAs por parada | Delega en `EtaService` |
| `GET /routes/{id}/briefing` | Briefing IA | Ver `DriverBriefingService` |
| `GET /routes/{id}/inspection` | Estado de inspección | Devuelve items default si no existe |
| `POST /routes/{id}/inspection` | Guardar inspección | `completed_at` se setea si todos `checked=true` |
| `POST /stops/{id}/deliver` | POD entrega OK | `DeliveryService::deliverStop` |
| `POST /stops/{id}/exception` | Reportar excepción | `DeliveryService::reportException` |
| `GET /stops/{id}/pod` | Metadata POD | `confirmation_mode: recipient_id_encoded` |
| `GET /stops/{id}/pod/download` | Payload POD | Incluye `recipient_id_encoded` + flag driver |
| `POST /routes/{id}/stops/{id}/feedback` | Feedback del conductor | Lat/Lng corregido, notas acceso |

### `/api/driver/push-subscription` (DriverPushSubscriptionController)

| Método + Path | Propósito |
|---|---|
| `POST /` | Registrar subscription (endpoint + authKey + p256dh). Idempotente |
| `DELETE /` | Desuscribir por endpoint |
| `GET /vapid-key` | Obtener VAPID public key para `pushManager.subscribe()` |

### `/driver/*` (DriverWebController — Twig legacy)

| Método + Path | Propósito |
|---|---|
| `GET /driver/routes` | Listado Twig con contadores (total/delivered/exceptions) |
| `GET /driver/routes/{id}` | Redirect a SPA: `/app/driver/routes/{id}` |

### `/api/routes/{id}/loading-manifest` (LoadingManifestApiController)

**Nota:** es `ROLE_OPERATOR`, no driver — el operador lo imprime/comparte con el
conductor. LIFO: última entrega = primera en cargarse.

---

## Services

| Servicio | Responsabilidad | Dependencias clave |
|---|---|---|
| `DriverBriefingService` | Briefing IA pre-ruta (paradas, riesgo, notas, duración) | `LlmClientInterface`, `AddressRiskService`, `RateLimitedApiClient` |
| `WebPushService` | Envío de push al driver por `User` | `PushSubscription` repo, VAPID keys |
| `LoadingManifestGenerator` | Manifiesto LIFO de carga | Lee `RouteStop` + `Shipment` |
| `DeliveryEvidenceFactory` | Payload POD (hash SHA-256 de recipient ID + fingerprint) | Stateless |
| `DeliveryService` | Facade para `deliverStop` / `reportException` | Ver `api-surface.md` |
| `RouteLifecycleService` | Start/finish de ruta | Ver `route-optimization.md` |
| `EtaService` | ETAs por parada | Ver `realtime.md` |

---

## Push Notification Architecture

**Flow:**
```
Browser (driver) → POST /api/driver/push-subscription { endpoint, auth_key, p256dh_key }
                 → PushSubscription persisted (user + endpoint unique)
Event/listener   → WebPushService::sendToDriver(User, title, body, data)
                 → Reads PushSubscription[] by user → logs payload
                   (prod: minishlink/web-push envía al endpoint)
```

**Entidades:**
- `PushSubscription` (en `src/Entity/`) — user + endpoint + authKey + p256dh.
- VAPID keys inyectadas al service vía constructor (`%env(VAPID_PUBLIC_KEY)%`).

**Estado actual:** `WebPushService` **logs the payload but does not actually POST to
the endpoint**. La integración con `minishlink/web-push` está pendiente — ver comentario
en la línea 48 de `WebPushService.php`.

---

## POD Capture Flow

1. **Driver llama** `POST /api/driver/stops/{id}/deliver` con `DeliverStopInput`
   (incluye `recipientIdEncoded`, `clientActionId` UUID idempotente, `confirmedByDriver`).
2. **DeliveryService** valida ownership + confirmación → construye evidencia.
3. **DeliveryEvidenceFactory::build()** produce array con:
   - `recipient_id_sha256` (hash del ID del receptor, no se almacena en claro)
   - `action_fingerprint` (SHA-256 de `routeStopId|clientActionId|driverUserId|bucket`)
   - `fingerprint_bucket` (minuto UTC — ventana de idempotencia)
   - `driver_ip`, `driver_user_agent`, `confirmed_at`
4. **POD persistido** con `confirmation_mode: recipient_id_encoded`.
5. **Idempotencia:** `DriverAction` (tabla `driver_action`) tiene unique constraint
   `(driver_user_id, client_action_id)`. Reintentos con mismo `clientActionId`
   devuelven 200 en vez de 201.

**Download:** `GET /stops/{id}/pod/download` devuelve `recipient_id_encoded` +
`confirmed_by_driver` (el encoded crudo — el SHA-256 queda en POD).

---

## DriverRoutePage (Frontend SPA)

Ruta: `/app/driver/routes/{publicId}` — `frontend/src/pages/driver/DriverRoutePage.tsx`.

**Comportamiento clave:**
- `useRouteMapData(publicId)` — carga ruta + stops + vehiclePosition vía SSE Mercure.
- **Current stop** = primer stop con `status === 'PENDING'` && `!isOrigin`.
- Auto-selecciona current stop en primer load.
- Auto-tracking del vehículo: cuando llega posición nueva, `flyTo(lng, lat, 14)`.
- `BottomSheet` + `WidgetRenderer` para detalle de stop (ver `widget-system.md`).
- `usePageLayout('driver_route')` define qué widgets se muestran.
- Componentes compartidos: `MapCanvas`, `RouteSummaryBar`, `StopMarkersLayer`,
  `RoutePolylineLayer`, `VehicleLayer`.

**Sin métricas ni comparación** — el driver solo ve info operativa de entrega
(filosofía UX: reducir fricción).

---

## Key Files Reference

**Backend — Controllers:**
- `backend/src/Controller/DriverApiController.php` — 13 endpoints, ~525 líneas
- `backend/src/Controller/DriverWebController.php` — listado Twig + redirect SPA
- `backend/src/Controller/DriverPushSubscriptionController.php` — subscribe/unsubscribe/vapid
- `backend/src/Controller/LoadingManifestApiController.php` — manifiesto LIFO (operator)

**Backend — Services:**
- `backend/src/Service/DriverBriefingService.php` — briefing IA con fallback
- `backend/src/Service/WebPushService.php` — push (pendiente wire a minishlink)
- `backend/src/Service/LoadingManifestGenerator.php`
- `backend/src/Service/DeliveryEvidenceFactory.php`

**Backend — Entities (en `src/Entity/`, pragmatic context):**
- `DriverAvailability` — horario semanal (dayOfWeek 0-6, startTime/endTime HH:MM)
- `DriverAction` — log idempotente de acciones con `client_action_id`
- `DriverFeedback` — correcciones del driver (lat/lng corregido, notas de acceso,
  tiempo real de servicio, comentarios)
- `PushSubscription` — endpoint Web Push por user
- `VehicleInspection` — checklist pre-start (entidad en `src/Entity/`)

**Frontend:**
- `frontend/src/pages/driver/DriverRoutePage.tsx` — página principal SPA
- `backend/templates/driver/routes/index.html.twig` — listado Twig

**DTOs:**
- `App\Dto\Driver\DeliverStopInput`, `ExceptionStopInput`, `StopFeedbackInput`,
  `VehicleInspectionInput`
- `App\Dto\DriverBriefing`
- `App\Dto\LoadingManifestItem`

---

## Gotchas

- **Inspección obligatoria:** `POST /routes/{id}/start` devuelve 422
  (`inspection_not_completed`) si no se completó antes. La UI debe bloquear el botón
  Start hasta que `GET /inspection` devuelva `completed_at !== null`.
- **Default inspection items:** si no hay inspección, `GET /inspection` devuelve 5 items
  hardcoded (`DEFAULT_INSPECTION_ITEMS`). NO crear la inspección server-side — espera al
  primer POST del driver.
- **404 enmascara 403:** cuando un driver intenta acceder a ruta/stop de otro driver,
  se devuelve 404 `route_not_found` / `stop_not_found` (no 403). Evita leak de
  existencia de recursos.
- **recipient_id nunca en claro en POD:** solo `recipient_id_sha256` se persiste en el
  POD principal; el `recipient_id_encoded` se guarda separado para download.
- **`clientActionId` obligatorio:** `DeliverStopInput` requiere UUID del cliente. Sin
  él pierdes idempotencia — reintentos crean acciones duplicadas.
- **`fingerprint_bucket` = minuto UTC:** dos requests dentro del mismo minuto con
  mismos datos → mismo fingerprint. Cambio de minuto genera fingerprint distinto.
- **Feedback opcional pero valioso:** `DriverFeedback.correctedLat/correctedLng`
  alimenta mejora de geocoding. No bloquees UX si el driver lo omite.
- **Briefing se degrada graciosamente:** si Claude API falla o rate-limit, cae a
  `buildFallbackSummary` (plantilla estática) — nunca devuelve error al driver.
- **WebPushService NO envía realmente:** actualmente solo loggea. Antes de prometer
  push functional, completar integración con `minishlink/web-push`.
- **Twig → SPA redirect:** `/driver/routes/{id}` redirige a `/app/driver/routes/{id}`.
  No agregar lógica al Twig show — está vacío a propósito.
- **`LoadingManifest` es operator-facing:** aunque es del journey del driver, el
  endpoint requiere `ROLE_OPERATOR`. El driver lo recibe impreso o vía otro canal.
- **LIFO loading order:** `reverse(stops by sequence ASC)` → última parada se carga
  primero (cerca de la puerta del camión).
- **`DriverAvailability` aún no se usa en planning:** existe la entidad + migración,
  pero la asignación de rutas no respeta todavía el horario. Verificar antes de
  depender de este campo.
