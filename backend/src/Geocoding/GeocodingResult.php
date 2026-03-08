<?php

declare(strict_types=1);

namespace App\Geocoding;

final readonly class GeocodingResult
{
    /**
     * @param array<string, mixed> $raw Raw provider response for debugging
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public string $formattedAddress,
        public ?string $street = null,
        public ?string $city = null,
        public ?string $postalCode = null,
        public ?string $country = null,
        public float $confidence = 0.0,
        public array $raw = [],
    ) {
    }
}
