<?php

declare(strict_types=1);

namespace App\Tracking;

interface GpsDeviceManagerInterface
{
    public function login(): void;

    public function getSessionCookie(): ?string;

    /** @return list<DeviceInfo> */
    public function getDevices(): array;

    public function createDevice(string $name, string $uniqueId): DeviceInfo;
}
