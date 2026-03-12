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

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        self::assertSame(NotificationTriggerType::Reminder, NotificationTriggerType::from('reminder'));
        self::assertNull(NotificationTriggerType::tryFrom('invalid'));
    }
}
