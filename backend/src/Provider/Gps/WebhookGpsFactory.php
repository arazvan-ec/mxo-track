<?php

declare(strict_types=1);

namespace App\Provider\Gps;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;

final class WebhookGpsFactory implements ProviderFactoryInterface
{
    public function create(array $config): WebhookGpsProvider
    {
        return new WebhookGpsProvider();
    }

    public function getProviderType(): string
    {
        return GpsProviderType::Webhook->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::GpsProvider;
    }
}
