<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Customer;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Factory for creating test entities with sensible defaults.
 * Used in functional tests that require database state.
 */
final class TestEntityFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function createCustomer(string $name = 'Test Customer'): Customer
    {
        $customer = new Customer($name);
        $customer->initializePublicId();
        $this->entityManager->persist($customer);

        return $customer;
    }

    public function createUser(
        string $email,
        UserRole $role = UserRole::ADMIN,
        ?Customer $customer = null,
        string $password = 'test1234',
    ): User {
        $user = new User($email);
        $user->initializePublicId();
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->assignRole($role);
        if ($customer !== null) {
            $user->setCustomer($customer);
        }

        $this->entityManager->persist($user);

        return $user;
    }

    public function createDriver(
        string $email = 'driver@test.com',
        ?Customer $customer = null,
        string $password = 'test1234',
    ): User {
        return $this->createUser($email, UserRole::DRIVER, $customer, $password);
    }

    public function createVehicle(string $name = 'Test Vehicle'): Vehicle
    {
        $vehicle = new Vehicle($name);
        $vehicle->initializePublicId();
        $this->entityManager->persist($vehicle);

        return $vehicle;
    }

    public function createRoute(
        string $name = 'Test Route',
        ?User $driver = null,
        ?Vehicle $vehicle = null,
        ?Customer $customer = null,
        RouteStatus $status = RouteStatus::PLANNED,
    ): Route {
        $route = new Route($name);
        $route->initializePublicId();
        $route->setStatus($status);

        if ($driver !== null) {
            $route->setDriver($driver);
        }
        if ($vehicle !== null) {
            $route->setVehicle($vehicle);
        }
        if ($customer !== null) {
            $route->setCustomer($customer);
        }

        $this->entityManager->persist($route);

        return $route;
    }

    public function createRouteStop(
        Route $route,
        int $sequence = 1,
        string $address = 'Calle Mayor 1, Madrid',
    ): RouteStop {
        $stop = new RouteStop($route, $sequence, $address);
        $stop->initializePublicId();
        $stop->setLatitude(40.4168);
        $stop->setLongitude(-3.7038);

        $this->entityManager->persist($stop);

        return $stop;
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
