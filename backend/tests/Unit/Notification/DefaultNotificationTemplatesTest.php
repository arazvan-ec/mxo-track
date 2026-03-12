<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Enum\NotificationChannel;
use App\Enum\NotificationTriggerType;
use App\Notification\DefaultNotificationTemplates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultNotificationTemplates::class)]
final class DefaultNotificationTemplatesTest extends TestCase
{
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
    public function it_resolves_default_sms_template_when_custom_is_null(): void
    {
        $result = DefaultNotificationTemplates::resolve(
            NotificationTriggerType::Reminder,
            NotificationChannel::Sms,
            null,
        );
        self::assertStringContainsString('{recipient_name}', $result);
        self::assertStringContainsString('{tracking_url}', $result);
    }

    #[Test]
    public function it_resolves_default_whatsapp_template(): void
    {
        $result = DefaultNotificationTemplates::resolve(
            NotificationTriggerType::Delivered,
            NotificationChannel::WhatsApp,
            null,
        );
        self::assertStringContainsString('{tracking_url}', $result);
    }

    #[Test]
    public function it_has_templates_for_all_trigger_types(): void
    {
        foreach (NotificationTriggerType::cases() as $trigger) {
            foreach (NotificationChannel::cases() as $channel) {
                $result = DefaultNotificationTemplates::resolve($trigger, $channel, null);
                self::assertNotEmpty($result, sprintf('Missing template for %s/%s', $trigger->value, $channel->value));
            }
        }
    }
}
