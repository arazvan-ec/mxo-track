<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Route\ValueObject;

use App\Domain\Route\ValueObject\TimeWindow;
use PHPUnit\Framework\TestCase;

final class TimeWindowTest extends TestCase
{
    public function testCreatesWithValidWindow(): void
    {
        $start = new \DateTimeImmutable('08:00');
        $end = new \DateTimeImmutable('17:00');
        $tw = new TimeWindow($start, $end);

        self::assertSame($start, $tw->start);
        self::assertSame($end, $tw->end);
    }

    public function testRejectsStartAfterEnd(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TimeWindow(
            new \DateTimeImmutable('17:00'),
            new \DateTimeImmutable('08:00'),
        );
    }

    public function testRejectsEqualStartAndEnd(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $time = new \DateTimeImmutable('12:00');
        new TimeWindow($time, $time);
    }
}
