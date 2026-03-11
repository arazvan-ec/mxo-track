# Notifications

**Última actualización:** 2026-03-11
**Estado:** Vigente

## Canales de Notificación

| Canal | Entidad | Destinatario | Trigger |
|-------|---------|-------------|---------|
| **In-app** | `Notification` | Usuarios del sistema | Varios (rutas, envíos) |
| **SMS/WhatsApp** | `RecipientNotification` | Destinatario de envío | Conductor cerca (500m), entrega, excepción |
| **Web Push** | `PushSubscription` | Usuarios suscritos | Eventos de ruta/envío |
| **Webhook** | `WebhookEndpoint` | Sistemas externos | Eventos configurados |

## Componentes

### Event Subscribers

| Subscriber | Evento | Acción |
|------------|--------|--------|
| `ApproachingNotificationSubscriber` | `VehiclePositionReceived` | SMS/WhatsApp cuando conductor está a 500m (usa haversine interno) |
| `RouteActivatedNotificationSubscriber` | Route activated | Notifica activación de ruta |
| `NotifyDeliveryListener` | `StopDelivered`/`StopExceptionReported` | Notificación de entrega o excepción |

### Servicios

- `RecipientNotificationService`: Envío de SMS/WhatsApp al destinatario
- `PushNotificationService`: Web Push via suscripciones
- `WebhookDispatcher`: Dispatch de webhooks a endpoints configurados

## Webhook System

Los customers configuran `WebhookEndpoint` entities con URLs donde recibir eventos:

- Endpoints configurables por customer
- Gestión via API v1: `GET/POST/DELETE /api/v1/webhooks`
- Firma de payloads para verificación

## Historial

- 2026-03-11: Creación inicial
