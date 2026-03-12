# Spec: Migración a symfony/notifier + Messenger multi-tenant

**Fecha:** 2026-03-12
**Fase:** 3.1 del Plan Maestro (Experiencia del Receptor)
**Estado:** Aprobado
**Objetivo:** Reemplazar el sistema custom de notificaciones (Provider/Channel/Template) por `symfony/notifier` con envío async vía Messenger y soporte multi-tenant por customer.

---

## Contexto

### Sistema actual

El sistema de notificaciones al receptor está construido con una arquitectura custom:

```
EventSubscriber (síncrono)
  → RecipientNotificationService.notify*(RouteStop)
    → NotificationChannelInterface.send() (SmsChannel, WhatsAppChannel, LogChannel)
      → SmsProviderInterface / WhatsAppProviderInterface
        → TwilioSmsProvider / TwilioWhatsAppProvider (HTTP directo)
    → RecipientNotification entity (tracking)
```

**Problemas:**
1. **Síncrono** — SMS se envía dentro del event handler, bloqueando el request
2. **Sin retries** — si Twilio falla, se loguea y se pierde
3. **Sin multi-tenant** — una sola config de Twilio para todo el sistema (env vars)
4. **Código custom redundante** — lo que symfony/notifier resuelve nativamente
5. **PreDeliveryNotificationMessage existe pero no se usa** — la intención de async nunca se completó

### Decisión

Migrar completamente a `symfony/notifier` + `symfony/messenger`:
- Usar `SmsChannel` nativo de Symfony con transport Twilio
- Envío asíncrono vía Messenger con retry policies
- Multi-tenant: cada customer puede configurar sus credenciales Twilio vía `CustomerIntegration`
- Mantener `RecipientNotification` entity para tracking de negocio

---

## Diseño

### 1. Paquetes nuevos

```
symfony/notifier: ^7.4
symfony/twilio-notifier: ^7.4
```

### 2. Notification classes (reemplazan Templates)

5 clases Symfony `Notification` que implementan `SmsNotificationInterface`:

| Clase nueva | Template que reemplaza | `getTemplateName()` |
|---|---|---|
| `DeliveryApproachingNotification` | `PreDeliveryTemplate` | `pre_delivery_notification` |
| `DeliveryCompletedNotification` | `DeliveryCompletedTemplate` | `delivery_completed` |
| `RatingRequestNotification` | `RatingRequestTemplate` | `rating_request` |
| `DeliverySlotConfirmedNotification` | `DeliverySlotConfirmationTemplate` | `delivery_slot_confirmation` |
| `RescheduleConfirmedNotification` | `RescheduleConfirmationTemplate` | `reschedule_confirmation` |

Cada Notification:
- Extiende `Symfony\Component\Notifier\Notification\Notification`
- Implementa `SmsNotificationInterface`
- Define `asSmsMessage(SmsRecipientInterface $recipient): ?SmsMessage` con el mismo texto que el template actual
- Conserva un método `getTemplateName(): string` para compatibilidad con `RecipientNotification` entity
- Recibe los mismos parámetros que el template original (recipientName, trackingUrl, etc.)

**Ejemplo — DeliveryApproachingNotification:**

```php
<?php
declare(strict_types=1);

namespace App\Notification;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

final class DeliveryApproachingNotification extends Notification implements SmsNotificationInterface
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $driverName,
        private readonly \DateTimeInterface $estimatedArrival,
        private readonly string $trackingUrl,
    ) {
        parent::__construct('Su entrega llega pronto');
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        return new SmsMessage(
            $recipient->getPhone(),
            sprintf(
                'Hola %s, su entrega llega en ~30 minutos. Conductor: %s. Seguimiento: %s',
                $this->recipientName,
                $this->driverName,
                $this->trackingUrl,
            ),
        );
    }

    public function getTemplateName(): string
    {
        return 'pre_delivery_notification';
    }
}
```

### 3. Recipient: SmsRecipient

Se usa `Symfony\Component\Notifier\Recipient\Recipient` directamente:

```php
use Symfony\Component\Notifier\Recipient\Recipient;

$recipient = new Recipient('', $phoneNumber); // email vacío, phone requerido
```

### 4. Multi-tenant Transport

#### 4.1 ServiceType enum — añadir caso

```php
enum ServiceType: string
{
    case RouteOptimizer = 'route_optimizer';
    case RoutingEngine = 'routing_engine';
    case GpsProvider = 'gps_provider';
    case RealtimePublisher = 'realtime_publisher';
    case SmsNotifier = 'sms_notifier'; // NUEVO
}
```

#### 4.2 SmsNotifierProviderType enum (nuevo)

```php
enum SmsNotifierProviderType: string
{
    case Twilio = 'twilio';
    case Null = 'null';
}
```

#### 4.3 TwilioSmsTransportFactory (nuevo)

Implementa `ProviderFactoryInterface`. Crea un `TwilioTransport` de `symfony/twilio-notifier` a partir de credenciales en `CustomerIntegration.config`:

```php
final class TwilioSmsTransportFactory implements ProviderFactoryInterface
{
    public function create(array $config): object
    {
        // config: {account_sid, auth_token, from_number}
        $dsn = new Dsn(sprintf(
            'twilio://%s:%s@default?from=%s',
            $config['account_sid'],
            $config['auth_token'],
            urlencode($config['from_number']),
        ));
        return (new TwilioTransportFactory())->create($dsn);
    }

    public function getProviderType(): string
    {
        return SmsNotifierProviderType::Twilio->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::SmsNotifier;
    }
}
```

#### 4.4 NullSmsTransport (nuevo)

Para desarrollo — loguea el SMS pero no envía:

```php
final class NullSmsTransport implements TransportInterface
{
    public function send(MessageInterface $message): SentMessage
    {
        // Log message, return SentMessage
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }
}
```

#### 4.5 TenantAwareSmsTransport (nuevo)

Implementa `TransportInterface` de symfony/notifier. Resuelve el transport correcto por customer.

**Problema clave:** `TenantContext` usa `Security::getUser()` que no funciona en workers async de Messenger (no hay sesión HTTP). La solución es que el handler pase el `Customer` explícitamente al transport usando un `CustomerStamp` en el mensaje, sin depender de `TenantContext`.

**Solución: Messenger middleware + stamp**

1. `CustomerStamp` — stamp personalizado que lleva el `customerId` en el mensaje
2. `SetCustomerMiddleware` — middleware de Messenger que lee el stamp y configura el transport
3. `TenantAwareSmsTransport` acepta un `?Customer` explícito via setter (no `TenantContext`)

```php
// Stamp para propagar tenant en Messenger
final class CustomerStamp implements StampInterface
{
    public function __construct(public readonly ?int $customerId) {}
}

// Transport que resuelve per-tenant
final class TenantAwareSmsTransport implements TransportInterface
{
    private ?Customer $currentCustomer = null;

    public function __construct(
        private readonly ProviderResolverInterface $resolver,
        private readonly TransportInterface $defaultTransport,
    ) {}

    public function setCustomer(?Customer $customer): void
    {
        $this->currentCustomer = $customer;
    }

    public function send(MessageInterface $message): SentMessage
    {
        if ($this->currentCustomer !== null) {
            $transport = $this->resolver->resolve(
                ServiceType::SmsNotifier,
                $this->currentCustomer,
            );

            if ($transport instanceof TransportInterface) {
                return $transport->send($message);
            }
        }

        return $this->defaultTransport->send($message);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    public function __toString(): string
    {
        return 'tenant-aware-sms';
    }
}
```

### 5. Messenger async

#### 5.1 SendRecipientNotificationMessage (nuevo)

Mensaje serializable para el bus:

```php
final readonly class SendRecipientNotificationMessage
{
    public function __construct(
        public int $routeStopId,
        public string $notificationType, // 'approaching', 'delivered', 'route_started'
        public ?int $customerId = null,
    ) {}
}
```

**Nota:** Se usa `routeStopId` (internal ID, no public) porque es un mensaje interno del sistema, nunca expuesto en API pública.

#### 5.2 SendRecipientNotificationHandler (nuevo)

```php
#[AsMessageHandler]
final class SendRecipientNotificationHandler
{
    public function __construct(
        private readonly NotifierInterface $notifier,
        private readonly TenantAwareSmsTransport $transport,
        private readonly EntityManagerInterface $em,
        private readonly string $appBaseUrl,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SendRecipientNotificationMessage $message): void
    {
        $stop = $this->em->find(RouteStop::class, $message->routeStopId);
        if ($stop === null) return;

        $shipment = $stop->getShipment();
        if ($shipment === null) return;

        $phone = $shipment->getRecipientPhone() ?? $stop->getRecipientPhone();
        if ($phone === null || $phone === '') return;

        // Set tenant for multi-tenant transport resolution
        if ($message->customerId !== null) {
            $customer = $this->em->find(Customer::class, $message->customerId);
            $this->transport->setCustomer($customer);
        }

        $recipientName = $shipment->getRecipientName() ?? $stop->getRecipientName() ?? 'Cliente';
        $notification = $this->buildNotification($message->notificationType, $stop, $shipment, $recipientName);
        if ($notification === null) return;

        $recipient = new Recipient('', $phone);

        try {
            $this->notifier->send($notification, $recipient);
            $this->recordNotification($shipment, $phone, 'sms', $notification->getTemplateName(), true);
        } catch (\Throwable $e) {
            $this->recordNotification($shipment, $phone, 'sms', $notification->getTemplateName(), false, $e->getMessage());
            throw $e; // Re-throw para que Messenger haga retry
        } finally {
            $this->transport->setCustomer(null); // Cleanup
        }
    }
}
```

**Mapping completo de `notificationType`:**

| `notificationType` | Notification class | Trigger |
|---|---|---|
| `approaching` | `DeliveryApproachingNotification` | Vehículo a <500m |
| `route_started` | `DeliveryApproachingNotification` | Ruta activada (con ETA real) |
| `delivered` | `DeliveryCompletedNotification` | Stop entregado |
| `rating_request` | `RatingRequestNotification` | Post-delivery |
| `slot_confirmed` | `DeliverySlotConfirmedNotification` | Slot seleccionado |
| `rescheduled` | `RescheduleConfirmedNotification` | Entrega reprogramada |
```

#### 5.3 Messenger routing (messenger.yaml)

```yaml
routing:
    App\Notification\Message\SendRecipientNotificationMessage: async
```

Retry policy heredada del transport `async` (Doctrine).

### 6. RecipientNotificationService refactorizado

El servicio pierde la responsabilidad de envío directo. Se convierte en un **dispatcher al bus**:

```php
final class RecipientNotificationService
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly EtaService $etaService,
        private readonly LoggerInterface $logger,
        private readonly string $appBaseUrl = '',
    ) {}

    public function notifyRouteStarted(Route $route): void
    {
        // Calcula ETAs y dispatcha un message por cada stop
        foreach ($stops as $stop) {
            $this->bus->dispatch(new SendRecipientNotificationMessage(
                routeStopId: $stop->getId(),
                notificationType: 'route_started',
                customerId: $route->getCustomer()?->getId(),
            ));
        }
    }

    public function notifyApproaching(RouteStop $stop): void
    {
        $this->bus->dispatch(new SendRecipientNotificationMessage(
            routeStopId: $stop->getId(),
            notificationType: 'approaching',
        ));
    }

    public function notifyDelivered(RouteStop $stop): void
    {
        $this->bus->dispatch(new SendRecipientNotificationMessage(
            routeStopId: $stop->getId(),
            notificationType: 'delivered',
        ));
    }
}
```

### 7. Event Subscribers — sin cambios de interfaz

Los subscribers siguen llamando a `RecipientNotificationService`, que ahora dispatcha al bus internamente. **No necesitan cambio de lógica**, solo su behavior cambia de síncrono a asíncrono de forma transparente.

### 8. Configuración (services.yaml)

**Eliminar:**
```yaml
# Todo lo relacionado con app.notification_channel, SmsProviderInterface alias, WhatsAppProviderInterface alias
# TwilioSmsProvider, TwilioWhatsAppProvider config
```

**Añadir:**
```yaml
# TenantAwareSmsTransport como transport default del Notifier
App\Notification\Transport\TenantAwareSmsTransport:
    arguments:
        $defaultTransport: '@app.sms.default_transport'

# Default transport (env var o null)
app.sms.default_transport:
    class: App\Notification\Transport\NullSmsTransport
    # En producción: cambiar a TwilioTransport con DSN de env

# Provider factory
App\Provider\Factory\TwilioSmsTransportFactory:
    tags: ['app.provider_factory']

# Default SMS notifier for ProviderFactoryRegistry
# Necesario: sin esto ProviderResolver lanza "No default provider configured for sms_notifier"
```

**Env vars nuevas (`.env`):**
```env
DEFAULT_SMS_NOTIFIER=null
# En producción: DEFAULT_SMS_NOTIFIER=twilio
# TWILIO_DSN=twilio://ACCOUNT_SID:AUTH_TOKEN@default?from=+34600000000
```

**ProviderFactoryRegistry** necesita `sms_notifier` en su array `$defaults` (se wirea en `services.yaml`).

### 9. Configuración (notifier.yaml)

```yaml
framework:
    notifier:
        texter_transports:
            default: 'null://null'  # override por TenantAwareSmsTransport en services.yaml
```

**Nota:** El transport real se inyecta vía `TenantAwareSmsTransport`, no vía DSN estático.

---

## Archivos a eliminar (19)

| Archivo | Razón |
|---|---|
| `src/Notification/Provider/SmsProviderInterface.php` | Reemplazado por `TransportInterface` de symfony/notifier |
| `src/Notification/Provider/WhatsAppProviderInterface.php` | Reemplazado por `TransportInterface` |
| `src/Notification/Provider/TwilioSmsProvider.php` | Reemplazado por `symfony/twilio-notifier` |
| `src/Notification/Provider/TwilioWhatsAppProvider.php` | Reemplazado por `symfony/twilio-notifier` |
| `src/Notification/Provider/NullSmsProvider.php` | Reemplazado por `NullSmsTransport` |
| `src/Notification/Provider/NullWhatsAppProvider.php` | Reemplazado por `NullSmsTransport` |
| `src/Notification/Channel/NotificationChannelInterface.php` | Reemplazado por `SmsChannel` de symfony/notifier |
| `src/Notification/Channel/SmsChannel.php` | Reemplazado por `SmsChannel` de symfony/notifier |
| `src/Notification/Channel/WhatsAppChannel.php` | Se migra después |
| `src/Notification/Channel/LogChannel.php` | `NullSmsTransport` cumple este rol |
| `src/Notification/Template/NotificationTemplate.php` | Reemplazado por `Notification` de Symfony |
| `src/Notification/Template/PreDeliveryTemplate.php` | → `DeliveryApproachingNotification` |
| `src/Notification/Template/DeliveryCompletedTemplate.php` | → `DeliveryCompletedNotification` |
| `src/Notification/Template/RatingRequestTemplate.php` | → `RatingRequestNotification` |
| `src/Notification/Template/DeliverySlotConfirmationTemplate.php` | → `DeliverySlotConfirmedNotification` |
| `src/Notification/Template/RescheduleConfirmationTemplate.php` | → `RescheduleConfirmedNotification` |
| `src/Notification/Message/PreDeliveryNotificationMessage.php` | Reemplazado por `SendRecipientNotificationMessage` |
| `src/Notification/Message/PreDeliveryNotificationHandler.php` | Reemplazado por `SendRecipientNotificationHandler` |
| `src/Notification/RecipientPreference.php` | Constantes de canal reemplazadas por sistema de channels de symfony/notifier |

## Archivos a crear (14)

| Archivo | Responsabilidad |
|---|---|
| `config/packages/notifier.yaml` | Configuración de symfony/notifier |
| `src/Notification/DeliveryApproachingNotification.php` | Notification: conductor cerca |
| `src/Notification/DeliveryCompletedNotification.php` | Notification: entrega completada |
| `src/Notification/RatingRequestNotification.php` | Notification: solicitud de valoración |
| `src/Notification/DeliverySlotConfirmedNotification.php` | Notification: slot confirmado |
| `src/Notification/RescheduleConfirmedNotification.php` | Notification: reprogramación |
| `src/Notification/Transport/TenantAwareSmsTransport.php` | Transport multi-tenant |
| `src/Notification/Transport/NullSmsTransport.php` | Transport para desarrollo |
| `src/Notification/Message/SendRecipientNotificationMessage.php` | Mensaje Messenger |
| `src/Notification/Message/SendRecipientNotificationHandler.php` | Handler Messenger |
| `src/Provider/Enum/SmsNotifierProviderType.php` | Enum de providers SMS |
| `src/Provider/Factory/TwilioSmsTransportFactory.php` | Factory multi-tenant |
| `src/Provider/Factory/NullSmsTransportFactory.php` | Factory para NullSmsTransport (default dev) |
| `src/Notification/Transport/CustomerStamp.php` | Stamp de Messenger para propagar tenant |

## Archivos a modificar (5)

| Archivo | Cambio |
|---|---|
| `src/Provider/ServiceType.php` | Añadir `SmsNotifier` case |
| `src/Notification/RecipientNotificationService.php` | Refactorizar: dispatchar al bus en vez de enviar directo |
| `config/packages/messenger.yaml` | Añadir routing para `SendRecipientNotificationMessage` |
| `config/services.yaml` | Eliminar config old, añadir TenantAwareSmsTransport |
| `composer.json` | Añadir `symfony/notifier`, `symfony/twilio-notifier` |

## Archivos NO modificados

| Archivo | Razón |
|---|---|
| `src/EventSubscriber/ApproachingNotificationSubscriber.php` | Sigue llamando a `RecipientNotificationService` (ahora async internamente) |
| `src/EventSubscriber/RouteActivatedNotificationSubscriber.php` | Ídem |
| `src/Entity/RecipientNotification.php` | Entity de tracking intacto |
| `src/Notification/DeliverySlotService.php` | Sin cambios |
| `src/Notification/DeliveryRatingService.php` | Sin cambios |

---

## Flujo final

```
VehiclePositionReceived event
  → ApproachingNotificationSubscriber (haversine < 500m, dedup check)
    → RecipientNotificationService.notifyApproaching(stop)
      → MessageBus.dispatch(SendRecipientNotificationMessage)
        → [async via Doctrine transport]
          → SendRecipientNotificationHandler
            → Build DeliveryApproachingNotification
            → TenantAwareSmsTransport.send()
              → ProviderResolver → CustomerIntegration → TwilioSmsTransportFactory
              → TwilioTransport.send(SmsMessage) → Twilio API
            → RecipientNotification entity persisted
        → [on failure: retry 3x, then dead letter 'failed' transport]
```

---

## Notas

- **WhatsApp** se migra en una fase posterior. Esta spec cubre solo SMS.
- **RecipientPreference** se elimina — las constantes de canal se reemplazan por el sistema de channels de symfony/notifier.
- **Tests existentes** que referencien las clases eliminadas deberán adaptarse.
- **TWILIO_DSN** env var: `twilio://ACCOUNT_SID:AUTH_TOKEN@default?from=FROM_NUMBER` — formato estándar de symfony/twilio-notifier.
- **Flex recipe:** Al instalar `symfony/twilio-notifier`, Flex puede auto-generar config en `notifier.yaml`. Aceptar la recipe pero ajustar el DSN para que apunte a `null://null` por defecto (el transport real es `TenantAwareSmsTransport`).
- **symfony/messenger** ya está en `composer.json` — no requiere instalación adicional.
- **TenantContext no se usa en el handler:** En workers async no hay sesión HTTP, así que el `customerId` se pasa explícitamente en el mensaje y el handler carga el `Customer` de DB.
