<?php

declare(strict_types=1);

namespace App\Tracking;

interface GpsPositionProviderInterface
{
    /**
     * @return list<DevicePosition>
     */
    public function getPositions(int $deviceId, ?\DateTimeImmutable $since = null): array;

    public function isAvailable(): bool;
}
