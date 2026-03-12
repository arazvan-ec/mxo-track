<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Gate;

use App\Notification\Gate\QuietHoursGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(QuietHoursGuard::class)]
final class QuietHoursGuardTest extends TestCase
{
    #[Test]
    public function it_allows_during_business_hours(): void
    {
        $clock = $this->createClockAt('10:00');
        $guard = new QuietHoursGuard($clock);

        self::assertTrue($guard->canSendNow());
    }

    #[Test]
    public function it_blocks_late_at_night(): void
    {
        $clock = $this->createClockAt('23:00');
        $guard = new QuietHoursGuard($clock);

        self::assertFalse($guard->canSendNow());
    }

    #[Test]
    public function it_blocks_early_morning(): void
    {
        $clock = $this->createClockAt('06:00');
        $guard = new QuietHoursGuard($clock);

        self::assertFalse($guard->canSendNow());
    }

    #[Test]
    public function it_allows_at_exactly_8am(): void
    {
        $clock = $this->createClockAt('08:00');
        $guard = new QuietHoursGuard($clock);

        self::assertTrue($guard->canSendNow());
    }

    #[Test]
    public function it_blocks_at_exactly_10pm(): void
    {
        $clock = $this->createClockAt('22:00');
        $guard = new QuietHoursGuard($clock);

        self::assertFalse($guard->canSendNow());
    }

    private function createClockAt(string $time): ClockInterface
    {
        $now = new \DateTimeImmutable("2026-03-12 {$time}:00", new \DateTimeZone('Europe/Madrid'));
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        return $clock;
    }
}
