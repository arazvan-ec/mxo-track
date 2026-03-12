<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Enum\NotificationTriggerType;
use App\Notification\DefaultNotificationTiming;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultNotificationTiming::class)]
final class DefaultNotificationTimingTest extends TestCase
{
    #[Test]
    public function it_returns_defaults_when_custom_is_empty(): void
    {
        $result = DefaultNotificationTiming::resolve(NotificationTriggerType::Reminder, []);
        self::assertSame(['hours_before' => 12], $result);
    }

    #[Test]
    public function it_returns_custom_config_when_provided(): void
    {
        $custom = ['hours_before' => 18];
        $result = DefaultNotificationTiming::resolve(NotificationTriggerType::Reminder, $custom);
        self::assertSame($custom, $result);
    }

    #[Test]
    public function it_has_defaults_for_all_trigger_types(): void
    {
        foreach (NotificationTriggerType::cases() as $trigger) {
            $result = DefaultNotificationTiming::resolve($trigger, []);
            self::assertIsArray($result, sprintf('Missing timing for %s', $trigger->value));
        }
    }

    #[Test]
    public function it_returns_expected_presence_check_timing(): void
    {
        $result = DefaultNotificationTiming::resolve(NotificationTriggerType::PresenceCheck, []);
        self::assertSame(['minutes_before' => 30], $result);
    }

    #[Test]
    public function it_returns_empty_array_for_out_for_delivery(): void
    {
        $result = DefaultNotificationTiming::resolve(NotificationTriggerType::OutForDelivery, []);
        self::assertSame([], $result);
    }
}
