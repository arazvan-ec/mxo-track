<?php

declare(strict_types=1);

namespace App\Provider\Gps;

use App\Tracking\DeviceInfo;
use App\Tracking\GpsDeviceProviderInterface;

/**
 * GPS provider for webhook-based position ingestion.
 *
 * Positions are pushed via HTTP webhook rather than pulled from
 * a tracking server. Pull-based methods (getDevices, getPositions)
 * return empty results; createDevice returns a stub DeviceInfo.
 */
final class WebhookGpsProvider implements GpsDeviceProviderInterface
{
    public function getDevices(): array
    {
        return [];
    }

    public function createDevice(string $name, string $uniqueId): DeviceInfo
    {
        return new DeviceInfo(id: 0, name: $name, uniqueId: $uniqueId);
    }

    public function getPositions(int $deviceId, ?\DateTimeImmutable $since = null): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function login(): void
    {
        // No-op: webhook provider has no session concept.
    }

    public function getSessionCookie(): ?string
    {
        return null;
    }
}
