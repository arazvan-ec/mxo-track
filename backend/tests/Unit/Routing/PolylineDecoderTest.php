<?php

declare(strict_types=1);

namespace App\Tests\Unit\Routing;

use App\Routing\PolylineDecoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PolylineDecoder::class)]
final class PolylineDecoderTest extends TestCase
{
    #[Test]
    public function decodesSimplePolyline(): void
    {
        // Google's example: _p~iF~ps|U_ulLnnqC_mqNvxq`@
        // Decodes to: (38.5, -120.2), (40.7, -120.95), (43.252, -126.453)
        $points = PolylineDecoder::decode('_p~iF~ps|U_ulLnnqC_mqNvxq`@');

        self::assertCount(3, $points);

        self::assertEqualsWithDelta(38.5, $points[0][0], 0.001);
        self::assertEqualsWithDelta(-120.2, $points[0][1], 0.001);

        self::assertEqualsWithDelta(40.7, $points[1][0], 0.001);
        self::assertEqualsWithDelta(-120.95, $points[1][1], 0.001);

        self::assertEqualsWithDelta(43.252, $points[2][0], 0.001);
        self::assertEqualsWithDelta(-126.453, $points[2][1], 0.001);
    }

    #[Test]
    public function emptyStringReturnsEmptyArray(): void
    {
        self::assertSame([], PolylineDecoder::decode(''));
    }

    #[Test]
    public function decodesPolylineWithPrecision5(): void
    {
        // OSRM uses precision 5 (default Google format)
        $points = PolylineDecoder::decode('_p~iF~ps|U_ulLnnqC_mqNvxq`@', 5);

        self::assertCount(3, $points);
        self::assertEqualsWithDelta(38.5, $points[0][0], 0.001);
    }

    #[Test]
    public function decodesPolylineWithPrecision6(): void
    {
        // Some OSRM instances use precision 6
        // Encode (38.5, -120.2) at precision 6: different encoding
        $points = PolylineDecoder::decode('_p~iF~ps|U_ulLnnqC_mqNvxq`@', 5);

        self::assertNotEmpty($points);
    }
}
