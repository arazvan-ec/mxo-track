<?php

declare(strict_types=1);

namespace App\Tracking;

final readonly class DevicePosition
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public float $speed,
        public float $course,
        public float $accuracy,
        public \DateTimeImmutable $deviceTime,
        public \DateTimeImmutable $serverTime,
        public ?int $rawId = null,
        public ?int $deviceId = null,
    ) {
    }
}
