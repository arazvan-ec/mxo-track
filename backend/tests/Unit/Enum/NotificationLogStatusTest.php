<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\NotificationLogStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationLogStatus::class)]
class NotificationLogStatusTest extends TestCase
{
    #[Test]
    public function it_has_all_expected_cases(): void
    {
        $cases = NotificationLogStatus::cases();
        self::assertCount(4, $cases);
        self::assertSame('sent', NotificationLogStatus::Sent->value);
        self::assertSame('failed', NotificationLogStatus::Failed->value);
        self::assertSame('throttled', NotificationLogStatus::Throttled->value);
        self::assertSame('deferred', NotificationLogStatus::Deferred->value);
    }
}
