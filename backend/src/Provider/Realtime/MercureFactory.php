<?php

declare(strict_types=1);

namespace App\Provider\Realtime;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use App\Realtime\MercurePublisher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;

final class MercureFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function create(array $config): MercurePublisher
    {
        return new MercurePublisher($this->hub, $this->logger);
    }

    public function getProviderType(): string
    {
        return RealtimeProviderType::Mercure->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::RealtimePublisher;
    }
}
