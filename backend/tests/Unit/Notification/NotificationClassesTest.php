<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Notification\DeliveryApproachingNotification;
use App\Notification\DeliveryCompletedNotification;
use App\Notification\DeliverySlotConfirmedNotification;
use App\Notification\RatingRequestNotification;
use App\Notification\RescheduleConfirmedNotification;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

final class NotificationClassesTest extends TestCase
{
    #[Test]
    public function delivery_approaching_sms_message(): void
    {
        $notification = new DeliveryApproachingNotification(
            'Juan García',
            'Pedro López',
            new \DateTimeImmutable('+30 minutes'),
            'https://example.com/track/abc123',
        );

        self::assertInstanceOf(SmsNotificationInterface::class, $notification);
        self::assertSame('pre_delivery_notification', $notification->getTemplateName());
        self::assertSame(['sms'], $notification->getChannels(new Recipient('', '+34600000000')));

        $sms = $notification->asSmsMessage(new Recipient('', '+34600000000'));
        self::assertNotNull($sms);
        self::assertSame('+34600000000', $sms->getPhone());
        self::assertStringContainsString('Juan García', $sms->getSubject());
        self::assertStringContainsString('Pedro López', $sms->getSubject());
        self::assertStringContainsString('https://example.com/track/abc123', $sms->getSubject());
    }

    #[Test]
    public function delivery_completed_sms_message(): void
    {
        $notification = new DeliveryCompletedNotification(
            'Juan García',
            'REF-001',
            'https://example.com/track/abc123/rate',
        );

        self::assertInstanceOf(SmsNotificationInterface::class, $notification);
        self::assertSame('delivery_completed', $notification->getTemplateName());
        self::assertSame(['sms'], $notification->getChannels(new Recipient('', '+34600000000')));

        $sms = $notification->asSmsMessage(new Recipient('', '+34600000000'));
        self::assertNotNull($sms);
        self::assertStringContainsString('Juan García', $sms->getSubject());
        self::assertStringContainsString('REF-001', $sms->getSubject());
        self::assertStringContainsString('https://example.com/track/abc123/rate', $sms->getSubject());
    }

    #[Test]
    public function rating_request_sms_message(): void
    {
        $notification = new RatingRequestNotification(
            'Juan García',
            'https://example.com/track/abc123/rate',
        );

        self::assertInstanceOf(SmsNotificationInterface::class, $notification);
        self::assertSame('rating_request', $notification->getTemplateName());

        $sms = $notification->asSmsMessage(new Recipient('', '+34600000000'));
        self::assertNotNull($sms);
        self::assertStringContainsString('Juan García', $sms->getSubject());
        self::assertStringContainsString('https://example.com/track/abc123/rate', $sms->getSubject());
    }

    #[Test]
    public function delivery_slot_confirmed_sms_message(): void
    {
        $notification = new DeliverySlotConfirmedNotification(
            'Juan García',
            '15/03/2026',
            '10:00-12:00',
        );

        self::assertInstanceOf(SmsNotificationInterface::class, $notification);
        self::assertSame('delivery_slot_confirmation', $notification->getTemplateName());

        $sms = $notification->asSmsMessage(new Recipient('', '+34600000000'));
        self::assertNotNull($sms);
        self::assertStringContainsString('Juan García', $sms->getSubject());
        self::assertStringContainsString('15/03/2026', $sms->getSubject());
        self::assertStringContainsString('10:00-12:00', $sms->getSubject());
    }

    #[Test]
    public function reschedule_confirmed_sms_message(): void
    {
        $notification = new RescheduleConfirmedNotification(
            'Juan García',
            '16/03/2026',
            '14:00-16:00',
            'https://example.com/track/abc123',
        );

        self::assertInstanceOf(SmsNotificationInterface::class, $notification);
        self::assertSame('reschedule_confirmation', $notification->getTemplateName());

        $sms = $notification->asSmsMessage(new Recipient('', '+34600000000'));
        self::assertNotNull($sms);
        self::assertStringContainsString('Juan García', $sms->getSubject());
        self::assertStringContainsString('16/03/2026', $sms->getSubject());
        self::assertStringContainsString('14:00-16:00', $sms->getSubject());
        self::assertStringContainsString('https://example.com/track/abc123', $sms->getSubject());
    }
}
