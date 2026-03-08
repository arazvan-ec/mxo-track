<?php

declare(strict_types=1);

namespace App\Tracking;

use Psr\Log\LoggerInterface;

final class NullGpsProvider implements GpsDeviceProviderInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function login(): void
    {
        $this->logger->debug('NullGpsProvider: login() called (no-op).');
    }

    public function getSessionCookie(): ?string
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    /** @return list<DeviceInfo> */
    public function getDevices(): array
    {
        return [];
    }

    public function createDevice(string $name, string $uniqueId): DeviceInfo
    {
        return new DeviceInfo(id: 0, name: $name, uniqueId: $uniqueId);
    }

    /** @return list<DevicePosition> */
    public function getPositions(int $deviceId, ?\DateTimeImmutable $since = null): array
    {
        return [];
    }
}
