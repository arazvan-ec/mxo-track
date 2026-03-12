<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Gate;

use App\Enum\NotificationChannel;
use App\Notification\Gate\RecipientThrottle;
use App\Repository\NotificationLogRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecipientThrottle::class)]
final class RecipientThrottleTest extends TestCase
{
    private NotificationLogRepository&MockObject $logRepo;
    private RecipientThrottle $throttle;

    protected function setUp(): void
    {
        $this->logRepo = $this->createMock(NotificationLogRepository::class);
        $this->throttle = new RecipientThrottle($this->logRepo);
    }

    #[Test]
    public function it_allows_when_under_daily_limit_and_interval(): void
    {
        $this->logRepo->method('countSentSince')->willReturn(3);
        $this->logRepo->method('lastSentAt')->willReturn(
            new \DateTimeImmutable('-15 minutes'),
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
            new \DateTimeImmutable('-5 minutes'),
        );

        self::assertFalse($this->throttle->canSend('+34600000001', NotificationChannel::Sms));
    }

    #[Test]
    public function it_allows_when_no_previous_messages(): void
    {
        $this->logRepo->method('countSentSince')->willReturn(0);
        $this->logRepo->method('lastSentAt')->willReturn(null);

        self::assertTrue($this->throttle->canSend('+34600000001', NotificationChannel::Sms));
    }
}
