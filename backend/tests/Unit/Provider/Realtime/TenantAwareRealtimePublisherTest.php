<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Realtime;

use App\Entity\Customer;
use App\Provider\ProviderResolverInterface;
use App\Provider\Realtime\TenantAwareRealtimePublisher;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantAwareRealtimePublisher::class)]
final class TenantAwareRealtimePublisherTest extends TestCase
{
    #[Test]
    public function implementsRealtimePublisherInterface(): void
    {
        $resolver = $this->createMock(ProviderResolverInterface::class);
        $tenantContext = $this->createMock(TenantContext::class);

        $proxy = new TenantAwareRealtimePublisher($resolver, $tenantContext);

        self::assertInstanceOf(RealtimePublisherInterface::class, $proxy);
    }

    #[Test]
    public function publishDelegatesToResolvedProvider(): void
    {
        $customer = $this->createMock(Customer::class);
        $message = new SseMessage(data: ['test' => true], topics: ['/test']);

        $innerPublisher = $this->createMock(RealtimePublisherInterface::class);
        $innerPublisher->expects(self::once())
            ->method('publish')
            ->with($message);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::RealtimePublisher, $customer)
            ->willReturn($innerPublisher);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn($customer);

        $proxy = new TenantAwareRealtimePublisher($resolver, $tenantContext);
        $proxy->publish($message);
    }

    #[Test]
    public function publishBatchDelegatesToResolvedProvider(): void
    {
        $customer = $this->createMock(Customer::class);
        $messages = [
            new SseMessage(data: 'msg1', topics: ['/a']),
            new SseMessage(data: 'msg2', topics: ['/b']),
        ];

        $innerPublisher = $this->createMock(RealtimePublisherInterface::class);
        $innerPublisher->expects(self::once())
            ->method('publishBatch')
            ->with($messages);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::RealtimePublisher, $customer)
            ->willReturn($innerPublisher);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn($customer);

        $proxy = new TenantAwareRealtimePublisher($resolver, $tenantContext);
        $proxy->publishBatch($messages);
    }

    #[Test]
    public function publishUsesNullCustomerWhenNoTenantInContext(): void
    {
        $message = new SseMessage(data: 'test', topics: ['/test']);

        $innerPublisher = $this->createMock(RealtimePublisherInterface::class);
        $innerPublisher->expects(self::once())
            ->method('publish')
            ->with($message);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::RealtimePublisher, null)
            ->willReturn($innerPublisher);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn(null);

        $proxy = new TenantAwareRealtimePublisher($resolver, $tenantContext);
        $proxy->publish($message);
    }
}
