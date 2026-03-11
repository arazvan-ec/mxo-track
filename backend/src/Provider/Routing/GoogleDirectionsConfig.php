<?php

declare(strict_types=1);

namespace App\Provider\Routing;

final readonly class GoogleDirectionsConfig
{
    public function __construct(
        public string $apiKey,
        public string $region = 'es',
        public bool $avoidTolls = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            apiKey: $data['api_key'] ?? throw new \InvalidArgumentException('api_key is required'),
            region: $data['region'] ?? 'es',
            avoidTolls: (bool) ($data['avoid_tolls'] ?? false),
        );
    }
}
