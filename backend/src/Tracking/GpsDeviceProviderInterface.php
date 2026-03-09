<?php

declare(strict_types=1);

namespace App\Tracking;

interface GpsDeviceProviderInterface
{
    /** @return list<DeviceInfo> */
    public function getDevices(): array;

    public function createDevice(string $name, string $uniqueId): DeviceInfo;

    /**
     * @return list<DevicePosition>
     */
    public function getPositions(int $deviceId, ?\DateTimeImmutable $since = null): array;

    public function isAvailable(): bool;

    public function login(): void;

    public function getSessionCookie(): ?string;
}
