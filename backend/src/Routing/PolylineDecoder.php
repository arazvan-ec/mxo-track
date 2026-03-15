<?php

declare(strict_types=1);

namespace App\Routing;

/**
 * Decodes Google Encoded Polyline format to an array of [lat, lng] pairs.
 *
 * @see https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */
final class PolylineDecoder
{
    /**
     * @return list<array{0: float, 1: float}> Array of [latitude, longitude] pairs
     */
    public static function decode(string $encoded, int $precision = 5): array
    {
        if ($encoded === '') {
            return [];
        }

        $factor = 10 ** $precision;
        $points = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $len = \strlen($encoded);

        while ($index < $len) {
            $lat += self::decodeNextValue($encoded, $index, $len);
            $lng += self::decodeNextValue($encoded, $index, $len);

            $points[] = [$lat / $factor, $lng / $factor];
        }

        return $points;
    }

    private static function decodeNextValue(string $encoded, int &$index, int $len): int
    {
        $result = 0;
        $shift = 0;

        do {
            if ($index >= $len) {
                break;
            }
            $char = \ord($encoded[$index]) - 63;
            $index++;
            $result |= ($char & 0x1F) << $shift;
            $shift += 5;
        } while ($char >= 0x20);

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
    }
}
