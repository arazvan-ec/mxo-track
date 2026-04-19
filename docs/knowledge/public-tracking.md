# Public Tracking & Recipient Experience

**Última actualización:** 2026-04-19
**Estado:** Vigente

**Consultar cuando:** se toca el flujo público (sin login) de seguimiento — la página
`/track/{token}`, la reprogramación por parte del destinatario, el rating de entrega, o
cualquier acción que registra `RecipientAction`. Cross-ref: notificaciones salientes al
destinatario → `notifications.md`.

## Scope

El "Public Tracking" es la experiencia del destinatario (no autenticado) que recibe un
SMS/WhatsApp con un link tokenizado y llega a la web. Es el único flujo de la aplicación
que corre bajo `PUBLIC_ACCESS` (ver `security.yaml`), por lo que **la autorización se
apoya enteramente en el token**, no en sesión.

El flujo de negocio cubre 3 capacidades independientes sobre el mismo token:

1. **Tracking** — ver estado del envío, timeline de eventos, posición aproximada del
   vehículo, ETA.
2. **Reschedule** — proponer/seleccionar una franja horaria alternativa, o indicar
   "dejar en portería / dejar con vecino".
3. **Rate** — valorar la entrega (1-5 estrellas + comentario + tags) una vez entregado.

## Recipient Journey

```
Evento dispara notificación (backend/src/Notification/*Notification.php)
   └─ RecipientNotificationService → SendRecipientNotificationMessage (async)
        └─ Messenger worker envía SMS con URL: {APP_BASE_URL}/track/{trackingToken}
             └─ Destinatario abre link
                  └─ PublicTrackingController::track (GET /track/{token})
                       ├─ Registra RecipientAction(TrackingPageViewed)
                       └─ Renderiza tracking/public.html.twig con timeline + ETA + mapa
                            ├─ [opcional] CTA "Reprogramar" → /track/{token}/reschedule
                            ├─ [opcional] CTA "Valorar" (solo si DELIVERED) → /track/{token}/rate
                            ├─ [opcional] Confirmar presencia → POST /confirm-presence
                            └─ [opcional] Elegir opción alternativa → POST /alternative
```

Las notificaciones salientes se documentan en `notifications.md` (no duplicar aquí).

## URL Token Model — How Access Is Authorized Without Login

El `trackingToken` vive en `Shipment::$trackingToken` (nullable string). Se genera en el
constructor del Shipment (`Shipment::generateTrackingToken()`) con formato fijo:

```
TRK-XXXX-YYYY          (donde XXXX y YYYY son 4 hex chars upper-case cada uno)
```

- **Entropy:** 6 bytes aleatorios (`random_bytes(6)`) → 48 bits ≈ 2.8×10¹⁴ valores.
  Suficiente contra enumeración casual; no reemplaza rate-limiting.
- **Formato estricto:** `PublicTrackingService::trackByToken()` valida con regex
  `/^TRK-[A-Z0-9]{4}-[A-Z0-9]{4}$/` **antes** de tocar la base de datos — request con
  formato inválido devuelve `null` sin query.
- **Tenant filter bypass:** el servicio deshabilita explícitamente el filtro Doctrine
  `customer_tenant` (no hay sesión → no hay tenant). El token es el único control de
  acceso. Sin sesión, el filtro multi-tenant se saltaría silenciosamente dando 404 a
  todo; por eso hay que desactivarlo.
- **Sin expiración:** el token no expira; acompaña al Shipment de por vida. Es un
  identificador estable (no un nonce).
- **No exposición del `id`:** el token es independiente del `public_id` ULID. No cruzan
  información — el destinatario nunca ve el ULID.

### Por qué token-por-envío, no JWT

Un JWT con expiración forzaría re-emitir URLs cada vez que un SMS llegara tarde. El
token estable permite que el link en un SMS viejo siga funcionando, que es el
comportamiento esperado por el usuario (destinatarios abren links días después).

## RecipientAction Types — What a Recipient Can Do

Entity: `backend/src/Entity/RecipientAction.php` — audit log inmutable de interacciones
del destinatario. Un ShipmentID → N RecipientActions.

Enum: `backend/src/Enum/RecipientActionType.php`

| ActionType | Trigger | Payload |
|---|---|---|
| `TrackingPageViewed` | GET `/track/{token}` | `{}` (auto en cada render de la página) |
| `PresenceConfirmed` | POST `/track/{token}/confirm-presence` con `confirmed=true` | `{confirmed: true}` |
| `PresenceDenied` | POST `/track/{token}/confirm-presence` con `confirmed=false` | `{confirmed: false}` |
| `AlternativeRequested` | POST `/track/{token}/alternative` | `{option, instructions}` |
| `RescheduleRequested` | *(POST reschedule crea `ShipmentEvent::RESCHEDULE_REQUESTED` — no un RecipientAction directamente; ver sección Reschedule)* | — |
| `RatingSubmitted` | *(POST rate crea `DeliveryRating` — también registrado como RecipientAction opcional)* | — |

**Nota:** Rating y Reschedule escriben sus propias entities (DeliveryRating, DeliverySlot)
y/o eventos (`ShipmentEvent`). `RecipientAction` cubre las acciones "ligeras" sin
entidad dedicada. Los enum cases `RescheduleRequested` y `RatingSubmitted` existen pero
los endpoints actuales no los crean (los eventos/entities dedicados son la fuente de verdad).

## Controller Endpoints

Todos bajo prefijo `/track/{trackingToken}` y `PUBLIC_ACCESS`.

| Method | Path | Route name | Renders / Returns |
|---|---|---|---|
| GET | `/track/{trackingToken}` | `public_tracking` | `tracking/public.html.twig` — timeline, ETA, posición |
| GET | `/track/{trackingToken}/rate` | `public_tracking_rate_page` | `tracking/rate.html.twig` — formulario de rating (solo si latestEvent == DELIVERED) |
| POST | `/track/{trackingToken}/rate` | `public_tracking_rate` | `JsonResponse` — crea `DeliveryRating` (score 1-5, comment ≤500, tags ≤5) |
| GET | `/track/{trackingToken}/reschedule` | `public_tracking_reschedule` | `tracking/reschedule.html.twig` — lista `DeliverySlot`s propuestos |
| POST | `/track/{trackingToken}/reschedule` | `public_tracking_reschedule_submit` | Redirect + flash — crea `ShipmentEvent::RESCHEDULE_REQUESTED` y despacha SMS de confirmación |
| POST | `/track/{trackingToken}/confirm-presence` | `public_tracking_confirm_presence` | `JsonResponse` — crea `RecipientAction(PresenceConfirmed/Denied)` |
| POST | `/track/{trackingToken}/alternative` | `public_tracking_alternative` | `JsonResponse` — crea `RecipientAction(AlternativeRequested)` |

**Guard pattern repetido en cada endpoint:** `trackByToken()` → si null → 404. El rating
además exige `latestEvent->type === DELIVERED` (HTTP 400 si no).

**Ausencia de CSRF:** estos endpoints no validan CSRF porque el token de tracking ES el
credencial. CSRF exige sesión — estas rutas no la tienen.

## Entities Referenced

| Entity | File | Purpose |
|---|---|---|
| `RecipientAction` | `backend/src/Entity/RecipientAction.php` | Audit log inmutable de interacciones (ver, confirmar, pedir alternativa) |
| `RecipientNotification` | `backend/src/Entity/RecipientNotification.php` | Registro de SMS/WhatsApp enviado al destinatario (channel, template, status, sentAt, errorMessage). Rellenado por `SendRecipientNotificationHandler` |
| `DeliveryRating` | `backend/src/Entity/DeliveryRating.php` | Rating 1-5 + comment + tags + phone. `uniq_delivery_rating_shipment` → un rating por envío (servicio lanza `LogicException` → 409 Conflict) |
| `DeliverySlot` | `backend/src/Entity/DeliverySlot.php` | Franjas propuestas/seleccionadas/confirmadas para reschedule (statuses: `proposed` / `selected` / `confirmed` / `expired`) |
| `ShipmentEvent` | `backend/src/Domain/Shipment/Model/ShipmentEvent.php` | Timeline de estados (source of truth para `DELIVERED`, `RESCHEDULE_REQUESTED`, etc.) |

## Templates

Ubicación: `backend/templates/tracking/`. No extienden `base.html.twig` — son **standalone
HTML pages** (con `<!doctype html>` propio y Tailwind desde CDN). Razón: el destinatario
no tiene sesión, idioma, topbar ni navigation sidebar — la experiencia es intencionalmente
minimal y móvil-first (`max-w-2xl`).

| Template | Used by | Notas |
|---|---|---|
| `public.html.twig` | `track` (185 líneas) | Header + status badge + timeline de eventos + (opcional) mapa aproximado con posición vehículo + ETA. Badges con clases `badge-green/blue/red` |
| `rate.html.twig` | `ratePage` (250 líneas) | Alpine.js para UI de estrellas + textarea + tag chips. Submit vía fetch POST → JSON |
| `reschedule.html.twig` | `reschedule` (150 líneas) | Radio buttons de slots propuestos + opciones alternativas (portería/vecino). Submit form clásico POST |

**CSS vars:** usan `--color-surface`, `--color-text-primary`, `--color-border` de
`index.css` — por eso incluyen `<link rel="stylesheet" href="/app/assets/index.css">`
aunque no extiendan base.

## Reschedule Flow Details

`DeliverySlotService::getAvailableSlots()` busca slots existentes con status `proposed`.
Si hay cero, `PublicTrackingController::reschedule` llama a `proposeSlots()` con
`buildDefaultTimeWindows()` — genera 6 slots hardcoded: mañana AM/PM, pasado AM/PM,
próxima semana AM/PM (09:00-13:00 / 14:00-19:00).

Al seleccionar slot (POST):
1. Lookup por `publicId` + `shipment` + `status=proposed`. Si no match → flash error.
2. `DeliverySlotService::selectSlot()` → expira otros proposed del mismo shipment,
   marca el elegido como `selected`, guarda `recipientPhone`.
3. Crea `ShipmentEvent(RESCHEDULE_REQUESTED, payload={slot_public_id, slot_date, slot_time_range})`.
4. Despacha `SendRecipientNotificationMessage(stop, 'rescheduled', customerId, {slot_date, slot_time_range})` — SMS de confirmación.

Si el destinatario eligió "alternative_option" (portería/vecino) en lugar de un slot,
el controller escribe `Shipment::setNotes()` con el texto legible ("Dejar en porteria"
/ "Dejar con vecino") — afecta la próxima tentativa de entrega.

## Key Files Reference

| File | Role |
|---|---|
| `backend/src/Controller/PublicTrackingController.php` | 7 endpoints del flujo público |
| `backend/src/Application/Tracking/PublicTrackingService.php` | `trackByToken()` — valida formato, deshabilita tenant filter, carga timeline+ETA |
| `backend/src/Application/Tracking/TrackingInfo.php` | DTO readonly con shipment + events + latestEvent + approximatePosition + ETA |
| `backend/src/Notification/DeliveryRatingService.php` | `submitRating()` + `getRatingForShipment()` + `getAverageRatingForDriver()` |
| `backend/src/Notification/DeliverySlotService.php` | `proposeSlots()` / `selectSlot()` / `confirmSlot()` / `expireOtherSlots()` |
| `backend/src/Notification/Message/SendRecipientNotificationHandler.php` | Construye URLs `/track/{token}` y `/track/{token}/rate` para SMS |
| `backend/src/Entity/RecipientAction.php` | Audit log |
| `backend/src/Entity/RecipientNotification.php` | Registro de envío de SMS/WhatsApp |
| `backend/src/Entity/DeliveryRating.php` | Rating (1 por shipment) |
| `backend/src/Entity/DeliverySlot.php` | Franjas propuestas (status machine) |
| `backend/src/Enum/RecipientActionType.php` | 6 tipos de acción |
| `backend/templates/tracking/public.html.twig` | Página de seguimiento |
| `backend/templates/tracking/rate.html.twig` | Formulario de rating |
| `backend/templates/tracking/reschedule.html.twig` | Formulario de reschedule |
| `backend/config/packages/security.yaml` (L49) | `- { path: ^/track/, roles: PUBLIC_ACCESS }` |

## Gotchas

- **Sin expiración del token:** el link sirve indefinidamente. No hay "token rotation".
  Si un shipment se considera sensible tras cierto tiempo, habría que añadir lógica
  explícita (no existe hoy).
- **Sin rate-limiting a nivel controller:** ningún endpoint tiene `RateLimiter` attachado.
  La validación de formato regex mitiga enumeración, pero un atacante con un token
  válido puede spammear POST /alternative indefinidamente. Mitigación parcial: cada
  acción crea un `RecipientAction` persistente → observable, no bloqueado.
- **Multilingüe:** los templates están hardcoded en español (`lang="es"`, textos
  inline, sin `{{ 'key'|trans }}`). No usan el sistema de i18n. Cambiar idioma requiere
  duplicar templates o refactor.
- **Tenant filter manualmente deshabilitado:** `PublicTrackingService` llama
  `$filters->disable('customer_tenant')`. No hace re-enable al final — si el servicio
  se invocara desde un contexto autenticado (p.ej. admin viendo la vista del
  destinatario), el filter quedaría off para el resto del request. Hoy no se da el caso
  porque solo lo usa `PublicTrackingController` (stateless, un request por instancia de
  kernel).
- **CSRF ausente:** las rutas POST no tienen CSRF token — el `trackingToken` es el
  credencial. Un atacante que obtenga el token puede forzar acciones; está asumido.
- **`DeliveryRating` unique constraint:** el índice `uniq_delivery_rating_shipment` hace
  que `submitRating()` sobre un shipment ya valorado lance `LogicException` → el
  controller lo mapea a HTTP 409. No hay endpoint para editar el rating.
- **Default slots hardcoded:** los 6 slots (mañana/pasado/próxima semana × AM/PM) viven
  en `PublicTrackingController::buildDefaultTimeWindows()`. Si el negocio quiere slots
  configurables por customer, hay que mover la lógica al `DeliverySlotService` o a
  configuración por tenant.
- **`RecipientActionType::RescheduleRequested` y `RatingSubmitted` están definidos pero
  nunca se crean desde el controller** — la source of truth es `ShipmentEvent` /
  `DeliveryRating`. El enum está sobre-especificado respecto al uso real.
