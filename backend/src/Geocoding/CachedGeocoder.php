<?php

declare(strict_types=1);

namespace App\Geocoding;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedGeocoder implements GeocoderInterface
{
    private const TTL_SECONDS = 30 * 24 * 3600; // 30 days

    public function __construct(
        private readonly GeocoderInterface $inner,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function geocode(string $address, ?string $countryCode = null): ?GeocodingResult
    {
        $cacheKey = $this->forwardCacheKey($address, $countryCode);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($address, $countryCode): ?GeocodingResult {
            $item->expiresAfter(self::TTL_SECONDS);

            $this->logger->debug('Geocode cache miss.', ['address' => $address]);

            return $this->inner->geocode($address, $countryCode);
        });
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
        $cacheKey = $this->reverseCacheKey($latitude, $longitude);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($latitude, $longitude): ?ReverseGeocodingResult {
            $item->expiresAfter(self::TTL_SECONDS);

            $this->logger->debug('Reverse geocode cache miss.', [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return $this->inner->reverse($latitude, $longitude);
        });
    }

    private function forwardCacheKey(string $address, ?string $countryCode): string
    {
        $normalized = mb_strtolower(trim($address));
        $input = $countryCode !== null ? "{$normalized}|{$countryCode}" : $normalized;

        return 'geocode_fwd_' . md5($input);
    }

    private function reverseCacheKey(float $latitude, float $longitude): string
    {
        $rounded = sprintf('%.5f,%.5f', $latitude, $longitude);

        return 'geocode_rev_' . md5($rounded);
    }
}
