<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Route\ValueObject;

use App\Domain\Route\ValueObject\Capacity;
use PHPUnit\Framework\TestCase;

final class CapacityTest extends TestCase
{
    public function testCreatesWithValidValues(): void
    {
        $cap = new Capacity(100.5, 2.3, 10);
        self::assertSame(100.5, $cap->weightKg);
        self::assertSame(2.3, $cap->volumeM3);
        self::assertSame(10, $cap->parcels);
    }

    public function testAcceptsZeroValues(): void
    {
        $cap = new Capacity(0.0, 0.0, 0);
        self::assertSame(0.0, $cap->weightKg);
    }

    public function testRejectsNegativeWeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Capacity(-1.0, 0.0, 0);
    }

    public function testRejectsNegativeVolume(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Capacity(0.0, -0.1, 0);
    }

    public function testRejectsNegativeParcels(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Capacity(0.0, 0.0, -1);
    }

    public function testAdd(): void
    {
        $a = new Capacity(10.0, 1.0, 5);
        $b = new Capacity(20.0, 2.0, 3);
        $sum = $a->add($b);

        self::assertSame(30.0, $sum->weightKg);
        self::assertSame(3.0, $sum->volumeM3);
        self::assertSame(8, $sum->parcels);
    }
}
