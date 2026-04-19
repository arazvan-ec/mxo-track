# Shipment & Delivery Lifecycle

**Última actualización:** 2026-04-19
**Estado:** Vigente
**Consultar cuando:** se trabaje en creación/edición de Shipments, entregas (POD), slots de reparto, rating post-entrega, eventos de ciclo de vida (`ShipmentEvent`), tracking público, o analítica de entregas (zonas, riesgo, post-route).

Para relaciones entidad↔entidad, ver `docs/knowledge/domain-model.md`. Para ciclo de vida de Route (PLANNED → ACTIVE → DONE) y RouteStop, ver `docs/knowledge/route-optimization.md`.

## Lifecycle end-to-end

Un envío atraviesa el sistema en 4 fases principales — **registro**, **planificación**, **ejecución**, **cierre** — con dos dimensiones de estado paralelas: `ShipmentEvent` (append-only, cara al cliente/destinatario) y `Parcel.status` + `RouteStop.status` (operativo).

```
[CREATE]        [PLAN + DISPATCH]       [EXECUTION]               [CLOSE]
   │                  │                     │                        │
Shipment      DeliverySlot (proposed      Route.start()           POD + DeliveryRating
created       → selected → confirmed)     StopDelivered /         PostRouteAnalyzer
ShipmentEvent RoutePlanning assigns       StopExceptionReported   ZonePerformance
CREATED       stop to vehicle             DELIVERED / EXCEPTION
              DeliveryRisk pre-score      ShipmentEvent
```

### Tabla de transiciones — `ShipmentEventType` (append-only, no hay FSM estricto)

| De (último evento) | A (nuevo evento) | Disparador | Fuente en código |
|---|---|---|---|
| — | `CREATED` | Creación de Shipment (API v1, CSV importer) | `ShipmentApiController::create`, `ShipmentCsvImporter` |
| `CREATED` | `PICKED_UP` | Recogida en origen (no implementado auto; manual/webhook) | [PLANNED] |
| `PICKED_UP`/`CREATED` | `IN_HUB` | Llegada a hub (manual/webhook) | [PLANNED] |
| `IN_HUB` | `IN_TRANSIT` | Salida del hub (manual/webhook) | [PLANNED] |
| `IN_TRANSIT`/`IN_HUB` | `OUT_FOR_DELIVERY` | Route.start() — disparo derivado de `RouteStarted` | [PARTIAL] lectura en templates; no hay listener que escriba el evento automáticamente |
| `OUT_FOR_DELIVERY` | `DELIVERED` | Driver POD: `DeliveryService::deliverStop` | `DeliveryService.php:71` |
| `OUT_FOR_DELIVERY` | `EXCEPTION` | Driver excepción: `DeliveryService::reportException` | `DeliveryService.php:128` |
| Cualquiera (antes de DELIVERED) | `RESCHEDULE_REQUESTED` | Destinatario solicita desde tracking público | `PublicTrackingController.php:209` |

Los eventos son **append-only** — no hay transición prohibida a nivel de dominio, pero la UI (tracking público, portal cliente) infiere el "estado actual" del último evento.

### Tabla de transiciones — `ParcelStatus`

| De | A | Disparador | Notas |
|---|---|---|---|
| — | `REGISTERED` | `new Parcel()` | Estado por defecto |
| `REGISTERED` | `IN_WAREHOUSE` | Manual (ingreso a depot) | [PLANNED] auto |
| `IN_WAREHOUSE` | `LOADED` | Carga en vehículo | [PLANNED] auto |
| `LOADED` | `IN_TRANSIT` | Route.start() | [PLANNED] auto |
| `IN_TRANSIT` | `DELIVERED` | POD exitoso | [PLANNED] auto via listener |
| `IN_TRANSIT` | `ABSENT`/`RETURNED`/`DAMAGED`/`LOST` | Excepción o post-ruta | [PLANNED] auto |

`Parcel::transition(ParcelStatus)` es el único punto de mutación (no hay enforcement de grafo; cualquier `ParcelStatus` es aceptable). El acoplamiento automático con `RouteStop`/`Shipment` aún no existe — actualmente se deja al caller.

### Tabla de transiciones — `RouteStopStatus` (cara operativa)

| De | A | Método | Evento de dominio disparado |
|---|---|---|---|
| `PENDING` | `DELIVERED` | `RouteStop::markDelivered()` | `StopDelivered` |
| `PENDING` | `EXCEPTION` | `RouteStop::markException(code, notes)` | `StopExceptionReported` |
| `PENDING` | `SKIPPED` | `RouteStop::markSkipped()` | — (sin evento de dominio) |

## Mapa de entidades (Shipment + Parcel + Pod + soporte)

| Entidad | Ubicación | Persistencia | Campos clave |
|---|---|---|---|
| **Shipment** | `src/Domain/Shipment/Model/Shipment.php` (POPO) | XML mapping externo | `reference`, `customer`, `recipientName/Phone`, `address`, `lat/lng`, `serviceType`, `priority`, `trackingToken`, `preferredWindowStart/End`, `requiredSkills`, `parcels[]` |
| **Parcel** | `src/Domain/Shipment/Model/Parcel.php` (POPO) | XML mapping externo | `sequenceNumber`, `weightKg`, `volumeM3`, `ean`, `status: ParcelStatus` |
| **Pod** | `src/Domain/Shipment/Model/Pod.php` (POPO) | XML mapping externo | `routeStop` (FK), `shipment` (opt), `signedByName`, `recipientIdEncoded`, `confirmedByDriver`, `createdByUser` (driver) |
| **ShipmentEvent** | `src/Domain/Shipment/Model/ShipmentEvent.php` (POPO) | XML mapping externo, append-only | `eventType: ShipmentEventType`, `payload: array`, `createdAt` |
| **DeliverySlot** | `src/Entity/DeliverySlot.php` (ORM) | Attributes | `slotDate`, `slotStart/End`, `status` (proposed/selected/confirmed/expired), `recipientPhone`, `selectedAt` |
| **DeliveryZone** | `src/Entity/DeliveryZone.php` (ORM) | Attributes | `name`, `centerLat/Lng`, `radiusKm`, `deliveryCount`, scoped por `Customer` (nullable → global) |
| **DeliveryRating** | `src/Entity/DeliveryRating.php` (ORM) | Attributes, `OneToOne` Shipment | `score` (1-5), `comment`, `tags: array`, `recipientPhone` |

Relaciones:
- `Shipment 1──* Parcel` (OneToMany, cascade del embedded totals via `Shipment::recalculateTotals()`)
- `Shipment 1──* ShipmentEvent` (append-only log)
- `Shipment 1──* DeliverySlot` (múltiples propuestos, solo uno selected/confirmed)
- `Shipment 1──1 DeliveryRating` (constraint único)
- `RouteStop 1──1 Pod` (opt), `RouteStop *──1 Shipment` (opt — paradas sin shipment existen: origen, pickups)

## Servicios clave

| Servicio | Input → Output | Cuándo se invoca |
|---|---|---|
| **DeliveryService** (`Application/Delivery/`) | `DeliverStopInput` / `ExceptionStopInput` + driver → `DeliveryResult` / `ExceptionResult` | Driver portal (POD, excepción). Facade: idempotencia + POD + ShipmentEvent + audit + domain event dispatch. Depende de `RouteStopRepositoryInterface`, `ShipmentRepositoryInterface`, `DriverActionService`, `DeliveryEvidenceFactory` |
| **DeliveryEvidenceFactory** | `(recipientIdEncoded, stopId, driverId, ip, UA, ...)` → `array` | Cada POD. Construye hash evidencia (SHA-256 fingerprint + bucket temporal de 1 min) que se persiste en audit log |
| **DeliverySlotService** (`Notification/`) | `(Shipment, timeWindows)` → `DeliverySlot[]` + mutadores `selectSlot/confirmSlot` | Propuesta de slots post-creación; destinatario selecciona desde tracking público; confirmación via SMS/WhatsApp |
| **DeliveryZoneService** | `(customerId?, nClusters=5)` → `DeliveryZone[]` | Manual/cron. Llama ML sidecar (`cluster/delivery-zones`, K-means) → borra zonas previas por customer → persiste nuevas |
| **ZonePerformanceService** | `(from?, to?)` → weekly trends por zona | Dashboards admin (`ZonePerformanceController`). SQL crudo con fórmula haversine en `ACOS` para asignar stops→zona |
| **DeliveryRiskService** | `RouteStop` → `{risk_score, risk_level LOW/MED/HIGH, address_risk}` | En dispatch / briefing del conductor. Combina ML (`predict/delivery-risk`) + `AddressRiskService` (boost +0.15 si dirección histórica riesgosa) |
| **DeliveryNoteGenerator** | `Route` o `Shipment` → array con albarán estructurado | Impresión de albarán (PDF/HTML). `ALB-<últimos-8-ULID>-<YYMMDD>` |
| **PostRouteAnalyzer** | `Route` completed → `{summary, planned_vs_actual, insights, recommendations}` | Listener `PostRouteAnalysisListener` al disparar `RouteCompleted`. Llama LLM (fallback a stats si falla) |
| **DeliveryRatingService** | `(Shipment, score 1-5, comment?, tags?, phone?)` → `DeliveryRating` | Tracking público post-entrega. Constraint único por shipment (rechaza segundas valoraciones) |

## Ciclo de DeliverySlot (propose → select → confirm → delivered)

```
[propose] proposeSlots(Shipment, timeWindows[])
   └─ N slots creados en status=proposed
       │
       │ destinatario consulta tracking público, elige uno
       ▼
[select] selectSlot(slot, recipientPhone)
   └─ otros slots proposed del mismo shipment → expired
   └─ slot → status=selected + selectedAt timestamp
       │
       │ (flujo opcional de confirmación SMS/WhatsApp antes de dispatch)
       ▼
[confirm] confirmSlot(slot)
   └─ slot → status=confirmed
       │
       │ (luego dispatch/ruta/entrega usan el slot como ventana preferida)
       ▼
[delivered] (implícito cuando Shipment termina en DELIVERED)
```

Reglas:
- `selectSlot` solo permitido desde `proposed`. Transición ilegal → `LogicException`.
- `confirmSlot` solo permitido desde `selected`. Transición ilegal → `LogicException`.
- `expire()` se llama en batch a todos los `proposed` hermanos al seleccionar uno — no hay currency-control; último select gana.

## Sistemas de riesgo y rating

### Risk scoring (pre-dispatch)
`DeliveryRiskService::predictRisk(RouteStop)` extrae features (`hour_of_day`, `day_of_week`, `has_phone`, `parcel_count`, `weight_kg`, `stop_sequence`) → llama ML sidecar → si la dirección tiene histórico de excepciones (`AddressRiskService::checkAddress`) el score sube +0.15. Buckets:

| Score | Level |
|---|---|
| < 0.2 | LOW |
| 0.2 – 0.5 | MEDIUM |
| > 0.5 | HIGH |

Si el ML sidecar no responde, `model_version='fallback'` y score=0 (address_risk aún aplica). `getRiskScoresForRoute(Route)` devuelve scores indexados por secuencia — usado en briefing al conductor.

### Rating (post-delivery)
`DeliveryRatingService::submitRating(Shipment, score, comment?, tags?, phone?)`:
- Score **1-5** (validado en constructor de `DeliveryRating`; fuera de rango → `InvalidArgumentException`)
- Constraint único `uniq_delivery_rating_shipment` → re-submit lanza `LogicException`
- `getAverageRatingForDriver(driverId)` agrega por JOIN `DeliveryRating` → `Shipment` → `RouteStop` → `Route.driver`

## Flujo de POD — driver side → backend

```
Driver app (React/PWA)                Backend (Symfony)
──────────────────────                ───────────────────

POST /api/driver/stops/{stopPublicId}/deliver
body: {
  clientActionId,              ──▶   DriverApiController::deliverStop
  signedByName,                      → DeliveryService::deliverStop
  recipientIdEncoded,                  1. resolveStopForDriver (ownership check)
  confirmedByDriver: true,             2. DriverActionService::register (idempotency)
  shipmentPublicId?                    3. RouteStop::markDelivered()
}                                      4. new Pod + persist
                                       5. new ShipmentEvent(DELIVERED) + persist
                                       6. AuditLogger::log + DeliveryEvidenceFactory fingerprint
                                       7. em->flush()
                                       8. dispatch StopDelivered domain event
                                  ◀─   → MercureRouteProgressListener (SSE)
                                       → NotifyDeliveryListener (SMS/WhatsApp destinatario)
                                       → AuditDeliveryListener
                                       → RouteSnapshotListener (update cache)
                                       → RouteEventLogListener (append RouteEvent)
```

POST `/stops/{id}/exception` sigue flujo paralelo: `ExceptionStopInput` con `reason: ExceptionCode` → `RouteStop::markException` → `ShipmentEvent(EXCEPTION)` → dispatch `NlpClassificationMessage` async (Messenger) si `comment` no vacío → `StopExceptionReported` event.

**Idempotencia:** `clientActionId` (ULID generado en el driver) registra acción en `DriverAction` — reintentos devuelven `DeliveryResult(idempotent: true)` sin duplicar POD/evento.

**Evidence fingerprint:** `sha256(stopId|clientActionId|driverId|minuteBucket)` — bucket por minuto agrupa re-envíos del mismo clic pero distingue reintentos verdaderos.

## Flujo de Rating collection

```
DELIVERED → SMS/WhatsApp a recipientPhone con link
  https://{host}/tracking/{trackingToken}/rate
    │
    ▼
Tracking público (Twig) muestra formulario (1-5 estrellas + comentario + tags)
    │
    ▼ POST submitRating
DeliveryRatingService::submitRating
  → valida score 1-5
  → comprueba no-duplicado (constraint único)
  → persist DeliveryRating
```

## Archivos clave

| Concern | Ruta |
|---|---|
| Domain Shipment POPOs | `backend/src/Domain/Shipment/Model/{Shipment,Parcel,Pod,ShipmentEvent}.php` |
| Shipment repos (interfaces) | `backend/src/Domain/Shipment/Repository/` |
| Delivery facade | `backend/src/Application/Delivery/DeliveryService.php` |
| Driver HTTP endpoints | `backend/src/Controller/DriverApiController.php` (`/api/driver/...`) |
| Shipment HTTP endpoints | `backend/src/Controller/Api/V1/ShipmentApiController.php` |
| Tracking público | `backend/src/Controller/PublicTrackingController.php` |
| Slot entity + service | `backend/src/Entity/DeliverySlot.php`, `backend/src/Notification/DeliverySlotService.php` |
| Rating entity + service | `backend/src/Entity/DeliveryRating.php`, `backend/src/Notification/DeliveryRatingService.php` |
| Zonas | `backend/src/Entity/DeliveryZone.php`, `backend/src/Service/DeliveryZoneService.php`, `backend/src/Service/ZonePerformanceService.php` |
| Riesgo | `backend/src/Service/DeliveryRiskService.php`, `backend/src/Service/AddressRiskService.php` |
| Evidence + notas | `backend/src/Service/DeliveryEvidenceFactory.php`, `backend/src/Service/DeliveryNoteGenerator.php` |
| Post-route | `backend/src/Service/PostRouteAnalyzer.php` + `PostRouteAnalysisListener` |
| Enums | `backend/src/Enum/{ShipmentEventType,ParcelStatus,ExceptionCode,RouteStopStatus,RouteStatus,ShipmentPriority,ServiceType}.php` |
| Eventos de dominio | `backend/src/Domain/Event/{StopDelivered,StopExceptionReported,ShipmentCreated}.php` |
| Templates cara al cliente | `backend/templates/tracking/public.html.twig`, `backend/templates/customer/shipment/` |

## Gotchas

- **Multi-parcel shipments:** `Shipment::recalculateTotals()` debe llamarse tras cada `addParcel/removeParcel/setWeight/setVolume` — no hay hook automático. Sin llamada, `totalWeightKg/totalVolumeM3/totalParcels` quedan stale y el albarán/risk scoring usa valores incorrectos.
- **Parcel.status desincronizado con Shipment:** `ParcelStatus` transiciones son manuales y NO están enlazadas con `ShipmentEventType`. Marcar `ShipmentEvent(DELIVERED)` NO actualiza automáticamente `Parcel.status` a `DELIVERED`. [PLANNED] listener sync.
- **Eventos intermedios no se emiten automáticamente:** `PICKED_UP`, `IN_HUB`, `IN_TRANSIT`, `OUT_FOR_DELIVERY` solo se generan vía integraciones/webhooks manuales — no hay listener en `RouteStarted` que los emita. La UI los renderiza si llegan, pero no hay producer interno garantizado.
- **Entrega parcial (partial delivery) no soportada:** el modelo no tiene `PartialDelivered` — una excepción en un parcel implica excepción a nivel `RouteStop`/`Shipment` entero. Para desacoplar, habría que extender `DeliveryService` para aceptar `parcel_public_id[]` y emitir eventos por parcel.
- **Reschedule flow:** crear `ShipmentEvent(RESCHEDULE_REQUESTED)` es un "flag" informativo — no cancela la parada actual ni genera nuevos DeliverySlots. El operador debe actuar manualmente (el evento solo dispara notificaciones via `NotifyDeliveryListener`). [PARTIAL].
- **DeliverySlot.expire vs race:** `selectSlot` itera slots hermanos marcándolos expired en el mismo flush. Dos requests concurrentes pueden acabar con dos slots `selected` — no hay lock pesimista/versionado en la entidad. Para customers high-volume, envolver en transacción SERIALIZABLE.
- **Pod.shipment es nullable:** Pods creados via `DeliveryService::deliverStop` solo resuelven Shipment si `input.shipmentPublicId` está presente. Una parada sin `shipment_public_id` genera POD huérfano — el POD siempre referencia al `RouteStop` pero puede no tener `Shipment` para trazabilidad cliente.
- **DeliveryService viola DIP parcialmente:** depende de `EntityManagerInterface` directamente para `persist(Pod)` y `persist(ShipmentEvent)` en lugar de repos del Domain layer. Documentado en `backend/CLAUDE.md` > Known Violations. Al tocar este servicio, migrar a `PodRepositoryInterface::save()` y `ShipmentEventRepositoryInterface::save()`.
- **DeliveryRating constraint único por Shipment:** no hay flujo de "editar rating" — segunda llamada lanza `LogicException`. Si se requiere, abrir nueva interacción (brainstorm).

## Historial

- 2026-04-19: Creación inicial del módulo. Documentado: lifecycle end-to-end, POD flow, slot lifecycle, risk + rating, DeliveryService facade, gotchas (multi-parcel, partial delivery, reschedule, DIP violation).
