<?php

declare(strict_types=1);

namespace App\Tracking;

use Psr\Log\LoggerInterface;

final class NullGpsProvider implements GpsPositionProviderInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isAvailable(): bool
    {
        return false;
    }

    /** @return list<DevicePosition> */
    public function getPositions(int $deviceId, ?\DateTimeImmutable $since = null): array
    {
        return [];
    }
}
