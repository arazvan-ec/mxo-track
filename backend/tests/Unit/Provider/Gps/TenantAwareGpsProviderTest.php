<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Gps;

use App\Entity\Customer;
use App\Provider\Gps\TenantAwareGpsProvider;
use App\Provider\ProviderResolverInterface;
use App\Provider\ServiceType;
use App\Provider\TenantContext;
use App\Tracking\DeviceInfo;
use App\Tracking\DevicePosition;
use App\Tracking\GpsDeviceProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantAwareGpsProvider::class)]
final class TenantAwareGpsProviderTest extends TestCase
{
    #[Test]
    public function implementsGpsDeviceProviderInterface(): void
    {
        $resolver = $this->createMock(ProviderResolverInterface::class);
        $tenantContext = $this->createMock(TenantContext::class);

        $proxy = new TenantAwareGpsProvider($resolver, $tenantContext);

        self::assertInstanceOf(GpsDeviceProviderInterface::class, $proxy);
    }

    #[Test]
    public function getDevicesDelegatesToResolvedProvider(): void
    {
        $customer = $this->createMock(Customer::class);
        $expectedDevices = [new DeviceInfo(id: 1, name: 'Test', uniqueId: 'test-1')];

        $innerProvider = $this->createMock(GpsDeviceProviderInterface::class);
        $innerProvider->expects(self::once())
            ->method('getDevices')
            ->willReturn($expectedDevices);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(ServiceType::GpsProvider, $customer)
            ->willReturn($innerProvider);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn($customer);

        $proxy = new TenantAwareGpsProvider($resolver, $tenantContext);
        $result = $proxy->getDevices();

        self::assertSame($expectedDevices, $result);
    }

    #[Test]
    public function createDeviceDelegatesToResolvedProvider(): void
    {
        $customer = $this->createMock(Customer::class);
        $expectedDevice = new DeviceInfo(id: 2, name: 'New', uniqueId: 'new-1');

        $innerProvider = $this->createMock(GpsDeviceProviderInterface::class);
        $innerProvider->expects(self::once())
            ->method('createDevice')
            ->with('New', 'new-1')
            ->willReturn($expectedDevice);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->method('resolve')->willReturn($innerProvider);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn($customer);

        $proxy = new TenantAwareGpsProvider($resolver, $tenantContext);
        $result = $proxy->createDevice('New', 'new-1');

        self::assertSame($expectedDevice, $result);
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

        $innerProvider = $this->createMock(GpsDeviceProviderInterface::class);
        $innerProvider->expects(self::once())
            ->method('getPositions')
            ->with(42, $since)
            ->willReturn($expectedPositions);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->method('resolve')->willReturn($innerProvider);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn($customer);

        $proxy = new TenantAwareGpsProvider($resolver, $tenantContext);
        $result = $proxy->getPositions(42, $since);

        self::assertSame($expectedPositions, $result);
    }

    #[Test]
    public function isAvailableDelegatesToResolvedProvider(): void
    {
        $innerProvider = $this->createMock(GpsDeviceProviderInterface::class);
        $innerProvider->expects(self::once())
            ->method('isAvailable')
            ->willReturn(true);

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->method('resolve')->willReturn($innerProvider);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn(null);

        $proxy = new TenantAwareGpsProvider($resolver, $tenantContext);

        self::assertTrue($proxy->isAvailable());
    }

    #[Test]
    public function loginDelegatesToResolvedProvider(): void
    {
        $innerProvider = $this->createMock(GpsDeviceProviderInterface::class);
        $innerProvider->expects(self::once())
            ->method('login');

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->method('resolve')->willReturn($innerProvider);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn(null);

        $proxy = new TenantAwareGpsProvider($resolver, $tenantContext);
        $proxy->login();
    }

    #[Test]
    public function getSessionCookieDelegatesToResolvedProvider(): void
    {
        $innerProvider = $this->createMock(GpsDeviceProviderInterface::class);
        $innerProvider->expects(self::once())
            ->method('getSessionCookie')
            ->willReturn('JSESSIONID=abc123');

        $resolver = $this->createMock(ProviderResolverInterface::class);
        $resolver->method('resolve')->willReturn($innerProvider);

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext->method('getCustomer')->willReturn(null);

        $proxy = new TenantAwareGpsProvider($resolver, $tenantContext);

        self::assertSame('JSESSIONID=abc123', $proxy->getSessionCookie());
    }
}
