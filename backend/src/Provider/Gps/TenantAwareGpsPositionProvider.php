<?php

declare(strict_types=1);

namespace App\Provider\Gps;

use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\Tracking\GpsPositionProviderInterface;

final class TenantAwareGpsPositionProvider implements GpsPositionProviderInterface
{
    public function __construct(
        private readonly ProviderResolverInterface $resolver,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function getPositions(int $deviceId, ?\DateTimeImmutable $since = null): array
    {
        return $this->resolved()->getPositions($deviceId, $since);
    }

    public function isAvailable(): bool
    {
        return $this->resolved()->isAvailable();
    }

    private function resolved(): GpsPositionProviderInterface
    {
        $customer = $this->tenantContext->getCustomer();
        /** @var GpsPositionProviderInterface */
        return $this->resolver->resolve(ServiceType::GpsProvider, $customer);
    }
}
