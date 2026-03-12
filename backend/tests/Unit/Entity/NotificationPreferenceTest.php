<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\CustomerScopedEntityInterface;
use App\Entity\NotificationPreference;
use App\Enum\NotificationChannel;
use App\Enum\NotificationTriggerType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationPreference::class)]
final class NotificationPreferenceTest extends TestCase
{
    #[Test]
    public function it_implements_customer_scoped_entity_interface(): void
    {
        $pref = $this->createPreference();
        self::assertInstanceOf(CustomerScopedEntityInterface::class, $pref);
    }

    #[Test]
    public function it_stores_all_required_fields(): void
    {
        $customer = new Customer('Test Corp');
        $pref = new NotificationPreference(
            customer: $customer,
            triggerType: NotificationTriggerType::Reminder,
            channel: NotificationChannel::Sms,
        );

        self::assertSame($customer, $pref->getCustomer());
        self::assertSame(NotificationTriggerType::Reminder, $pref->getTriggerType());
        self::assertSame(NotificationChannel::Sms, $pref->getChannel());
        self::assertTrue($pref->isEnabled());
        self::assertNull($pref->getMessageTemplate());
        self::assertSame([], $pref->getTimingConfig());
        self::assertInstanceOf(\DateTimeImmutable::class, $pref->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $pref->getUpdatedAt());
    }

    #[Test]
    public function it_accepts_custom_template_and_timing(): void
    {
        $customer = new Customer('Test Corp');
        $pref = new NotificationPreference(
            customer: $customer,
            triggerType: NotificationTriggerType::Reminder,
            channel: NotificationChannel::Sms,
            messageTemplate: 'Custom: {recipient_name}, tu entrega llega mañana.',
            timingConfig: ['hours_before' => 18],
        );

        self::assertSame('Custom: {recipient_name}, tu entrega llega mañana.', $pref->getMessageTemplate());
        self::assertSame(['hours_before' => 18], $pref->getTimingConfig());
    }

    #[Test]
    public function it_can_be_disabled(): void
    {
        $pref = $this->createPreference();
        $pref->setEnabled(false);
        self::assertFalse($pref->isEnabled());
    }

    #[Test]
    public function it_can_update_template(): void
    {
        $pref = $this->createPreference();
        $pref->setMessageTemplate('New template: {tracking_url}');
        self::assertSame('New template: {tracking_url}', $pref->getMessageTemplate());
    }

    private function createPreference(): NotificationPreference
    {
        return new NotificationPreference(
            customer: new Customer('Test Corp'),
            triggerType: NotificationTriggerType::Delivered,
            channel: NotificationChannel::Sms,
        );
    }
}
