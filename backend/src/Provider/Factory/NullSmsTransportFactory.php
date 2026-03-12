<?php

declare(strict_types=1);

namespace App\Provider\Factory;

use App\Notification\Transport\NullSmsTransport;
use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use Psr\Log\LoggerInterface;

final class NullSmsTransportFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function create(array $config): object
    {
        return new NullSmsTransport($this->logger);
    }

    public function getProviderType(): string
    {
        return 'null';
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::SmsNotifier;
    }
}
