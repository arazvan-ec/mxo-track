<?php

declare(strict_types=1);

namespace App\Service;

use App\Tracking\GpsDeviceProviderInterface;
use DateTimeImmutable;

/**
 * @deprecated Use App\Tracking\GpsDeviceProviderInterface instead.
 */
final class TraccarApiClient
{
    public function __construct(
        private readonly GpsDeviceProviderInterface $provider,
    ) {
    }

    public function login(): void
    {
        $this->provider->login();
    }

    public function getSessionCookie(): ?string
    {
        return $this->provider->getSessionCookie();
    }

    public function canConnect(): bool
    {
        return $this->provider->isAvailable();
    }

    /** @return list<array<string,mixed>> */
    public function getDevices(): array
    {
        return array_map(
            static fn($d) => ['id' => $d->id, 'name' => $d->name, 'uniqueId' => $d->uniqueId],
            $this->provider->getDevices(),
        );
    }

    /** @return array<string,mixed> */
    public function createDevice(string $name, string $uniqueId): array
    {
        $d = $this->provider->createDevice($name, $uniqueId);

        return ['id' => $d->id, 'name' => $d->name, 'uniqueId' => $d->uniqueId];
    }

    /** @return list<array<string,mixed>> */
    public function getPositions(int $deviceId, ?DateTimeImmutable $from = null): array
    {
        return array_map(
            static fn($p) => [
                'latitude' => $p->latitude,
                'longitude' => $p->longitude,
                'speed' => $p->speed,
                'course' => $p->course,
                'accuracy' => $p->accuracy,
                'deviceTime' => $p->deviceTime->format(DATE_ATOM),
                'serverTime' => $p->serverTime->format(DATE_ATOM),
                'id' => $p->rawId,
                'deviceId' => $p->deviceId,
            ],
            $this->provider->getPositions($deviceId, $from),
        );
    }
}
