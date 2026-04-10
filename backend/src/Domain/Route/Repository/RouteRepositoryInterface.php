<?php

declare(strict_types=1);

namespace App\Domain\Route\Repository;

use App\Entity\Customer;
use App\Entity\Vehicle;
use App\Domain\Route\Model\Route;
use App\Entity\User;

interface RouteRepositoryInterface
{
    public function findOneByPublicId(string $publicId): ?Route;

    /**
     * @return list<string> Public IDs of active/planned routes for a customer
     */
    public function findActiveRoutePublicIdsForCustomer(Customer $customer): array;

    /**
     * @return list<string> Public IDs of active routes assigned to a driver
     */
    public function findActiveRoutePublicIdsForDriver(User $driver): array;

    /**
     * Returns the most recent route assigned to a vehicle (by creation date).
     */
    public function findLastByVehicle(Vehicle $vehicle): ?Route;

    public function save(Route $route): void;

    public function remove(Route $route): void;

    public function flush(): void;
}
