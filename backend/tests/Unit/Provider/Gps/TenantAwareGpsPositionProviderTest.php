<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Gps;

use App\Entity\Customer;
use App\Provider\Gps\TenantAwareGpsPositionProvider;
use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\Tracking\DevicePosition;
use App\Tracking\GpsPositionProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantAwareGpsPositionProvider::class)]
final class TenantAwareGpsPositionProviderTest extends TestCase
{
    #[Test]
    public function implementsGpsPositionProviderInterface(): void
    {
        $resolver = $this->createMock(ProviderResolverInterface::class);
        $tenantContext = $this->createMock(TenantContext::class);

        $proxy = new TenantAwareGpsPositionProvider($resolver, $tenantContext);

        self::assertInstanceOf(GpsPositionProviderInterface::class, $proxy);
    }

    #[Test]
    public function getPositionsDelegatesToResolvedProvider(): void
    {
        $customer = $this->createMock(Customer::class);
        $since = new \DateTimeImmutable('2026-01-01');
        $expectedPositions = [
            new DevicePosition(
                latitude: 40.0,
                longitude: -3.0,
                speed: 50.0,
                course: 180.0,
                accuracy: 5.0,
                deviceTime: new \DateTimeImmutable(),
                serverTime: new \DateTimeImmutable(),
            ),
        ];

        $innerProvider = $this->createMock(GpsPositionProviderInterface::class);
        $innerProvider->expects(self::once())
            ->method('getPositions')
            ->with(42, $since)
            ->willReturn($expectedPositions);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::GpsProvider, $customer)
            ->willReturn($innerProvider);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn($customer);

        $proxy = new TenantAwareGpsPositionProvider($resolver, $tenantContext);
        $result = $proxy->getPositions(42, $since);

        self::assertSame($expectedPositions, $result);
    }

    #[Test]
    public function isAvailableDelegatesToResolvedProvider(): void
    {
        $innerProvider = $this->createMock(GpsPositionProviderInterface::class);
        $innerProvider->expects(self::once())
            ->method('isAvailable')
            ->willReturn(true);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->method('resolve')->willReturn($innerProvider);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn(null);

        $proxy = new TenantAwareGpsPositionProvider($resolver, $tenantContext);

        self::assertTrue($proxy->isAvailable());
    }
}
