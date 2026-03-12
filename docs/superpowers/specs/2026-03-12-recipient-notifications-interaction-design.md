# Spec: Notificaciones al Destinatario — Interacción Bidireccional + Configuración + Protecciones

**Fecha:** 2026-03-12
**Fase:** 3.2 del Plan Maestro (Experiencia del Receptor — Interacción)
**Estado:** Aprobado
**Prerequisito:** `2026-03-12-symfony-notifier-design.md` (migración a symfony/notifier)
**Objetivo:** Permitir que el destinatario interactúe desde la tracking page (confirmar presencia, reprogramar, calificar), que cada customer configure sus preferencias de notificación, y proteger contra spam/costos con throttling y deduplicación.

---

## Contexto

### Sistema tras la migración a symfony/notifier (spec anterior)

Después de la spec `2026-03-12-symfony-notifier-design.md`, el sistema tiene:
- Envío async de SMS via Messenger + symfony/notifier
- Multi-tenant via `TenantAwareSmsTransport` + `CustomerIntegration`
- 5 Notification classes (approaching, delivered, rating, slot_confirmed, rescheduled)
- `RecipientNotificationService` dispatcha al bus

### Lo que falta (esta spec)

1. **Interacción bidireccional** — El destinatario no puede responder desde la tracking page
2. **Configuración per-customer** — Todos los customers reciben las mismas notificaciones con los mismos templates
3. **Sin protecciones** — No hay throttling, deduplicación, quiet hours, ni quotas
4. **Sin triggers programados** — Reminder (noche anterior) y presence check (~30 min antes) no existen

---

## Relación con la spec anterior (symfony/notifier)

### NotificationLog reemplaza RecipientNotification

`NotificationLog` **reemplaza** la entity `RecipientNotification` de la spec anterior. `RecipientNotification` es una entity simple de tracking con campos limitados (`templateName`, `status`, `sentAt`). `NotificationLog` cubre el mismo propósito con más detalle (trigger type, channel, provider response, phone, message content). La migración elimina `RecipientNotification` y su tabla.

### SendNotificationMessage reemplaza SendRecipientNotificationMessage

El pipeline de la spec anterior (`RecipientNotificationService` → `SendRecipientNotificationMessage` → `SendRecipientNotificationHandler`) queda **supersedido** por:

```
EventListener → NotificationDispatcher → SendNotificationMessage → SendNotificationHandler
```

La diferencia clave: la spec anterior resolvía el template **dentro** del handler (el handler decidía qué Notification class usar). En esta spec, el `NotificationResolver` resuelve template y canal **antes** del dispatch, y el message ya lleva el texto renderizado. Esto permite que las customer preferences influyan en la resolución.

`RecipientNotificationService` se mantiene pero se refactoriza para usar `NotificationDispatcher` internamente en vez de despachar `SendRecipientNotificationMessage` directamente.

---

## Diseño

### Sección 1: NotificationLog

Entidad para registrar cada notificación enviada, incluyendo estado y respuesta del provider.

```php
#[ORM\Entity]
#[ORM\Index(columns: ['shipment_id', 'trigger_type', 'channel'], name: 'idx_notif_dedup')]
#[ORM\Index(columns: ['recipient_phone', 'channel', 'created_at'], name: 'idx_notif_throttle')]
#[ORM\Index(columns: ['customer_id', 'channel', 'created_at'], name: 'idx_notif_quota')]
class NotificationLog implements CustomerScopedEntityInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Shipment $shipment;

    // Denormalized for efficient quota counting and tenant scoping
    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(type: 'string', enumType: NotificationChannel::class)]
    private NotificationChannel $channel;

    #[ORM\Column(type: 'string', enumType: NotificationTriggerType::class)]
    private NotificationTriggerType $triggerType;

    #[ORM\Column(length: 20)]
    private string $recipientPhone;

    #[ORM\Column(type: 'text')]
    private string $messageContent;

    #[ORM\Column(type: 'string', enumType: NotificationLogStatus::class)]
    private NotificationLogStatus $status;

    #[ORM\Column(type: 'json')]
    private array $providerResponse = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

---

### Sección 2: RecipientAction

Registra cada acción que toma el destinatario desde la tracking page.

```php
#[ORM\Entity]
class RecipientAction
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Shipment $shipment;

    #[ORM\Column(type: 'string', enumType: RecipientActionType::class)]
    private RecipientActionType $actionType;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

#### RecipientActionType enum

```php
enum RecipientActionType: string
{
    case PresenceConfirmed = 'presence_confirmed';
    case PresenceDenied = 'presence_denied';
    case RescheduleRequested = 'reschedule_requested';
    case AlternativeRequested = 'alternative_requested';
    case RatingSubmitted = 'rating_submitted';
    case TrackingPageViewed = 'tracking_page_viewed';
}
```

#### Tracking page endpoints nuevos

| Método | Path | Propósito |
|--------|------|-----------|
| `POST` | `/track/{token}/confirm-presence` | Confirmar/negar presencia |
| `POST` | `/track/{token}/reschedule` | Solicitar reprogramación |
| `POST` | `/track/{token}/alternative` | Solicitar alternativa (portería, vecino) |

#### Flujo de interacción completo

```
1. REMINDER (noche anterior)
   SMS → Destinatario abre tracking page (tracking_page_viewed)

2. PRESENCE CONFIRMATION (~30 min antes)
   SMS → Confirma (presence_confirmed) o niega (presence_denied)
       → Si niega: opciones de reprogramar/alternativa

3. DELIVERY NOTIFICATION (post-entrega)
   SMS → Detalles de entrega o opciones de reprogramación si excepción

4. LIVE ETA (cuando cambia >15 min)
   SMS → Tracking page con ETA actualizado
```

#### Tracking page secciones condicionales

| Estado del envío | Sección visible |
|-----------------|-----------------|
| Pendiente (día anterior) | Info + franja horaria |
| En camino | ETA live + mapa + "Confirmar presencia" |
| Cercano (<30 min) | ETA + "¿Estarás?" con Sí/No |
| Entregado | Resumen + formulario calificación |
| Excepción | Opciones de reprogramación |

---

### Sección 3: NotificationPreference (Configuración per-customer)

```php
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['customer_id', 'trigger_type', 'channel'])]
class NotificationPreference implements CustomerScopedEntityInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(type: 'string', enumType: NotificationTriggerType::class)]
    private NotificationTriggerType $triggerType;

    #[ORM\Column(type: 'string', enumType: NotificationChannel::class)]
    private NotificationChannel $channel;

    #[ORM\Column]
    private bool $enabled = true;

    // Template con placeholders: {recipient_name}, {tracking_url}, {eta}, {time_window}
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $messageTemplate = null;

    // Timing config (JSON): hours_before, minutes_before, delay_minutes, threshold_minutes
    #[ORM\Column(type: 'json')]
    private array $timingConfig = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;
}
```

#### NotificationTriggerType enum

```php
enum NotificationTriggerType: string
{
    case Reminder = 'reminder';
    case PresenceCheck = 'presence_check';
    case Delivered = 'delivered';
    case DeliveryException = 'delivery_exception';
    case EtaChange = 'eta_change';
    case OutForDelivery = 'out_for_delivery';
}
```

#### NotificationChannel enum

```php
enum NotificationChannel: string
{
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
}
```

#### Templates y timing por defecto

`DefaultNotificationTemplates` — templates por defecto por (trigger, channel). Si `messageTemplate` es null en la preference, se usa el default.

`DefaultNotificationTiming` — timing por defecto por trigger:

| Trigger | Timing default |
|---------|---------------|
| `reminder` | `hours_before: 12` |
| `presence_check` | `minutes_before: 30` |
| `delivered` | `delay_minutes: 5` |
| `delivery_exception` | `delay_minutes: 10` |
| `eta_change` | `threshold_minutes: 15` |
| `out_for_delivery` | inmediato |

#### API de configuración

| Método | Path | Rol |
|--------|------|-----|
| `GET` | `/api/notification-preferences` | ROLE_CUSTOMER |
| `POST` | `/api/notification-preferences` | ROLE_CUSTOMER |
| `DELETE` | `/api/notification-preferences/{publicId}` | ROLE_CUSTOMER |
| `GET` | `/api/notification-logs` | ROLE_CUSTOMER |

---

### Sección 4: Orquestación (NotificationResolver + NotificationDispatcher)

#### Flujo de arquitectura

```
[Evento del sistema / Scheduler]
       ↓
[EventListener / Command]  →  NotificationResolver::resolve()
       ↓                              ↓
NotificationCommand[]          (trigger + customer preferences)
       ↓
NotificationDispatcher::dispatch()
       ↓
[Messenger async + DelayStamp]  →  SendNotificationHandler
       ↓
[Gates: dedup, quiet hours, throttle, quota]
       ↓
ProviderFactory::create()  →  Provider::send()
       ↓
NotificationLog (persistido)
```

#### NotificationResolver

Dado (shipment, trigger), consulta `NotificationPreference` del customer. Si no tiene, usa defaults (SMS habilitado). Genera `NotificationCommand[]` con canal, mensaje renderizado, y timing.

#### NotificationDispatcher

Recibe commands y despacha `SendNotificationMessage` via Messenger. Aplica `DelayStamp` si el timing incluye delay.

#### SendNotificationMessage (Messenger message)

```php
final readonly class SendNotificationMessage
{
    public function __construct(
        public int $shipmentId,
        public string $channel,
        public string $triggerType,
        public string $recipientPhone,
        public string $message,
        public array $timing,
    ) {}
}
```

#### Event Listeners

| Listener | Evento | Trigger |
|----------|--------|---------|
| `RouteStartedNotificationListener` | `RouteStartedEvent` | `out_for_delivery` |
| `ShipmentDeliveredNotificationListener` | `ShipmentDeliveredEvent` | `delivered` |
| `ShipmentExceptionNotificationListener` | `ShipmentExceptionEvent` | `delivery_exception` |
| `EtaChangedNotificationListener` | `ShipmentEtaChangedEvent` | `eta_change` |

#### Triggers programados (Scheduler command)

`app:notifications:schedule` — ejecuta cada 5 min:
- **Reminder:** shipments para mañana sin reminder enviado
- **Presence check:** shipments con ETA en ~45 min sin presence_check enviado

#### Messenger config

```yaml
framework:
    messenger:
        transports:
            notifications:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 5000
                    multiplier: 3
        routing:
            'App\Message\SendNotificationMessage': notifications
```

---

### Sección 5: Protecciones (Throttling, Deduplicación, Quiet Hours, Quotas)

Todas las gates se evalúan en `SendNotificationHandler` antes de enviar.

#### Gate 1: Deduplicación

Un mismo `(shipment, trigger_type, channel)` con status `sent` solo se envía una vez.

#### Gate 2: Quiet Hours (`QuietHoursGuard`)

No enviar entre 22:00-08:00 en la **timezone del sistema** (configurada en `APP_TIMEZONE`, default `Europe/Madrid`). Si cae en quiet hours, se re-encola con delay hasta las 08:00.

#### Gate 3: Throttle por destinatario (`RecipientThrottle`)

- Max 6 SMS por teléfono por día
- Min 10 minutos entre mensajes al mismo teléfono
- Si se excede: log con status `throttled`, no se reintenta

#### Gate 4: Quota por customer (`CustomerNotificationQuota`)

- Default 1000 SMS/mes por customer
- Configurable via `Customer.notificationQuota`
- Si se excede: log con status `throttled`

#### Flujo completo del handler

```php
// Gate 1: Deduplicación → return si ya enviado
// Gate 2: Quiet hours → re-encolar si fuera de horario
// Gate 3: Throttle destinatario → throttled si excede límite
// Gate 4: Quota customer → throttled si excede quota
// Enviar via provider
// Log resultado (sent/failed)
```

#### NotificationLogStatus enum

```php
enum NotificationLogStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
    case Throttled = 'throttled';
    case Deferred = 'deferred';
}
```

| Status | Significado |
|--------|-------------|
| `sent` | Enviado exitosamente |
| `failed` | Error del provider (se reintenta via Messenger) |
| `throttled` | Bloqueado por rate limit (no se reintenta) |
| `deferred` | Pospuesto por quiet hours (re-encolado) |

---

## Entidades nuevas (resumen)

| Entidad | Multi-tenant |
|---------|--------------|
| `NotificationLog` | Sí (CustomerScopedEntityInterface, `customer_id` denormalized) |
| `RecipientAction` | No (público via token) |
| `NotificationPreference` | Sí (CustomerScopedEntityInterface) |

## Enums nuevos

| Enum | Valores |
|------|---------|
| `NotificationTriggerType` | reminder, presence_check, out_for_delivery, delivered, delivery_exception, eta_change |
| `NotificationChannel` | sms, whatsapp |
| `RecipientActionType` | presence_confirmed, presence_denied, reschedule_requested, alternative_requested, rating_submitted, tracking_page_viewed |
| `NotificationLogStatus` | sent, failed, throttled, deferred |

## Servicios nuevos

| Servicio | Responsabilidad |
|----------|-----------------|
| `NotificationResolver` | (shipment, trigger) → NotificationCommand[] según preferences |
| `NotificationDispatcher` | Commands → Messenger async |
| `SendNotificationHandler` | Gates → Provider → Log |
| `DefaultNotificationTemplates` | Templates por defecto por (trigger, canal) |
| `DefaultNotificationTiming` | Timing por defecto por trigger |
| `RecipientThrottle` | Max 6/día, min 10 min entre mensajes |
| `CustomerNotificationQuota` | Límite mensual por customer |
| `QuietHoursGuard` | No enviar 22:00-08:00 |
| `ScheduleNotificationsCommand` | Cron cada 5 min: reminder + presence_check |

## Cambios en entidades existentes

| Entidad | Cambio |
|---------|--------|
| `Customer` | Nuevo campo `notificationQuota: ?int` (`null` = usar default del sistema: 1000/mes) |
| `PublicTrackingController` | Nuevos endpoints: confirm-presence, reschedule, alternative |

## Endpoints nuevos

| Método | Path | Rol |
|--------|------|-----|
| `POST` | `/track/{token}/confirm-presence` | Público |
| `POST` | `/track/{token}/reschedule` | Público |
| `POST` | `/track/{token}/alternative` | Público |
| `GET` | `/api/notification-preferences` | ROLE_CUSTOMER |
| `POST` | `/api/notification-preferences` | ROLE_CUSTOMER |
| `DELETE` | `/api/notification-preferences/{publicId}` | ROLE_CUSTOMER |
| `GET` | `/api/notification-logs` | ROLE_CUSTOMER |

## Archivos a eliminar (de la spec anterior)

| Archivo | Razón |
|---------|-------|
| `src/Entity/RecipientNotification.php` | Reemplazado por `NotificationLog` |
| `src/Notification/Message/SendRecipientNotificationMessage.php` | Reemplazado por `SendNotificationMessage` |
| `src/Notification/Message/SendRecipientNotificationHandler.php` | Reemplazado por `SendNotificationHandler` |

## Limitaciones conocidas

- **RecipientAction sin `customer_id`**: Para consultar acciones de destinatarios por customer, se requiere JOIN a través de Shipment→Route→Customer. Aceptable porque las queries sobre RecipientAction serán por shipment (que ya tiene el scope correcto), no por customer directamente.

## Lo que NO está en scope

- Email / Push notifications (se añadirán al enum `NotificationChannel` cuando se implementen)
- UI de configuración de templates (solo API en esta fase)
- Internacionalización de templates (solo español)
- Webhook de status delivery del provider (ej: Twilio status callbacks)
- Encriptación de credenciales en CustomerIntegration (backlog existente)
