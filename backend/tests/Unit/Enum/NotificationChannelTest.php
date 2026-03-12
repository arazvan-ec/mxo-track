<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\NotificationChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationChannel::class)]
class NotificationChannelTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_cases(): void
    {
        $cases = NotificationChannel::cases();
        self::assertCount(2, $cases);
        self::assertSame('sms', NotificationChannel::Sms->value);
        self::assertSame('whatsapp', NotificationChannel::WhatsApp->value);
    }
}
