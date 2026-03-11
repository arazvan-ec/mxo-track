<?php

declare(strict_types=1);

namespace App\Provider\Realtime;

use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;

final class TenantAwareRealtimePublisher implements RealtimePublisherInterface
{
    public function __construct(
        private readonly ProviderResolverInterface $resolver,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function publish(SseMessage $message): void
    {
        $this->resolved()->publish($message);
    }

    public function publishBatch(array $messages): void
    {
        $this->resolved()->publishBatch($messages);
    }

    private function resolved(): RealtimePublisherInterface
    {
        $customer = $this->tenantContext->getCustomer();
        /** @var RealtimePublisherInterface */
        return $this->resolver->resolve(ServiceType::RealtimePublisher, $customer);
    }
}
