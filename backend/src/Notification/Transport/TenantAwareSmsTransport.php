<?php

declare(strict_types=1);

namespace App\Notification\Transport;

use App\Entity\Customer;
use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

final class TenantAwareSmsTransport implements TransportInterface
{
    private ?Customer $currentCustomer = null;

    public function __construct(
        private readonly ProviderResolverInterface $resolver,
        private readonly TransportInterface $defaultTransport,
    ) {
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->currentCustomer = $customer;
    }

    public function send(MessageInterface $message): SentMessage
    {
        if ($this->currentCustomer !== null) {
            $transport = $this->resolver->resolve(
                ServiceType::SmsNotifier,
                $this->currentCustomer,
            );

            if ($transport instanceof TransportInterface) {
                return $transport->send($message);
            }
        }

        return $this->defaultTransport->send($message);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    public function __toString(): string
    {
        return 'tenant-aware-sms';
    }
}
