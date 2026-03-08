<?php

declare(strict_types=1);

namespace App\Geocoding;

use Psr\Log\LoggerInterface;

final class NullGeocoder implements GeocoderInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function geocode(string $address, ?string $countryCode = null): ?GeocodingResult
    {
        $this->logger->debug('NullGeocoder::geocode called.', [
            'address' => $address,
            'countryCode' => $countryCode,
        ]);

        return null;
    }

    public function geocodeBatch(array $addresses, ?string $countryCode = null): array
    {
        $this->logger->debug('NullGeocoder::geocodeBatch called.', [
            'count' => \count($addresses),
            'countryCode' => $countryCode,
        ]);

        return array_fill_keys(array_keys($addresses), null);
    }

    public function reverse(float $latitude, float $longitude): ?ReverseGeocodingResult
    {
        $this->logger->debug('NullGeocoder::reverse called.', [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        return null;
    }
}
