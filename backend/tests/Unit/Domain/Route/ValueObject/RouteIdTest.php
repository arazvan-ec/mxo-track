<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Route\ValueObject;

use App\Domain\Route\ValueObject\RouteId;
use PHPUnit\Framework\TestCase;

final class RouteIdTest extends TestCase
{
    public function testCreatesWithValidValue(): void
    {
        $id = new RouteId('01HXYZ1234567890ABCDEF');
        self::assertSame('01HXYZ1234567890ABCDEF', $id->value());
        self::assertSame('01HXYZ1234567890ABCDEF', (string) $id);
    }

    public function testRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RouteId('');
    }

    public function testEquality(): void
    {
        $a = new RouteId('ABC');
        $b = new RouteId('ABC');
        $c = new RouteId('XYZ');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
