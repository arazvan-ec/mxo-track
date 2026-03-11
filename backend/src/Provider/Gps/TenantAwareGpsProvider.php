<?php

declare(strict_types=1);

namespace App\Provider\Gps;

use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\Tracking\DeviceInfo;
use App\Tracking\DevicePosition;
use App\Tracking\GpsDeviceProviderInterface;

final class TenantAwareGpsProvider implements GpsDeviceProviderInterface
{
    public function __construct(
        private readonly ProviderResolverInterface $resolver,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function getDevices(): array
    {
        return $this->resolved()->getDevices();
    }

    public function createDevice(string $name, string $uniqueId): DeviceInfo
    {
        return $this->resolved()->createDevice($name, $uniqueId);
    }

    public function getPositions(int $deviceId, ?\DateTimeImmutable $since = null): array
    {
        return $this->resolved()->getPositions($deviceId, $since);
    }

    public function isAvailable(): bool
    {
        return $this->resolved()->isAvailable();
    }

    public function login(): void
    {
        $this->resolved()->login();
    }

    public function getSessionCookie(): ?string
    {
        return $this->resolved()->getSessionCookie();
    }

    private function resolved(): GpsDeviceProviderInterface
    {
        $customer = $this->tenantContext->getCustomer();
        /** @var GpsDeviceProviderInterface */
        return $this->resolver->resolve(ServiceType::GpsProvider, $customer);
    }
}
