# Notifications

**Última actualización:** 2026-03-12
**Estado:** Vigente

## Canales de Notificación

| Canal | Entidad | Destinatario | Trigger |
|-------|---------|-------------|---------|
| **In-app** | `Notification` | Usuarios del sistema | Varios (rutas, envíos) |
| **SMS** | `RecipientNotification` | Destinatario de envío | Conductor cerca (500m), entrega, ruta iniciada, rating, slot, reschedule |
| **Web Push** | `PushSubscription` | Usuarios suscritos | Eventos de ruta/envío |
| **Webhook** | `WebhookEndpoint` | Sistemas externos | Eventos configurados |

## Arquitectura SMS (symfony/notifier + Messenger)

### Stack

- **symfony/notifier** ^7.4: `NotifierInterface`, `SmsNotificationInterface`, `Recipient`
- **symfony/twilio-notifier** ^7.4: `TwilioTransportFactory` para envío real vía Twilio API
- **symfony/messenger**: Dispatch asíncrono con transporte Doctrine, retry exponencial

### Flujo

1. **Event subscriber** o **service** detecta evento → llama a `RecipientNotificationService`
2. `RecipientNotificationService` valida que hay teléfono y despacha `SendRecipientNotificationMessage` al bus
3. **Messenger worker** consume el mensaje → `SendRecipientNotificationHandler` procesa:
   - Carga `RouteStop` + `Shipment` desde BD
   - Configura tenant en `TenantAwareSmsTransport` (si hay `customerId`)
   - Construye la `Notification` correspondiente (match por tipo)
   - Envía vía `NotifierInterface::send()`
   - Registra `RecipientNotification` (éxito/fallo)

### Multi-tenant SMS

Cada customer puede configurar sus propias credenciales Twilio via `CustomerIntegration`:

- `TenantAwareSmsTransport`: proxy que resuelve el transporte per-tenant vía `ProviderResolver`
- Si no hay config de customer → usa el transporte default (`NullSmsTransport` en dev)
- `TwilioSmsTransportFactory`: crea transportes Twilio a partir de config `{account_sid, auth_token, from_number}`
- Registrado como `ProviderFactoryInterface` con `ServiceType::SmsNotifier`

### Clases de Notificación

| Clase | Template Name | Trigger |
|-------|--------------|---------|
| `DeliveryApproachingNotification` | `pre_delivery_notification` | `approaching`, `route_started` |
| `DeliveryCompletedNotification` | `delivery_completed` | `delivered` |
| `RatingRequestNotification` | `rating_request` | `rating_request` |
| `DeliverySlotConfirmedNotification` | `delivery_slot_confirmation` | `slot_confirmed` |
| `RescheduleConfirmedNotification` | `reschedule_confirmation` | `rescheduled` |

Todas implementan `SmsNotificationInterface` + extienden `Notification` de Symfony.

### Messenger Message

- `SendRecipientNotificationMessage(routeStopId: string, notificationType: string, customerId: ?string)`
- Routed a `async` transport en `messenger.yaml`
- IDs son `string` porque Doctrine BIGINT devuelve string en PHP

## Componentes

### Event Subscribers

| Subscriber | Evento | Acción |
|------------|--------|--------|
| `ApproachingNotificationSubscriber` | `VehiclePositionReceived` | SMS cuando conductor está a 500m (haversine) |
| `RouteActivatedNotificationSubscriber` | Route activated | Notifica activación de ruta |
| `NotifyDeliveryListener` | `StopDelivered`/`StopExceptionReported` | Notificación de entrega o excepción |

### Servicios

- `RecipientNotificationService`: Despacha mensajes al bus (no envía directamente)
- `SendRecipientNotificationHandler`: Handler Messenger que envía SMS vía NotifierInterface
- `PushNotificationService`: Web Push via suscripciones
- `WebhookDispatcher`: Dispatch de webhooks a endpoints configurados

### Transportes

- `NullSmsTransport`: No-op para desarrollo, logea mensajes
- `TenantAwareSmsTransport`: Proxy que resuelve transporte per-customer
- Twilio: Via `symfony/twilio-notifier` + `TwilioSmsTransportFactory`

## Webhook System

Los customers configuran `WebhookEndpoint` entities con URLs donde recibir eventos:

- Endpoints configurables por customer
- Gestión via API v1: `GET/POST/DELETE /api/v1/webhooks`
- Firma de payloads para verificación

## Configuración

```yaml
# config/packages/notifier.yaml
framework:
    notifier:
        texter_transports:
            default: 'null://null'
        channel_policy:
            urgent: ['sms']
            high: ['sms']

# config/packages/messenger.yaml (routing)
'App\Notification\Message\SendRecipientNotificationMessage': async

# .env
DEFAULT_SMS_NOTIFIER=null  # 'twilio' en producción
```

## Historial

- 2026-03-12: Migración a symfony/notifier + Messenger async, multi-tenant SMS
- 2026-03-11: Creación inicial
