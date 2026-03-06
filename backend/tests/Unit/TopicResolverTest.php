<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
use App\Entity\User;
use App\Enum\UserRole;
use App\Security\TopicResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopicResolver::class)]
final class TopicResolverTest extends TestCase
{
    private TopicResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TopicResolver();
    }

    #[Test]
    public function adminGetsWildcardTopics(): void
    {
        $user = new User('admin@test.com');
        $user->assignRole(UserRole::ADMIN);

        $topics = $this->resolver->resolveForUser($user);

        self::assertSame(['*'], $topics);
    }

    #[Test]
    public function adminWithVehiclesStillGetsOnlyWildcard(): void
    {
        $user = new User('admin@test.com');
        $user->assignRole(UserRole::ADMIN);

        $topics = $this->resolver->resolveForUser($user, ['vehicle-1', 'vehicle-2']);

        self::assertSame(['*'], $topics);
    }

    #[Test]
    public function customerGetsVehicleAndCustomerTopics(): void
    {
        $customer = new Customer('Tenant A');
        $customer->initializePublicId();

        $user = new User('customer@test.com');
        $user->assignRole(UserRole::CUSTOMER);
        $user->setCustomer($customer);

        $vehiclePublicIds = ['01HX1234ABCDEF5678900000'];
        $topics = $this->resolver->resolveForUser($user, $vehiclePublicIds);

        self::assertContains('/vehicles/01HX1234ABCDEF5678900000/position', $topics);
        self::assertContains(sprintf('/customers/%s/routes', $customer->getPublicIdString()), $topics);
        self::assertContains(sprintf('/customers/%s/shipments', $customer->getPublicIdString()), $topics);
        self::assertContains(sprintf('/users/%s/notifications', $user->getId()), $topics);
        self::assertCount(4, $topics);
    }

    #[Test]
    public function customerWithNoVehiclesGetsOnlyCustomerTopics(): void
    {
        $customer = new Customer('Tenant B');
        $customer->initializePublicId();

        $user = new User('customer@test.com');
        $user->assignRole(UserRole::CUSTOMER);
        $user->setCustomer($customer);

        $topics = $this->resolver->resolveForUser($user);

        self::assertCount(3, $topics);
        self::assertContains(sprintf('/customers/%s/routes', $customer->getPublicIdString()), $topics);
        self::assertContains(sprintf('/customers/%s/shipments', $customer->getPublicIdString()), $topics);
        self::assertContains(sprintf('/users/%s/notifications', $user->getId()), $topics);
    }

    #[Test]
    public function customerWithoutCustomerEntityGetsEmptyTopics(): void
    {
        $user = new User('orphan@test.com');
        $user->assignRole(UserRole::CUSTOMER);
        // No customer set

        $topics = $this->resolver->resolveForUser($user);

        self::assertEmpty($topics);
    }

    #[Test]
    public function driverGetsVehicleTopics(): void
    {
        $user = new User('driver@test.com');
        $user->assignRole(UserRole::DRIVER);

        $vehiclePublicIds = ['01HX1111ABCDEF0000000000', '01HX2222ABCDEF0000000000'];
        $topics = $this->resolver->resolveForUser($user, $vehiclePublicIds);

        self::assertCount(3, $topics);
        self::assertContains('/vehicles/01HX1111ABCDEF0000000000/position', $topics);
        self::assertContains('/vehicles/01HX2222ABCDEF0000000000/position', $topics);
        self::assertContains(sprintf('/users/%s/notifications', $user->getId()), $topics);
    }

    #[Test]
    public function driverWithNoVehiclesGetsNotificationsOnly(): void
    {
        $user = new User('driver@test.com');
        $user->assignRole(UserRole::DRIVER);

        $topics = $this->resolver->resolveForUser($user);

        self::assertCount(1, $topics);
        self::assertContains(sprintf('/users/%s/notifications', $user->getId()), $topics);
    }

    #[Test]
    public function userWithNoRolesGetsEmptyTopics(): void
    {
        $user = new User('basic@test.com');
        $user->setRoles([]);

        $topics = $this->resolver->resolveForUser($user);

        self::assertEmpty($topics);
    }

    #[Test]
    public function driverWithDuplicateVehicleIdsDeduplicates(): void
    {
        $user = new User('driver@test.com');
        $user->assignRole(UserRole::DRIVER);

        $vehiclePublicIds = ['01HX1111ABCDEF0000000000', '01HX1111ABCDEF0000000000'];
        $topics = $this->resolver->resolveForUser($user, $vehiclePublicIds);

        self::assertCount(2, $topics);
        self::assertContains('/vehicles/01HX1111ABCDEF0000000000/position', $topics);
        self::assertContains(sprintf('/users/%s/notifications', $user->getId()), $topics);
    }
}
