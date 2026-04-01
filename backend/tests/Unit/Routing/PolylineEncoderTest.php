<?php

declare(strict_types=1);

namespace App\Tests\Unit\Routing;

use App\Routing\PolylineDecoder;
use App\Routing\PolylineEncoder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PolylineEncoderTest extends TestCase
{
    #[Test]
    public function encode_empty_array_returns_empty_string(): void
    {
        self::assertSame('', PolylineEncoder::encode([]));
    }

    #[Test]
    public function encode_single_point(): void
    {
        $encoded = PolylineEncoder::encode([[40.41600, -3.70300]]);
        $decoded = PolylineDecoder::decode($encoded);

        self::assertCount(1, $decoded);
        self::assertEqualsWithDelta(40.416, $decoded[0][0], 0.00001);
        self::assertEqualsWithDelta(-3.703, $decoded[0][1], 0.00001);
    }

    #[Test]
    public function encode_roundtrip_with_multiple_points(): void
    {
        $original = [
            [40.34600, -3.69700],  // Villaverde warehouse
            [40.41500, -3.68000],  // Menéndez Pelayo
            [40.42100, -3.67600],  // O'Donnell
            [40.42200, -3.67800],  // Narváez
        ];

        $encoded = PolylineEncoder::encode($original);
        self::assertNotEmpty($encoded);

        $decoded = PolylineDecoder::decode($encoded);
        self::assertCount(4, $decoded);

        foreach ($original as $i => [$lat, $lng]) {
            self::assertEqualsWithDelta($lat, $decoded[$i][0], 0.00001, "Lat mismatch at index $i");
            self::assertEqualsWithDelta($lng, $decoded[$i][1], 0.00001, "Lng mismatch at index $i");
        }
    }

    #[Test]
    public function encode_known_polyline(): void
    {
        // Google's example: (38.5, -120.2), (40.7, -120.95), (43.252, -126.453)
        $points = [
            [38.5, -120.2],
            [40.7, -120.95],
            [43.252, -126.453],
        ];

        $encoded = PolylineEncoder::encode($points);
        $decoded = PolylineDecoder::decode($encoded);

        self::assertCount(3, $decoded);
        foreach ($points as $i => [$lat, $lng]) {
            self::assertEqualsWithDelta($lat, $decoded[$i][0], 0.00001);
            self::assertEqualsWithDelta($lng, $decoded[$i][1], 0.00001);
        }
    }
}
