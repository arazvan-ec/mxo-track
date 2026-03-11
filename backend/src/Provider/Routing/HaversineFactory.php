<?php

declare(strict_types=1);

namespace App\Provider\Routing;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;

final class HaversineFactory implements ProviderFactoryInterface
{
    public function create(array $config): HaversineEngine
    {
        return new HaversineEngine(HaversineConfig::fromArray($config));
    }

    public function getProviderType(): string
    {
        return RoutingProvider::Haversine->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::RoutingEngine;
    }
}
