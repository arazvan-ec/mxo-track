<?php

declare(strict_types=1);

namespace App\Geocoding;

interface GeocoderInterface
{
    /**
     * Forward geocode: address string to coordinates.
     */
    public function geocode(string $address, ?string $countryCode = null): ?GeocodingResult;

    /**
     * Batch forward geocode: multiple addresses to coordinates.
     *
     * @param list<string> $addresses
     * @return array<int, GeocodingResult|null> Results indexed by the same key as input
     */
    public function geocodeBatch(array $addresses, ?string $countryCode = null): array;

    /**
     * Reverse geocode: coordinates to address.
     */
    public function reverse(float $latitude, float $longitude): ?ReverseGeocodingResult;
}
