# Plan: Migración a symfony/notifier + Messenger multi-tenant

**Goal:** Reemplazar el sistema custom de notificaciones por symfony/notifier con Messenger async y multi-tenant per-customer.
**Spec:** `docs/superpowers/specs/2026-03-12-symfony-notifier-design.md`
**Architecture:** Symfony 7.4 LTS, PHP 8.4, Doctrine ORM 3.x, PostgreSQL
**Branch:** `claude/phase-3-symfony-notifier-Ka95W`

---

## File Structure

### Files to create
```
backend/src/Notification/DeliveryApproachingNotification.php
backend/src/Notification/DeliveryCompletedNotification.php
backend/src/Notification/RatingRequestNotification.php
backend/src/Notification/DeliverySlotConfirmedNotification.php
backend/src/Notification/RescheduleConfirmedNotification.php
backend/src/Notification/Transport/TenantAwareSmsTransport.php
backend/src/Notification/Transport/NullSmsTransport.php
backend/src/Notification/Transport/CustomerStamp.php
backend/src/Notification/Message/SendRecipientNotificationMessage.php
backend/src/Notification/Message/SendRecipientNotificationHandler.php
backend/src/Provider/Enum/SmsNotifierProviderType.php
backend/src/Provider/Factory/TwilioSmsTransportFactory.php
backend/src/Provider/Factory/NullSmsTransportFactory.php
backend/config/packages/notifier.yaml
```

### Files to modify
```
backend/composer.json                          # Add symfony/notifier, symfony/twilio-notifier
backend/src/Provider/ServiceType.php           # Add SmsNotifier case
backend/src/Notification/RecipientNotificationService.php  # Refactor to dispatch bus messages
backend/config/packages/messenger.yaml         # Add notification routing
backend/config/services.yaml                   # Replace old wiring with new
backend/.env                                   # Add DEFAULT_SMS_NOTIFIER
```

### Files to delete (19)
```
backend/src/Notification/Provider/SmsProviderInterface.php
backend/src/Notification/Provider/WhatsAppProviderInterface.php
backend/src/Notification/Provider/TwilioSmsProvider.php
backend/src/Notification/Provider/TwilioWhatsAppProvider.php
backend/src/Notification/Provider/NullSmsProvider.php
backend/src/Notification/Provider/NullWhatsAppProvider.php
backend/src/Notification/Channel/NotificationChannelInterface.php
backend/src/Notification/Channel/SmsChannel.php
backend/src/Notification/Channel/WhatsAppChannel.php
backend/src/Notification/Channel/LogChannel.php
backend/src/Notification/Template/NotificationTemplate.php
backend/src/Notification/Template/PreDeliveryTemplate.php
backend/src/Notification/Template/DeliveryCompletedTemplate.php
backend/src/Notification/Template/RatingRequestTemplate.php
backend/src/Notification/Template/DeliverySlotConfirmationTemplate.php
backend/src/Notification/Template/RescheduleConfirmationTemplate.php
backend/src/Notification/Message/PreDeliveryNotificationMessage.php
backend/src/Notification/Message/PreDeliveryNotificationHandler.php
backend/src/Notification/RecipientPreference.php
```

---

## Tasks

### Task 1: Install dependencies and configure notifier

- [ ] 1.1 Run `composer require symfony/notifier symfony/twilio-notifier` in backend/
- [ ] 1.2 Create `backend/config/packages/notifier.yaml`:
```yaml
framework:
    notifier:
        texter_transports:
            default: 'null://null'
```
- [ ] 1.3 Add `DEFAULT_SMS_NOTIFIER=null` to `backend/.env`
- [ ] 1.4 Add `sms_notifier` default to `backend/config/services.yaml` under ProviderFactoryRegistry:
```yaml
  App\Provider\ProviderFactoryRegistry:
    arguments:
      $factories: !tagged_iterator app.provider_factory
      $defaults:
        route_optimizer: '%env(DEFAULT_ROUTE_OPTIMIZER)%'
        routing_engine: '%env(DEFAULT_ROUTING_ENGINE)%'
        gps_provider: '%env(DEFAULT_GPS_PROVIDER)%'
        realtime_publisher: '%env(DEFAULT_REALTIME_PUBLISHER)%'
        sms_notifier: '%env(DEFAULT_SMS_NOTIFIER)%'
```
- [ ] 1.5 Add `SmsNotifier` case to `ServiceType` enum:
```php
case SmsNotifier = 'sms_notifier';
```
- [ ] 1.6 Create `SmsNotifierProviderType` enum in `backend/src/Provider/Enum/SmsNotifierProviderType.php`:
```php
<?php
declare(strict_types=1);
namespace App\Provider\Enum;

enum SmsNotifierProviderType: string
{
    case Twilio = 'twilio';
    case Null = 'null';
}
```
- [ ] 1.7 Verify: `php bin/console about` — no errors
- [ ] 1.8 Commit: "feat: install symfony/notifier and configure base setup"

### Task 2: Create NullSmsTransport and NullSmsTransportFactory (TDD)

- [ ] 2.1 Write test `backend/tests/Unit/Notification/Transport/NullSmsTransportTest.php`:
  - Test: `send()` returns `SentMessage` without making HTTP calls
  - Test: `supports()` returns true for `SmsMessage`, false for others
  - Test: `__toString()` returns `'null'`
- [ ] 2.2 Verify test fails (RED)
- [ ] 2.3 Create `backend/src/Notification/Transport/NullSmsTransport.php`:
```php
<?php
declare(strict_types=1);
namespace App\Notification\Transport;

use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Psr\Log\LoggerInterface;

final class NullSmsTransport implements TransportInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function send(MessageInterface $message): SentMessage
    {
        $this->logger?->info('NullSmsTransport: would send SMS to {phone}: {text}', [
            'phone' => $message instanceof SmsMessage ? $message->getPhone() : 'unknown',
            'text' => $message instanceof SmsMessage ? $message->getSubject() : '',
        ]);

        return new SentMessage($message, (string) $this);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    public function __toString(): string
    {
        return 'null';
    }
}
```
- [ ] 2.4 Write test `backend/tests/Unit/Provider/Factory/NullSmsTransportFactoryTest.php`:
  - Test: `create([])` returns `NullSmsTransport`
  - Test: `getProviderType()` returns `'null'`
  - Test: `getServiceType()` returns `ServiceType::SmsNotifier`
- [ ] 2.5 Verify tests fail (RED)
- [ ] 2.6 Create `backend/src/Provider/Factory/NullSmsTransportFactory.php`:
```php
<?php
declare(strict_types=1);
namespace App\Provider\Factory;

use App\Notification\Transport\NullSmsTransport;
use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use Psr\Log\LoggerInterface;

final class NullSmsTransportFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function create(array $config): object
    {
        return new NullSmsTransport($this->logger);
    }

    public function getProviderType(): string
    {
        return 'null';
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::SmsNotifier;
    }
}
```
- [ ] 2.7 Verify tests pass (GREEN)
- [ ] 2.8 Commit: "feat: add NullSmsTransport and factory for dev environment"

### Task 3: Create TwilioSmsTransportFactory (TDD)

- [ ] 3.1 Write test `backend/tests/Unit/Provider/Factory/TwilioSmsTransportFactoryTest.php`:
  - Test: `create(config)` returns a transport object (can't test actual Twilio without credentials)
  - Test: `getProviderType()` returns `'twilio'`
  - Test: `getServiceType()` returns `ServiceType::SmsNotifier`
  - Test: `create()` with empty config throws or returns with defaults
- [ ] 3.2 Verify tests fail (RED)
- [ ] 3.3 Create `backend/src/Provider/Factory/TwilioSmsTransportFactory.php`:
```php
<?php
declare(strict_types=1);
namespace App\Provider\Factory;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

final class TwilioSmsTransportFactory implements ProviderFactoryInterface
{
    public function create(array $config): object
    {
        $dsn = new Dsn(sprintf(
            'twilio://%s:%s@default?from=%s',
            $config['account_sid'] ?? '',
            $config['auth_token'] ?? '',
            urlencode($config['from_number'] ?? ''),
        ));

        return (new TwilioTransportFactory())->create($dsn);
    }

    public function getProviderType(): string
    {
        return 'twilio';
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::SmsNotifier;
    }
}
```
- [ ] 3.4 Verify tests pass (GREEN)
- [ ] 3.5 Commit: "feat: add TwilioSmsTransportFactory for multi-tenant SMS"

### Task 4: Create TenantAwareSmsTransport + CustomerStamp (TDD)

- [ ] 4.1 Create `backend/src/Notification/Transport/CustomerStamp.php`:
```php
<?php
declare(strict_types=1);
namespace App\Notification\Transport;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class CustomerStamp implements StampInterface
{
    public function __construct(
        public readonly ?int $customerId,
    ) {}
}
```
- [ ] 4.2 Write test `backend/tests/Unit/Notification/Transport/TenantAwareSmsTransportTest.php`:
  - Test: `send()` without customer uses default transport
  - Test: `send()` with customer resolves via ProviderResolver and uses tenant transport
  - Test: `send()` with customer but no CustomerIntegration falls back to default
  - Test: `supports()` delegates correctly
  - Test: `setCustomer(null)` resets to default behavior
- [ ] 4.3 Verify tests fail (RED)
- [ ] 4.4 Create `backend/src/Notification/Transport/TenantAwareSmsTransport.php`:
```php
<?php
declare(strict_types=1);
namespace App\Notification\Transport;

use App\Entity\Customer;
use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

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
- [ ] 4.5 Verify tests pass (GREEN)
- [ ] 4.6 Commit: "feat: add TenantAwareSmsTransport with CustomerStamp for multi-tenant SMS"

### Task 5: Create Notification classes (TDD)

- [ ] 5.1 Write test `backend/tests/Unit/Notification/DeliveryApproachingNotificationTest.php`:
  - Test: `asSmsMessage()` returns SmsMessage with correct text containing recipientName, driverName, trackingUrl
  - Test: `getTemplateName()` returns `'pre_delivery_notification'`
  - Test: `getChannels()` returns `['sms']`
- [ ] 5.2 Verify tests fail (RED)
- [ ] 5.3 Create `backend/src/Notification/DeliveryApproachingNotification.php`:
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
                'Hola %s, su entrega llega en ~%d minutos. Conductor: %s. Seguimiento: %s',
                $this->recipientName,
                max(1, (int) round(($this->estimatedArrival->getTimestamp() - time()) / 60)),
                $this->driverName,
                $this->trackingUrl,
            ),
        );
    }

    public function getTemplateName(): string
    {
        return 'pre_delivery_notification';
    }

    public function getChannels(object $recipient): array
    {
        return ['sms'];
    }
}
```
- [ ] 5.4 Verify tests pass (GREEN)
- [ ] 5.5 Write test `backend/tests/Unit/Notification/DeliveryCompletedNotificationTest.php`:
  - Test: `asSmsMessage()` contains recipientName, shipmentReference, ratingUrl
  - Test: `getTemplateName()` returns `'delivery_completed'`
- [ ] 5.6 Verify tests fail (RED)
- [ ] 5.7 Create `backend/src/Notification/DeliveryCompletedNotification.php`:
```php
<?php
declare(strict_types=1);
namespace App\Notification;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

final class DeliveryCompletedNotification extends Notification implements SmsNotificationInterface
{
    public function __construct(
        private readonly string $recipientName,
        private readonly string $shipmentReference,
        private readonly string $ratingUrl,
    ) {
        parent::__construct('Su envío ha sido entregado');
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, ?string $transport = null): ?SmsMessage
    {
        return new SmsMessage(
            $recipient->getPhone(),
            sprintf(
                'Hola %s, su envío %s ha sido entregado. ¿Cómo fue su experiencia? Califique aquí: %s',
                $this->recipientName,
                $this->shipmentReference,
                $this->ratingUrl,
            ),
        );
    }

    public function getTemplateName(): string
    {
        return 'delivery_completed';
    }

    public function getChannels(object $recipient): array
    {
        return ['sms'];
    }
}
```
- [ ] 5.8 Verify tests pass (GREEN)
- [ ] 5.9 Create remaining 3 Notification classes (RatingRequest, DeliverySlotConfirmed, RescheduleConfirmed) following same pattern with tests
- [ ] 5.10 Verify all notification tests pass
- [ ] 5.11 Commit: "feat: add Symfony Notification classes replacing custom templates"

### Task 6: Create Messenger Message and Handler (TDD)

- [ ] 6.1 Create `backend/src/Notification/Message/SendRecipientNotificationMessage.php`:
```php
<?php
declare(strict_types=1);
namespace App\Notification\Message;

final readonly class SendRecipientNotificationMessage
{
    public function __construct(
        public int $routeStopId,
        public string $notificationType,
        public ?int $customerId = null,
    ) {}
}
```
- [ ] 6.2 Write test `backend/tests/Unit/Notification/Message/SendRecipientNotificationHandlerTest.php`:
  - Test: handler with valid routeStopId and 'approaching' type sends SMS notification
  - Test: handler with null shipment on stop does nothing
  - Test: handler with no phone number does nothing
  - Test: handler records RecipientNotification entity on success
  - Test: handler records failed RecipientNotification and re-throws on error
  - Test: handler sets customer on transport when customerId is provided
  - Test: handler cleans up customer on transport after send (even on error)
- [ ] 6.3 Verify tests fail (RED)
- [ ] 6.4 Create `backend/src/Notification/Message/SendRecipientNotificationHandler.php`:
```php
<?php
declare(strict_types=1);
namespace App\Notification\Message;

use App\Entity\Customer;
use App\Entity\RecipientNotification;
use App\Entity\RouteStop;
use App\Notification\DeliveryApproachingNotification;
use App\Notification\DeliveryCompletedNotification;
use App\Notification\DeliverySlotConfirmedNotification;
use App\Notification\RatingRequestNotification;
use App\Notification\RescheduleConfirmedNotification;
use App\Notification\Transport\TenantAwareSmsTransport;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

#[AsMessageHandler]
final class SendRecipientNotificationHandler
{
    public function __construct(
        private readonly NotifierInterface $notifier,
        private readonly TenantAwareSmsTransport $transport,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $appBaseUrl = '',
    ) {}

    public function __invoke(SendRecipientNotificationMessage $message): void
    {
        $stop = $this->em->find(RouteStop::class, $message->routeStopId);
        if ($stop === null) {
            $this->logger->warning('SendRecipientNotificationHandler: stop {id} not found', [
                'id' => $message->routeStopId,
            ]);
            return;
        }

        $shipment = $stop->getShipment();
        if ($shipment === null) {
            return;
        }

        $phone = $shipment->getRecipientPhone() ?? $stop->getRecipientPhone();
        if ($phone === null || $phone === '') {
            return;
        }

        // Set tenant for multi-tenant transport
        if ($message->customerId !== null) {
            $customer = $this->em->find(Customer::class, $message->customerId);
            $this->transport->setCustomer($customer);
        }

        try {
            $recipientName = $shipment->getRecipientName() ?? $stop->getRecipientName() ?? 'Cliente';
            $notification = $this->buildNotification($message->notificationType, $stop, $shipment, $recipientName);

            if ($notification === null) {
                $this->logger->warning('Unknown notification type: {type}', [
                    'type' => $message->notificationType,
                ]);
                return;
            }

            $recipient = new Recipient('', $phone);
            $this->notifier->send($notification, $recipient);

            $this->recordNotification($shipment, $phone, $notification->getTemplateName(), true);
        } catch (\Throwable $e) {
            $this->recordNotification($shipment, $phone, $message->notificationType, false, $e->getMessage());
            throw $e;
        } finally {
            $this->transport->setCustomer(null);
        }
    }

    // buildNotification() method builds the correct Notification based on type
    // recordNotification() method creates/persists RecipientNotification entity
}
```
- [ ] 6.5 Verify tests pass (GREEN)
- [ ] 6.6 Add messenger routing in `backend/config/packages/messenger.yaml`:
```yaml
        routing:
            'App\Notification\Message\SendRecipientNotificationMessage': async
```
- [ ] 6.7 Commit: "feat: add SendRecipientNotificationMessage handler with multi-tenant support"

### Task 7: Refactor RecipientNotificationService to dispatch bus messages (TDD)

- [ ] 7.1 Write test `backend/tests/Unit/Notification/RecipientNotificationServiceTest.php`:
  - Test: `notifyRouteStarted()` dispatches one `SendRecipientNotificationMessage` per non-origin stop with shipment+phone
  - Test: `notifyRouteStarted()` skips stops without phone numbers
  - Test: `notifyRouteStarted()` skips origin stops
  - Test: `notifyApproaching()` dispatches message with type 'approaching'
  - Test: `notifyDelivered()` dispatches message with type 'delivered'
  - Test: `notifyApproaching()` does nothing when shipment is null
- [ ] 7.2 Verify tests fail (RED)
- [ ] 7.3 Refactor `backend/src/Notification/RecipientNotificationService.php`:
  - Remove dependency on `iterable $channels`
  - Add dependency on `MessageBusInterface $bus`
  - Keep `EtaService`, `EntityManagerInterface`, `LoggerInterface`, `$appBaseUrl`
  - `notifyRouteStarted()`: dispatch `SendRecipientNotificationMessage` per stop
  - `notifyApproaching()`: dispatch `SendRecipientNotificationMessage`
  - `notifyDelivered()`: dispatch `SendRecipientNotificationMessage`
  - Remove `notify()` and `sendAndRecord()` methods (moved to handler)
  - Keep `buildTrackingUrl()` and `buildRatingUrl()` as they may be needed elsewhere
- [ ] 7.4 Verify tests pass (GREEN)
- [ ] 7.5 Verify existing tests still pass: `php vendor/bin/phpunit`
- [ ] 7.6 Commit: "refactor: RecipientNotificationService dispatches to Messenger bus"

### Task 8: Update services.yaml wiring

- [ ] 8.1 Remove from `backend/config/services.yaml`:
  - `App\Notification\Channel\SmsChannel` tag
  - `App\Notification\Channel\WhatsAppChannel` tag
  - `App\Notification\Channel\LogChannel` tag
  - `App\Notification\RecipientNotificationService` `$channels` argument
  - `App\Notification\Provider\SmsProviderInterface` alias
  - `App\Notification\Provider\WhatsAppProviderInterface` alias
  - `App\Notification\Provider\TwilioSmsProvider` config
  - `App\Notification\Provider\TwilioWhatsAppProvider` config
- [ ] 8.2 Add to `backend/config/services.yaml`:
```yaml
  # --- Recipient Notification System (symfony/notifier) ---

  App\Notification\Transport\NullSmsTransport: ~

  App\Notification\Transport\TenantAwareSmsTransport:
    arguments:
      $defaultTransport: '@App\Notification\Transport\NullSmsTransport'

  App\Notification\RecipientNotificationService:
    arguments:
      $appBaseUrl: '%env(string:default::APP_BASE_URL)%'

  App\Notification\Message\SendRecipientNotificationHandler:
    arguments:
      $appBaseUrl: '%env(string:default::APP_BASE_URL)%'
```
- [ ] 8.3 Add `sms_notifier` to ProviderFactoryRegistry defaults
- [ ] 8.4 Verify: `php bin/console about` — no errors
- [ ] 8.5 Verify: `php vendor/bin/phpunit` — all tests pass
- [ ] 8.6 Commit: "refactor: update services.yaml for symfony/notifier wiring"

### Task 9: Delete old files

- [ ] 9.1 Delete all 19 files listed in "Files to delete" section
- [ ] 9.2 Remove empty directories:
  - `backend/src/Notification/Provider/` (if empty)
  - `backend/src/Notification/Channel/` (if empty)
  - `backend/src/Notification/Template/` (if empty)
- [ ] 9.3 Verify: `php bin/console about` — no errors
- [ ] 9.4 Verify: `php vendor/bin/phpunit` — all tests pass
- [ ] 9.5 Verify: `make lint` — no lint errors
- [ ] 9.6 Commit: "refactor: remove custom notification channels, providers, and templates"

### Task 10: Update documentation

- [ ] 10.1 Update `docs/knowledge/notifications.md` with new architecture
- [ ] 10.2 Update `docs/FEATURES.md` if notification section exists
- [ ] 10.3 Commit: "docs: update notification documentation for symfony/notifier migration"

### Task 11: Final verification

- [ ] 11.1 Run full test suite: `php vendor/bin/phpunit` — 0 failures, 0 errors
- [ ] 11.2 Run lint: `make lint` — clean
- [ ] 11.3 Verify all new test files are green
- [ ] 11.4 Push to branch: `git push -u origin claude/phase-3-symfony-notifier-Ka95W`

---

## Expected test count

- NullSmsTransportTest: 3 tests
- NullSmsTransportFactoryTest: 3 tests
- TwilioSmsTransportFactoryTest: 3 tests
- TenantAwareSmsTransportTest: 5 tests
- DeliveryApproachingNotificationTest: 3 tests
- DeliveryCompletedNotificationTest: 2 tests
- RatingRequestNotificationTest: 2 tests
- DeliverySlotConfirmedNotificationTest: 2 tests
- RescheduleConfirmedNotificationTest: 2 tests
- SendRecipientNotificationHandlerTest: 7 tests
- RecipientNotificationServiceTest: 6 tests

**Total new tests: ~38**
**Expected total: ~334 + 38 = ~372 tests**
