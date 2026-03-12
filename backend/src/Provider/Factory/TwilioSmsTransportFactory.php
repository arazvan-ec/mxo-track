<?php

declare(strict_types=1);

namespace App\Provider\Factory;

use App\Provider\ProviderFactoryInterface;
use App\Provider\ServiceType;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory as SymfonyTwilioTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TwilioSmsTransportFactory implements ProviderFactoryInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function create(array $config): object
    {
        $dsn = new Dsn(sprintf(
            'twilio://%s:%s@default?from=%s',
            $config['account_sid'] ?? '',
            $config['auth_token'] ?? '',
            urlencode($config['from_number'] ?? ''),
        ));

        $factory = new SymfonyTwilioTransportFactory(client: $this->httpClient);

        return $factory->create($dsn);
    }

    public function getProviderType(): string
    {
        return 'twilio';
    }

    public function getServiceType(): ServiceType
    {
        return ServiceType::SmsNotifier;
    }
}
