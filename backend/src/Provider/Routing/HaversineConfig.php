<?php

declare(strict_types=1);

namespace App\Provider\Routing;

final readonly class HaversineConfig
{
    public function __construct(
        public float $correctionFactor = 1.3,
        public float $averageSpeedKmh = 30.0,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            correctionFactor: (float) ($data['correction_factor'] ?? 1.3),
            averageSpeedKmh: (float) ($data['average_speed_kmh'] ?? 30.0),
        );
    }
}
