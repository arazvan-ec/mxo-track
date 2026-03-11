<?php

declare(strict_types=1);

namespace App\Provider\Gps;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use App\Tracking\TraccarGpsProvider;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TraccarFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $defaultBaseUrl,
        private readonly string $defaultUsername,
        private readonly string $defaultPassword,
    ) {
    }

    public function create(array $config): TraccarGpsProvider
    {
        $baseUrl = $config['base_url'] ?? $this->defaultBaseUrl;
        $username = $config['username'] ?? $this->defaultUsername;
        $password = $config['password'] ?? $this->defaultPassword;

        return new TraccarGpsProvider(
            $this->httpClient,
            $this->logger,
            $baseUrl,
            $username,
            $password,
        );
    }

    public function getProviderType(): string
    {
        return GpsProviderType::Traccar->value;
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::GpsProvider;
    }
}
