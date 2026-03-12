# Plan: Recipient Notifications — Interaction, Configuration & Protections

**Goal:** Implement bidirectional recipient interaction from the tracking page, per-customer notification preferences, and protection gates (dedup, throttle, quiet hours, quotas).

**Spec:** `docs/superpowers/specs/2026-03-12-recipient-notifications-interaction-design.md`
**Prerequisite:** symfony/notifier migration already done (existing Notification classes, TenantAwareSmsTransport, Messenger async).

**Architecture:** Symfony 7.4, PHP 8.4, Doctrine ORM 3.x, PostgreSQL 16, Messenger async
**Testing:** PHPUnit 10+, TDD (red-green-refactor)

---

## File Structure

### New files to create

```
backend/src/Enum/NotificationTriggerType.php
backend/src/Enum/NotificationChannel.php
backend/src/Enum/RecipientActionType.php
backend/src/Enum/NotificationLogStatus.php
backend/src/Entity/NotificationLog.php
backend/src/Entity/RecipientAction.php
backend/src/Entity/NotificationPreference.php
backend/src/Repository/NotificationLogRepository.php
backend/src/Repository/RecipientActionRepository.php
backend/src/Repository/NotificationPreferenceRepository.php
backend/src/Notification/DefaultNotificationTemplates.php
backend/src/Notification/DefaultNotificationTiming.php
backend/src/Notification/NotificationCommand.php
backend/src/Notification/NotificationResolver.php
backend/src/Notification/NotificationDispatcher.php
backend/src/Notification/Gate/RecipientThrottle.php
backend/src/Notification/Gate/CustomerNotificationQuota.php
backend/src/Notification/Gate/QuietHoursGuard.php
backend/src/Notification/Message/SendNotificationMessage.php
backend/src/Notification/Message/SendNotificationHandler.php
backend/src/EventListener/RouteStartedNotificationListener.php
backend/src/EventListener/ShipmentDeliveredNotificationListener.php
backend/src/EventListener/ShipmentExceptionNotificationListener.php
backend/src/EventListener/EtaChangedNotificationListener.php
backend/src/Command/ScheduleNotificationsCommand.php
backend/src/Controller/Api/NotificationPreferenceController.php
backend/src/Dto/NotificationPreferenceDto.php
backend/migrations/Version20260312000200.php
backend/tests/Unit/Enum/NotificationTriggerTypeTest.php
backend/tests/Unit/Enum/NotificationChannelTest.php
backend/tests/Unit/Enum/RecipientActionTypeTest.php
backend/tests/Unit/Enum/NotificationLogStatusTest.php
backend/tests/Unit/Entity/NotificationLogTest.php
backend/tests/Unit/Entity/RecipientActionTest.php
backend/tests/Unit/Entity/NotificationPreferenceTest.php
backend/tests/Unit/Notification/DefaultNotificationTemplatesTest.php
backend/tests/Unit/Notification/DefaultNotificationTimingTest.php
backend/tests/Unit/Notification/NotificationResolverTest.php
backend/tests/Unit/Notification/NotificationDispatcherTest.php
backend/tests/Unit/Notification/Gate/RecipientThrottleTest.php
backend/tests/Unit/Notification/Gate/CustomerNotificationQuotaTest.php
backend/tests/Unit/Notification/Gate/QuietHoursGuardTest.php
backend/tests/Unit/Notification/Message/SendNotificationHandlerTest.php
backend/tests/Unit/Controller/PublicTrackingControllerRecipientActionTest.php
backend/tests/Unit/Command/ScheduleNotificationsCommandTest.php
```

### Existing files to modify

```
backend/src/Entity/Customer.php                          — Add notificationQuota field
backend/src/Controller/PublicTrackingController.php       — Add confirm-presence, reschedule (POST), alternative endpoints
backend/src/Notification/RecipientNotificationService.php — Refactor to use NotificationDispatcher
backend/config/packages/messenger.yaml                    — Add routing for SendNotificationMessage
backend/config/services.yaml                              — Register new services
```

### Files to delete (after migration complete)

```
backend/src/Entity/RecipientNotification.php              — Replaced by NotificationLog
backend/src/Notification/Message/SendRecipientNotificationMessage.php — Replaced by SendNotificationMessage
backend/src/Notification/Message/SendRecipientNotificationHandler.php — Replaced by SendNotificationHandler
```

---

## Tasks

### Task 1: Create enums

Create the 4 new enums following existing enum patterns in the codebase.

**Test first:** `backend/tests/Unit/Enum/NotificationTriggerTypeTest.php`
```php
<?php
declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\NotificationTriggerType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationTriggerType::class)]
class NotificationTriggerTypeTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_cases(): void
    {
        $cases = NotificationTriggerType::cases();
        self::assertCount(6, $cases);
        self::assertSame('reminder', NotificationTriggerType::Reminder->value);
        self::assertSame('presence_check', NotificationTriggerType::PresenceCheck->value);
        self::assertSame('delivered', NotificationTriggerType::Delivered->value);
        self::assertSame('delivery_exception', NotificationTriggerType::DeliveryException->value);
        self::assertSame('eta_change', NotificationTriggerType::EtaChange->value);
        self::assertSame('out_for_delivery', NotificationTriggerType::OutForDelivery->value);
    }
}
```

Similar tests for `NotificationChannel` (2 cases: sms, whatsapp), `RecipientActionType` (6 cases), `NotificationLogStatus` (4 cases: sent, failed, throttled, deferred).

**Implementation files:**

- `backend/src/Enum/NotificationTriggerType.php`
- `backend/src/Enum/NotificationChannel.php`
- `backend/src/Enum/RecipientActionType.php`
- `backend/src/Enum/NotificationLogStatus.php`

**Verify:** Run enum tests → all green
**Commit:** `feat: add notification enums (trigger type, channel, action type, log status)`

---

### Task 2: Create NotificationLog entity + repository

**Test first:** `backend/tests/Unit/Entity/NotificationLogTest.php`
```php
#[CoversClass(NotificationLog::class)]
class NotificationLogTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $customer = $this->createMock(Customer::class);

        $log = new NotificationLog(
            shipment: $shipment,
            customer: $customer,
            channel: NotificationChannel::Sms,
            triggerType: NotificationTriggerType::Reminder,
            recipientPhone: '+34600000001',
            messageContent: 'Test message',
            status: NotificationLogStatus::Sent,
        );

        self::assertSame($shipment, $log->getShipment());
        self::assertSame($customer, $log->getCustomer());
        self::assertSame(NotificationChannel::Sms, $log->getChannel());
        self::assertSame(NotificationTriggerType::Reminder, $log->getTriggerType());
        self::assertSame('+34600000001', $log->getRecipientPhone());
        self::assertSame('Test message', $log->getMessageContent());
        self::assertSame(NotificationLogStatus::Sent, $log->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $log->getCreatedAt());
    }

    #[Test]
    public function it_stores_provider_response(): void
    {
        // ... test providerResponse JSON field
    }
}
```

**Implementation:**

`backend/src/Entity/NotificationLog.php` — Entity with:
- `id` (BIGINT auto-increment)
- `PublicIdTrait` (ULID)
- `shipment` (ManyToOne Shipment)
- `customer` (ManyToOne Customer, denormalized)
- `channel` (NotificationChannel enum)
- `triggerType` (NotificationTriggerType enum)
- `recipientPhone` (string 20)
- `messageContent` (text)
- `status` (NotificationLogStatus enum)
- `providerResponse` (json)
- `createdAt` (datetime_immutable)
- Implements `CustomerScopedEntityInterface`
- Indexes: `idx_notif_dedup` on (shipment_id, trigger_type, channel), `idx_notif_throttle` on (recipient_phone, channel, created_at), `idx_notif_quota` on (customer_id, channel, created_at)

`backend/src/Repository/NotificationLogRepository.php` — Repository with helper methods:
- `hasBeenSent(Shipment, NotificationTriggerType, NotificationChannel): bool`
- `countSentSince(string $phone, NotificationChannel, \DateTimeImmutable): int`
- `lastSentAt(string $phone, NotificationChannel): ?\DateTimeImmutable`
- `countSentByCustomerSince(Customer, NotificationChannel, \DateTimeImmutable): int`

**Verify:** Run entity test → green
**Commit:** `feat: add NotificationLog entity and repository`

---

### Task 3: Create RecipientAction entity + repository

**Test first:** `backend/tests/Unit/Entity/RecipientActionTest.php`

Test creation with required fields, payload storage.

**Implementation:**

`backend/src/Entity/RecipientAction.php` — Entity with:
- `id`, `PublicIdTrait`
- `shipment` (ManyToOne Shipment)
- `actionType` (RecipientActionType enum)
- `payload` (json)
- `createdAt` (datetime_immutable)

`backend/src/Repository/RecipientActionRepository.php`

**Verify:** Test → green
**Commit:** `feat: add RecipientAction entity for tracking page interactions`

---

### Task 4: Create NotificationPreference entity + repository

**Test first:** `backend/tests/Unit/Entity/NotificationPreferenceTest.php`

Test creation, customer scoping, default values.

**Implementation:**

`backend/src/Entity/NotificationPreference.php` — Entity with:
- `id`, `PublicIdTrait`
- `customer` (ManyToOne Customer)
- `triggerType` (NotificationTriggerType enum)
- `channel` (NotificationChannel enum)
- `enabled` (bool, default true)
- `messageTemplate` (text, nullable)
- `timingConfig` (json)
- `createdAt`, `updatedAt` (datetime_immutable)
- Implements `CustomerScopedEntityInterface`
- UniqueConstraint on (customer_id, trigger_type, channel)

`backend/src/Repository/NotificationPreferenceRepository.php`

**Verify:** Test → green
**Commit:** `feat: add NotificationPreference entity for per-customer config`

---

### Task 5: Add notificationQuota to Customer entity

**Test first:** Test in Customer entity test (or create if not exists).

**Implementation:**
Add to `backend/src/Entity/Customer.php`:
```php
#[ORM\Column(nullable: true)]
private ?int $notificationQuota = null; // null = system default (1000/month)
```

With getter/setter.

**Verify:** Test → green
**Commit:** `feat: add notificationQuota field to Customer entity`

---

### Task 6: Create database migration

**Implementation:** `backend/migrations/Version20260312000200.php`

```sql
-- Create notification_log table
CREATE TABLE notification_log (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(26) NOT NULL,
    shipment_id BIGINT NOT NULL REFERENCES shipment(id),
    customer_id BIGINT NOT NULL REFERENCES customer(id),
    channel VARCHAR(255) NOT NULL,
    trigger_type VARCHAR(255) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    message_content TEXT NOT NULL,
    status VARCHAR(255) NOT NULL,
    provider_response JSON NOT NULL DEFAULT '{}',
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
);
CREATE UNIQUE INDEX UNIQ_notification_log_public_id ON notification_log (public_id);
CREATE INDEX idx_notif_dedup ON notification_log (shipment_id, trigger_type, channel);
CREATE INDEX idx_notif_throttle ON notification_log (recipient_phone, channel, created_at);
CREATE INDEX idx_notif_quota ON notification_log (customer_id, channel, created_at);

-- Create recipient_action table
CREATE TABLE recipient_action (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(26) NOT NULL,
    shipment_id BIGINT NOT NULL REFERENCES shipment(id),
    action_type VARCHAR(255) NOT NULL,
    payload JSON NOT NULL DEFAULT '{}',
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
);
CREATE UNIQUE INDEX UNIQ_recipient_action_public_id ON recipient_action (public_id);

-- Create notification_preference table
CREATE TABLE notification_preference (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(26) NOT NULL,
    customer_id BIGINT NOT NULL REFERENCES customer(id),
    trigger_type VARCHAR(255) NOT NULL,
    channel VARCHAR(255) NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    message_template TEXT DEFAULT NULL,
    timing_config JSON NOT NULL DEFAULT '{}',
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
);
CREATE UNIQUE INDEX UNIQ_notification_preference_public_id ON notification_preference (public_id);
CREATE UNIQUE INDEX UNIQ_notif_pref_customer_trigger_channel ON notification_preference (customer_id, trigger_type, channel);

-- Add notification_quota to customer
ALTER TABLE customer ADD COLUMN notification_quota INTEGER DEFAULT NULL;

-- Migrate data from recipient_notification to notification_log (if any data exists)
-- Then drop recipient_notification table
DROP TABLE IF EXISTS recipient_notification;
```

**Verify:** `php bin/console doctrine:migrations:migrate -n` → success
**Commit:** `feat: add migration for notification_log, recipient_action, notification_preference`

---

### Task 7: Create DefaultNotificationTemplates + DefaultNotificationTiming

**Test first:** `backend/tests/Unit/Notification/DefaultNotificationTemplatesTest.php`
```php
#[Test]
public function it_resolves_custom_template_over_default(): void
{
    $custom = 'Custom: {recipient_name}';
    $result = DefaultNotificationTemplates::resolve(
        NotificationTriggerType::Reminder,
        NotificationChannel::Sms,
        $custom,
    );
    self::assertSame($custom, $result);
}

#[Test]
public function it_resolves_default_template_when_custom_is_null(): void
{
    $result = DefaultNotificationTemplates::resolve(
        NotificationTriggerType::Reminder,
        NotificationChannel::Sms,
        null,
    );
    self::assertStringContainsString('{recipient_name}', $result);
    self::assertStringContainsString('{tracking_url}', $result);
}
```

Similar for `DefaultNotificationTiming`:
```php
#[Test]
public function it_returns_defaults_when_custom_is_empty(): void
{
    $result = DefaultNotificationTiming::resolve(
        NotificationTriggerType::Reminder,
        [],
    );
    self::assertSame(['hours_before' => 12], $result);
}
```

**Implementation:**
- `backend/src/Notification/DefaultNotificationTemplates.php` — static `resolve()` with TEMPLATES array
- `backend/src/Notification/DefaultNotificationTiming.php` — static `resolve()` with DEFAULTS array
- `backend/src/Notification/NotificationCommand.php` — simple DTO: shipment, channel, message, timing

**Verify:** Tests → green
**Commit:** `feat: add default notification templates and timing configuration`

---

### Task 8: Create protection gates (RecipientThrottle, CustomerNotificationQuota, QuietHoursGuard)

**Test first:** `backend/tests/Unit/Notification/Gate/RecipientThrottleTest.php`
```php
#[Test]
public function it_allows_when_under_daily_limit(): void
{
    $this->logRepo->method('countSentSince')->willReturn(3);
    $this->logRepo->method('lastSentAt')->willReturn(
        new \DateTimeImmutable('-15 minutes')
    );
    self::assertTrue($this->throttle->canSend('+34600000001', NotificationChannel::Sms));
}

#[Test]
public function it_blocks_when_daily_limit_exceeded(): void
{
    $this->logRepo->method('countSentSince')->willReturn(6);
    self::assertFalse($this->throttle->canSend('+34600000001', NotificationChannel::Sms));
}

#[Test]
public function it_blocks_when_interval_too_short(): void
{
    $this->logRepo->method('countSentSince')->willReturn(1);
    $this->logRepo->method('lastSentAt')->willReturn(
        new \DateTimeImmutable('-5 minutes')
    );
    self::assertFalse($this->throttle->canSend('+34600000001', NotificationChannel::Sms));
}
```

Similar for `CustomerNotificationQuota`:
```php
#[Test]
public function it_allows_when_under_quota(): void { ... }

#[Test]
public function it_blocks_when_quota_exceeded(): void { ... }

#[Test]
public function it_uses_customer_custom_quota(): void { ... }
```

And `QuietHoursGuard`:
```php
#[Test]
public function it_allows_during_business_hours(): void { ... }

#[Test]
public function it_blocks_during_quiet_hours(): void { ... }
```

Note: QuietHoursGuard tests may need a clock interface or testable constructor to control "now".

**Implementation:**
- `backend/src/Notification/Gate/RecipientThrottle.php` — uses `NotificationLogRepository`
- `backend/src/Notification/Gate/CustomerNotificationQuota.php` — uses `NotificationLogRepository` + `Customer.notificationQuota`
- `backend/src/Notification/Gate/QuietHoursGuard.php` — checks 22:00-08:00 system timezone

**Verify:** Gate tests → all green
**Commit:** `feat: add notification protection gates (throttle, quota, quiet hours)`

---

### Task 9: Create NotificationResolver

**Test first:** `backend/tests/Unit/Notification/NotificationResolverTest.php`
```php
#[Test]
public function it_resolves_from_customer_preferences(): void
{
    // Setup: customer has SMS + WhatsApp preferences for reminder
    // Assert: returns 2 NotificationCommands with rendered messages
}

#[Test]
public function it_falls_back_to_sms_default_when_no_preferences(): void
{
    // Setup: no NotificationPreference for this customer
    // Assert: returns 1 NotificationCommand for SMS with default template
}

#[Test]
public function it_skips_disabled_preferences(): void
{
    // Setup: preference exists but enabled=false
    // Assert: returns empty array
}

#[Test]
public function it_renders_template_placeholders(): void
{
    // Assert: {recipient_name}, {tracking_url} replaced with actual values
}
```

**Implementation:** `backend/src/Notification/NotificationResolver.php`
- Constructor: `NotificationPreferenceRepository`, `string $appBaseUrl`
- `resolve(Shipment, NotificationTriggerType): NotificationCommand[]`
- Private `renderTemplate(string $template, Shipment): string` — replaces placeholders

**Verify:** Tests → green
**Commit:** `feat: add NotificationResolver for per-customer notification resolution`

---

### Task 10: Create NotificationDispatcher

**Test first:** `backend/tests/Unit/Notification/NotificationDispatcherTest.php`
```php
#[Test]
public function it_dispatches_messages_for_each_command(): void
{
    // Setup: resolver returns 2 commands
    // Assert: bus->dispatch called 2 times with SendNotificationMessage
}

#[Test]
public function it_applies_delay_stamp_for_delayed_timing(): void
{
    // Setup: command timing has delay_minutes=5
    // Assert: dispatch called with DelayStamp(300000)
}

#[Test]
public function it_dispatches_without_delay_for_immediate(): void
{
    // Setup: command timing is empty (immediate)
    // Assert: dispatch called without DelayStamp
}
```

**Implementation:** `backend/src/Notification/NotificationDispatcher.php`
- Constructor: `MessageBusInterface`, `NotificationResolver`
- `dispatchForShipment(Shipment, NotificationTriggerType): void`

**Verify:** Tests → green
**Commit:** `feat: add NotificationDispatcher for async message dispatch`

---

### Task 11: Create SendNotificationMessage + SendNotificationHandler

**Test first:** `backend/tests/Unit/Notification/Message/SendNotificationHandlerTest.php`
```php
#[Test]
public function it_sends_via_provider_and_logs_success(): void
{
    // Setup: shipment exists, all gates pass
    // Assert: TenantAwareSmsTransport.send() called, NotificationLog persisted with status=sent
}

#[Test]
public function it_skips_if_already_sent_dedup(): void
{
    // Setup: NotificationLog exists with same (shipment, trigger, channel, status=sent)
    // Assert: no provider call, no new log
}

#[Test]
public function it_defers_during_quiet_hours(): void
{
    // Setup: QuietHoursGuard.canSendNow() returns false
    // Assert: log with status=deferred, message re-dispatched
}

#[Test]
public function it_throttles_when_recipient_limit_exceeded(): void
{
    // Setup: RecipientThrottle.canSend() returns false
    // Assert: log with status=throttled, no provider call
}

#[Test]
public function it_throttles_when_customer_quota_exceeded(): void
{
    // Setup: CustomerNotificationQuota.canSend() returns false
    // Assert: log with status=throttled
}

#[Test]
public function it_logs_failure_and_rethrows_on_provider_error(): void
{
    // Setup: provider throws exception
    // Assert: log with status=failed, exception re-thrown for Messenger retry
}
```

**Implementation:**

`backend/src/Notification/Message/SendNotificationMessage.php`:
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

`backend/src/Notification/Message/SendNotificationHandler.php`:
```php
#[AsMessageHandler]
final class SendNotificationHandler
{
    // Constructor: ShipmentRepository, NotificationLogRepository, TenantAwareSmsTransport,
    //             RecipientThrottle, CustomerNotificationQuota, QuietHoursGuard,
    //             EntityManagerInterface, MessageBusInterface

    public function __invoke(SendNotificationMessage $message): void
    {
        // 1. Load shipment (return if not found)
        // 2. Gate 1: Dedup check
        // 3. Gate 2: Quiet hours
        // 4. Gate 3: Recipient throttle
        // 5. Gate 4: Customer quota
        // 6. Send via TenantAwareSmsTransport
        // 7. Log result
    }
}
```

**Verify:** Tests → green
**Commit:** `feat: add SendNotificationMessage and handler with protection gates`

---

### Task 12: Create event listeners

**Test first:** Tests for each listener verifying they call `NotificationDispatcher.dispatchForShipment()` with correct trigger type.

Note: These listeners will listen to **existing** domain events. Check if the events (`RouteStartedEvent`, `ShipmentDeliveredEvent`, etc.) already exist. If some don't exist, they need to be created or mapped to existing events.

The existing `RouteActivatedNotificationSubscriber` listens to a `RouteStarted` event — we need to check the exact event class name and replace this subscriber with the new listener.

**Implementation:**

- `backend/src/EventListener/RouteStartedNotificationListener.php` — listens to RouteStarted/RouteActivated event, dispatches `out_for_delivery` for each shipment in route
- `backend/src/EventListener/ShipmentDeliveredNotificationListener.php` — dispatches `delivered`
- `backend/src/EventListener/ShipmentExceptionNotificationListener.php` — dispatches `delivery_exception`
- `backend/src/EventListener/EtaChangedNotificationListener.php` — dispatches `eta_change`

**Verify:** Tests → green
**Commit:** `feat: add notification event listeners for route/shipment events`

---

### Task 13: Create ScheduleNotificationsCommand

**Test first:** `backend/tests/Unit/Command/ScheduleNotificationsCommandTest.php`
```php
#[Test]
public function it_dispatches_reminders_for_tomorrows_shipments(): void
{
    // Setup: 2 shipments scheduled for tomorrow, no reminder sent yet
    // Assert: dispatcher called 2 times with Reminder trigger
}

#[Test]
public function it_skips_shipments_with_reminder_already_sent(): void
{
    // Setup: shipment has NotificationLog with reminder+sent
    // Assert: dispatcher not called
}

#[Test]
public function it_dispatches_presence_check_for_nearby_eta(): void
{
    // Setup: shipment with ETA in 40 minutes, no presence_check sent
    // Assert: dispatcher called with PresenceCheck trigger
}
```

**Implementation:** `backend/src/Command/ScheduleNotificationsCommand.php`
- Command name: `app:notifications:schedule`
- Queries shipments for tomorrow (reminder) and shipments with ETA within 45 min (presence_check)
- Checks NotificationLogRepository for existing sent notifications
- Dispatches via NotificationDispatcher

**Verify:** Tests → green
**Commit:** `feat: add scheduled notifications command (reminder + presence check)`

---

### Task 14: Add recipient action endpoints to PublicTrackingController

**Test first:** `backend/tests/Unit/Controller/PublicTrackingControllerRecipientActionTest.php`

Test that POST to `/track/{token}/confirm-presence` creates a RecipientAction with correct type and payload.

**Implementation:** Add to `backend/src/Controller/PublicTrackingController.php`:

```php
#[Route('/track/{trackingToken}/confirm-presence', name: 'public_tracking_confirm_presence', methods: ['POST'])]
public function confirmPresence(string $trackingToken, Request $request): Response
{
    $shipment = $this->resolveShipment($trackingToken);
    $confirmed = $request->request->getBoolean('confirmed');

    $action = new RecipientAction(
        shipment: $shipment,
        actionType: $confirmed ? RecipientActionType::PresenceConfirmed : RecipientActionType::PresenceDenied,
        payload: ['confirmed' => $confirmed],
    );
    $this->em->persist($action);
    $this->em->flush();

    if (!$confirmed) {
        return $this->render('tracking/reschedule_options.html.twig', [
            'shipment' => $shipment,
        ]);
    }
    return $this->render('tracking/presence_confirmed.html.twig', [
        'shipment' => $shipment,
    ]);
}

#[Route('/track/{trackingToken}/alternative', name: 'public_tracking_alternative', methods: ['POST'])]
public function alternative(string $trackingToken, Request $request): Response
{
    $shipment = $this->resolveShipment($trackingToken);
    $option = $request->request->getString('option'); // porteria, vecino
    $instructions = $request->request->getString('instructions', '');

    $action = new RecipientAction(
        shipment: $shipment,
        actionType: RecipientActionType::AlternativeRequested,
        payload: ['option' => $option, 'instructions' => $instructions],
    );
    $this->em->persist($action);
    $this->em->flush();

    return $this->render('tracking/alternative_confirmed.html.twig', [
        'shipment' => $shipment,
        'option' => $option,
    ]);
}
```

Also add `tracking_page_viewed` action recording in the existing `track()` method.

**Twig templates:** Create minimal templates for the new views:
- `templates/tracking/presence_confirmed.html.twig`
- `templates/tracking/reschedule_options.html.twig`
- `templates/tracking/alternative_confirmed.html.twig`

**Verify:** Tests → green
**Commit:** `feat: add recipient action endpoints to tracking page`

---

### Task 15: Create NotificationPreference API controller

**Test first:** Test CRUD endpoints for notification preferences.

**Implementation:**

`backend/src/Dto/NotificationPreferenceDto.php`:
```php
final class NotificationPreferenceDto
{
    #[Assert\NotBlank]
    public string $triggerType;

    #[Assert\NotBlank]
    public string $channel;

    public bool $enabled = true;
    public ?string $messageTemplate = null;
    public array $timingConfig = [];

    public static function fromArray(array $data): self { ... }
}
```

`backend/src/Controller/Api/NotificationPreferenceController.php`:
- `GET /api/notification-preferences` — list preferences for current customer
- `POST /api/notification-preferences` — create/upsert preference
- `DELETE /api/notification-preferences/{publicId}` — delete preference
- `GET /api/notification-logs` — list notification logs for current customer (paginated)

All endpoints require `ROLE_CUSTOMER` or higher.

**Verify:** Tests → green
**Commit:** `feat: add NotificationPreference API endpoints`

---

### Task 16: Refactor RecipientNotificationService to use NotificationDispatcher

**Test first:** Update `backend/tests/Unit/Notification/RecipientNotificationServiceTest.php`

The service should now delegate to `NotificationDispatcher` instead of dispatching `SendRecipientNotificationMessage` directly.

**Implementation:** Modify `backend/src/Notification/RecipientNotificationService.php`:
- Replace `MessageBusInterface` dependency with `NotificationDispatcher`
- `notifyRouteStarted()` → loop shipments, call `dispatcher->dispatchForShipment(shipment, OutForDelivery)`
- `notifyApproaching()` → call `dispatcher->dispatchForShipment(shipment, PresenceCheck)`
- `notifyDelivered()` → call `dispatcher->dispatchForShipment(shipment, Delivered)`

**Verify:** Tests → green
**Commit:** `refactor: RecipientNotificationService delegates to NotificationDispatcher`

---

### Task 17: Update configuration files

**Implementation:**

`backend/config/packages/messenger.yaml` — add routing:
```yaml
routing:
    'App\Notification\Message\SendNotificationMessage': async
    # Remove or keep old routing for backwards compat during transition
```

`backend/config/services.yaml` — register new services if not auto-wired.

**Verify:** `php bin/console about` → no errors
**Commit:** `chore: update messenger routing and service configuration`

---

### Task 18: Clean up old notification code

Remove replaced files:
- `backend/src/Entity/RecipientNotification.php`
- `backend/src/Repository/RecipientNotificationRepository.php` (if exists)
- `backend/src/Notification/Message/SendRecipientNotificationMessage.php`
- `backend/src/Notification/Message/SendRecipientNotificationHandler.php`

Update references:
- Remove old `SendRecipientNotificationMessage` routing from `messenger.yaml`
- Update `ApproachingNotificationSubscriber` to use new system (or remove if event listeners cover it)
- Update `RouteActivatedNotificationSubscriber` similarly
- Update tests that reference removed classes

**Verify:** Full test suite → green, `php bin/console about` → no errors
**Commit:** `refactor: remove old notification classes replaced by new system`

---

### Task 19: Run full verification

- [ ] `make lint` → clean
- [ ] `php vendor/bin/phpunit` → all tests pass
- [ ] `php bin/console doctrine:schema:validate` → schema in sync
- [ ] `php bin/console about` → Symfony healthy

**Commit:** Any final fixes needed
**Push:** `git push -u origin claude/deploy-providers-railway-sTixQ`

---

## Execution Order Summary

| # | Task | Dependencies |
|---|------|-------------|
| 1 | Enums | None |
| 2 | NotificationLog entity | Task 1 |
| 3 | RecipientAction entity | Task 1 |
| 4 | NotificationPreference entity | Task 1 |
| 5 | Customer.notificationQuota | None |
| 6 | Database migration | Tasks 2-5 |
| 7 | DefaultTemplates + DefaultTiming | Task 1 |
| 8 | Protection gates | Tasks 1, 2 |
| 9 | NotificationResolver | Tasks 4, 7 |
| 10 | NotificationDispatcher | Tasks 9, 1 |
| 11 | SendNotificationHandler | Tasks 2, 8, 10 |
| 12 | Event listeners | Task 10 |
| 13 | ScheduleNotificationsCommand | Tasks 2, 10 |
| 14 | Tracking page endpoints | Task 3 |
| 15 | NotificationPreference API | Task 4 |
| 16 | Refactor RecipientNotificationService | Task 10 |
| 17 | Config updates | Tasks 11, 16 |
| 18 | Cleanup old code | Tasks 16, 17 |
| 19 | Full verification | All |
