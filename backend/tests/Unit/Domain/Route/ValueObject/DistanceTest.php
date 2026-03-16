<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Route\ValueObject;

use App\Domain\Route\ValueObject\Distance;
use PHPUnit\Framework\TestCase;

final class DistanceTest extends TestCase
{
    public function testCreatesWithValidValue(): void
    {
        $d = new Distance(42.5);
        self::assertSame(42.5, $d->km);
    }

    public function testAcceptsZero(): void
    {
        $d = new Distance(0.0);
        self::assertSame(0.0, $d->km);
    }

    public function testRejectsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Distance(-1.0);
    }
}
