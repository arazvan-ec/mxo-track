<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Doctrine\CustomerTenantFilter;
use App\Entity\Customer;
use App\Entity\CustomerScopedEntityInterface;
use App\Entity\Shipment;
use App\Entity\User;
use App\Enum\UserRole;
use App\EventSubscriber\DoctrineCustomerFilterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Doctrine\ORM\Query\FilterCollection;

#[CoversClass(CustomerTenantFilter::class)]
#[CoversClass(DoctrineCustomerFilterSubscriber::class)]
final class CustomerTenantFilterTest extends TestCase
{
    #[Test]
    public function subscriberRegistersForKernelRequestEvent(): void
    {
        $events = DoctrineCustomerFilterSubscriber::getSubscribedEvents();

        self::assertArrayHasKey('kernel.request', $events);
        self::assertSame(['onKernelRequest', 50], $events['kernel.request']);
    }

    #[Test]
    public function subscriberEnablesFilterForCustomerUser(): void
    {
        $customer = new Customer('Tenant A');
        // Use reflection to set a fake ID for the customer
        $ref = new \ReflectionProperty($customer, 'id');
        $ref->setValue($customer, '42');

        $user = new User('customer@tenanta.com');
        $user->assignRole(UserRole::CUSTOMER);
        $user->setCustomer($customer);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $filterCollection = $this->createMock(FilterCollection::class);
        $filterCollection->method('has')->with('customer_tenant')->willReturn(true);
        $filterCollection->method('isEnabled')->with('customer_tenant')->willReturn(false);
        $filterCollection->expects(self::once())->method('enable')->with('customer_tenant');

        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('quote')->willReturnCallback(fn (string $value) => "'" . $value . "'");

        $filterEntityManager = $this->createMock(EntityManagerInterface::class);
        $filterEntityManager->method('getFilters')->willReturn($filterCollection);
        $filterEntityManager->method('getConnection')->willReturn($connection);

        $filter = new CustomerTenantFilter($filterEntityManager);

        $filterCollection->expects(self::once())->method('getFilter')->with('customer_tenant')->willReturn($filter);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getFilters')->willReturn($filterCollection);

        $subscriber = new DoctrineCustomerFilterSubscriber($security, $entityManager);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertTrue($filter->hasParameter('customer_id'));
        self::assertSame("'42'", $filter->getParameter('customer_id'));
    }

    #[Test]
    public function subscriberDisablesFilterForAdminUser(): void
    {
        $user = new User('admin@system.com');
        $user->assignRole(UserRole::ADMIN);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $filterCollection = $this->createMock(FilterCollection::class);
        $filterCollection->method('has')->with('customer_tenant')->willReturn(true);
        $filterCollection->method('isEnabled')->with('customer_tenant')->willReturn(true);
        $filterCollection->expects(self::once())->method('disable')->with('customer_tenant');
        $filterCollection->expects(self::never())->method('enable');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getFilters')->willReturn($filterCollection);

        $subscriber = new DoctrineCustomerFilterSubscriber($security, $entityManager);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
    }

    #[Test]
    public function subscriberDisablesFilterWhenNoUser(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $filterCollection = $this->createMock(FilterCollection::class);
        $filterCollection->method('has')->with('customer_tenant')->willReturn(true);
        $filterCollection->method('isEnabled')->with('customer_tenant')->willReturn(true);
        $filterCollection->expects(self::once())->method('disable')->with('customer_tenant');
        $filterCollection->expects(self::never())->method('enable');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getFilters')->willReturn($filterCollection);

        $subscriber = new DoctrineCustomerFilterSubscriber($security, $entityManager);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
    }

    #[Test]
    public function subscriberIgnoresSubRequests(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('getUser');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getFilters');

        $subscriber = new DoctrineCustomerFilterSubscriber($security, $entityManager);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $subscriber->onKernelRequest($event);
    }

    #[Test]
    public function subscriberDoesNothingWhenFilterNotRegistered(): void
    {
        $security = $this->createMock(Security::class);

        $filterCollection = $this->createMock(FilterCollection::class);
        $filterCollection->method('has')->with('customer_tenant')->willReturn(false);
        $filterCollection->expects(self::never())->method('enable');
        $filterCollection->expects(self::never())->method('disable');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getFilters')->willReturn($filterCollection);

        $subscriber = new DoctrineCustomerFilterSubscriber($security, $entityManager);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
    }

    #[Test]
    public function subscriberDisablesFilterForCustomerUserWithoutCustomerAssociation(): void
    {
        // A user with ROLE_CUSTOMER but no customer entity attached
        $user = new User('orphan@customer.com');
        $user->assignRole(UserRole::CUSTOMER);
        // No customer set

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $filterCollection = $this->createMock(FilterCollection::class);
        $filterCollection->method('has')->with('customer_tenant')->willReturn(true);
        $filterCollection->method('isEnabled')->with('customer_tenant')->willReturn(true);
        $filterCollection->expects(self::once())->method('disable')->with('customer_tenant');
        $filterCollection->expects(self::never())->method('enable');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getFilters')->willReturn($filterCollection);

        $subscriber = new DoctrineCustomerFilterSubscriber($security, $entityManager);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
    }

    #[Test]
    public function shipmentImplementsCustomerScopedInterface(): void
    {
        // Verify that Shipment entity opts into the tenant filter
        $interfaces = class_implements(Shipment::class);
        self::assertArrayHasKey(CustomerScopedEntityInterface::class, $interfaces);
    }

    #[Test]
    public function customerEntityDoesNotImplementScopedInterface(): void
    {
        // Customer itself should not be filtered by customer_id
        $interfaces = class_implements(Customer::class);
        self::assertArrayNotHasKey(CustomerScopedEntityInterface::class, $interfaces);
    }
}
