<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Route\ValueObject;

use App\Domain\Route\ValueObject\Coordinate;
use PHPUnit\Framework\TestCase;

final class CoordinateTest extends TestCase
{
    public function testCreatesWithValidValues(): void
    {
        $coord = new Coordinate(19.4326, -99.1332);
        self::assertSame(19.4326, $coord->latitude);
        self::assertSame(-99.1332, $coord->longitude);
    }

    public function testRejectsLatitudeOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Coordinate(91.0, 0.0);
    }

    public function testRejectsNegativeLatitudeOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Coordinate(-91.0, 0.0);
    }

    public function testRejectsLongitudeOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Coordinate(0.0, 181.0);
    }

    public function testAcceptsBoundaryValues(): void
    {
        $coord = new Coordinate(90.0, -180.0);
        self::assertSame(90.0, $coord->latitude);
        self::assertSame(-180.0, $coord->longitude);
    }

    public function testEquality(): void
    {
        $a = new Coordinate(19.4326, -99.1332);
        $b = new Coordinate(19.4326, -99.1332);
        self::assertTrue($a->equals($b));
    }
}
