<?php

declare(strict_types=1);

namespace App\Routing;

/**
 * Encodes an array of [lat, lng] pairs into Google Encoded Polyline format.
 *
 * @see https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */
final class PolylineEncoder
{
    /**
     * @param list<array{0: float, 1: float}> $points Array of [latitude, longitude] pairs
     */
    public static function encode(array $points, int $precision = 5): string
    {
        if ($points === []) {
            return '';
        }

        $factor = 10 ** $precision;
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as [$lat, $lng]) {
            $latRound = (int) round($lat * $factor);
            $lngRound = (int) round($lng * $factor);

            $encoded .= self::encodeValue($latRound - $prevLat);
            $encoded .= self::encodeValue($lngRound - $prevLng);

            $prevLat = $latRound;
            $prevLng = $lngRound;
        }

        return $encoded;
    }

    private static function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $encoded = '';

        while ($value >= 0x20) {
            $encoded .= \chr((($value & 0x1F) | 0x20) + 63);
            $value >>= 5;
        }

        $encoded .= \chr($value + 63);

        return $encoded;
    }
}
