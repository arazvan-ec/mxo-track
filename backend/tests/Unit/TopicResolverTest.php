<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\RouteRepository;
use App\Security\TopicResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopicResolver::class)]
final class TopicResolverTest extends TestCase
{
    private TopicResolver $resolver;
    private RouteRepository $routeRepo;

    protected function setUp(): void
    {
        $this->routeRepo = $this->createMock(RouteRepository::class);
        $this->routeRepo->method('findActiveRoutePublicIdsForCustomer')->willReturn([]);
        $this->routeRepo->method('findActiveRoutePublicIdsForDriver')->willReturn([]);

        $this->resolver = new TopicResolver($this->routeRepo);
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
    public function customerGetsVehicleAndRouteTopics(): void
    {
        $customer = new Customer('Tenant A');
        $customer->initializePublicId();

        $user = new User('customer@test.com');
        $user->assignRole(UserRole::CUSTOMER);
        $user->setCustomer($customer);

        $this->routeRepo = $this->createMock(RouteRepository::class);
        $this->routeRepo->method('findActiveRoutePublicIdsForCustomer')->willReturn(['route-abc']);
        $this->routeRepo->method('findActiveRoutePublicIdsForDriver')->willReturn([]);
        $this->resolver = new TopicResolver($this->routeRepo);

        $vehiclePublicIds = ['01HX1234ABCDEF5678900000'];
        $topics = $this->resolver->resolveForUser($user, $vehiclePublicIds);

        self::assertContains('/map/vehicles/01HX1234ABCDEF5678900000/position', $topics);
        self::assertContains('/map/routes/route-abc/updates', $topics);
        self::assertContains(sprintf('/map/users/%s/notifications', $user->getId()), $topics);
        self::assertCount(3, $topics);
    }

    #[Test]
    public function customerWithNoVehiclesGetsOnlyNotificationAndRouteTopics(): void
    {
        $customer = new Customer('Tenant B');
        $customer->initializePublicId();

        $user = new User('customer@test.com');
        $user->assignRole(UserRole::CUSTOMER);
        $user->setCustomer($customer);

        $topics = $this->resolver->resolveForUser($user);

        self::assertCount(1, $topics);
        self::assertContains(sprintf('/map/users/%s/notifications', $user->getId()), $topics);
    }

    #[Test]
    public function customerWithoutCustomerEntityGetsEmptyTopics(): void
    {
        $user = new User('orphan@test.com');
        $user->assignRole(UserRole::CUSTOMER);

        $topics = $this->resolver->resolveForUser($user);

        self::assertEmpty($topics);
    }

    #[Test]
    public function driverGetsVehicleAndRouteTopics(): void
    {
        $user = new User('driver@test.com');
        $user->assignRole(UserRole::DRIVER);

        $this->routeRepo = $this->createMock(RouteRepository::class);
        $this->routeRepo->method('findActiveRoutePublicIdsForCustomer')->willReturn([]);
        $this->routeRepo->method('findActiveRoutePublicIdsForDriver')->willReturn(['route-xyz']);
        $this->resolver = new TopicResolver($this->routeRepo);

        $vehiclePublicIds = ['01HX1111ABCDEF0000000000', '01HX2222ABCDEF0000000000'];
        $topics = $this->resolver->resolveForUser($user, $vehiclePublicIds);

        self::assertCount(4, $topics);
        self::assertContains(sprintf('/map/users/%s/notifications', $user->getId()), $topics);
        self::assertContains('/map/vehicles/01HX1111ABCDEF0000000000/position', $topics);
        self::assertContains('/map/vehicles/01HX2222ABCDEF0000000000/position', $topics);
        self::assertContains('/map/routes/route-xyz/updates', $topics);
    }

    #[Test]
    public function driverWithNoVehiclesGetsNotificationTopics(): void
    {
        $user = new User('driver@test.com');
        $user->assignRole(UserRole::DRIVER);

        $topics = $this->resolver->resolveForUser($user);

        self::assertCount(1, $topics);
        self::assertContains(sprintf('/map/users/%s/notifications', $user->getId()), $topics);
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
        self::assertContains(sprintf('/map/users/%s/notifications', $user->getId()), $topics);
        self::assertContains('/map/vehicles/01HX1111ABCDEF0000000000/position', $topics);
    }
}
