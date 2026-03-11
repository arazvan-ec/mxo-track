<?php

declare(strict_types=1);

namespace App\Provider\Realtime;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

final class HttpPollingFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function create(array $config): HttpPollingPublisher
    {
        return new HttpPollingPublisher($this->em, $this->tenantContext);
    }

    public function getProviderType(): string
    {
        return RealtimeProviderType::HttpPolling->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::RealtimePublisher;
    }
}
