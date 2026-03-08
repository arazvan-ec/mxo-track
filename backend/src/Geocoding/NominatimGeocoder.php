<?php

declare(strict_types=1);

namespace App\Geocoding;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NominatimGeocoder implements GeocoderInterface
{
    private const USER_AGENT = 'mxo-track/1.0 (logistics-tracking)';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $nominatimUrl = 'https://nominatim.openstreetmap.org',
    ) {
    }

    public function geocode(string $address, ?string $countryCode = null): ?GeocodingResult
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $query = [
            'q' => $address,
            'format' => 'jsonv2',
            'limit' => 1,
            'addressdetails' => 1,
        ];

        if ($countryCode !== null) {
            $query['countrycodes'] = $countryCode;
        }

        try {
            $response = $this->httpClient->request('GET', $this->buildUrl('/search'), [
                'query' => $query,
                'headers' => ['User-Agent' => self::USER_AGENT],
            ]);

            $data = $response->toArray();

            if ($data === []) {
                $this->logger->info('Nominatim geocode returned no results.', ['address' => $address]);

                return null;
            }

            return $this->mapForwardResult($data[0]);
        } catch (\Throwable $e) {
            $this->logger->error('Nominatim geocode failed.', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function geocodeBatch(array $addresses, ?string $countryCode = null): array
    {
        $results = [];

        foreach ($addresses as $key => $address) {
            $results[$key] = $this->geocode($address, $countryCode);
        }

        return $results;
    }

    public function reverse(float $latitude, float $longitude): ?ReverseGeocodingResult
    {
        $query = [
            'lat' => $latitude,
            'lon' => $longitude,
            'format' => 'jsonv2',
            'addressdetails' => 1,
        ];

        try {
            $response = $this->httpClient->request('GET', $this->buildUrl('/reverse'), [
                'query' => $query,
                'headers' => ['User-Agent' => self::USER_AGENT],
            ]);

            $data = $response->toArray();

            if (!isset($data['lat'], $data['lon'])) {
                $this->logger->info('Nominatim reverse geocode returned no results.', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);

                return null;
            }

            return $this->mapReverseResult($data);
        } catch (\Throwable $e) {
            $this->logger->error('Nominatim reverse geocode failed.', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildUrl(string $path): string
    {
        return rtrim($this->nominatimUrl, '/') . $path;
    }

    private function mapForwardResult(array $item): GeocodingResult
    {
        $address = $item['address'] ?? [];

        return new GeocodingResult(
            latitude: (float) $item['lat'],
            longitude: (float) $item['lon'],
            formattedAddress: $item['display_name'] ?? '',
            street: $this->extractStreet($address),
            city: $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
            postalCode: $address['postcode'] ?? null,
            country: $address['country'] ?? null,
            confidence: $this->mapConfidence($item),
            raw: $item,
        );
    }

    private function mapReverseResult(array $item): ReverseGeocodingResult
    {
        $address = $item['address'] ?? [];

        return new ReverseGeocodingResult(
            latitude: (float) $item['lat'],
            longitude: (float) $item['lon'],
            formattedAddress: $item['display_name'] ?? '',
            street: $this->extractStreet($address),
            city: $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
            postalCode: $address['postcode'] ?? null,
            country: $address['country'] ?? null,
            confidence: $this->mapConfidence($item),
            raw: $item,
        );
    }

    private function extractStreet(array $address): ?string
    {
        $road = $address['road'] ?? null;
        $houseNumber = $address['house_number'] ?? null;

        if ($road === null) {
            return null;
        }

        return $houseNumber !== null ? "{$road}, {$houseNumber}" : $road;
    }

    /**
     * Maps Nominatim's `importance` field (0-1) to a confidence score.
     * Falls back to a conservative default if not present.
     */
    private function mapConfidence(array $item): float
    {
        $importance = $item['importance'] ?? null;

        if ($importance === null) {
            return 0.5;
        }

        return max(0.0, min(1.0, (float) $importance));
    }
}
