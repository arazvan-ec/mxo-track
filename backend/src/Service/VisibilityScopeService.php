<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerVehicle;
use App\Entity\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class VisibilityScopeService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return list<string> */
    public function vehicleIdsFor(User $user): array
    {
        if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_OPERATOR')) {
            return [];
        }

        if ($user->hasRole('ROLE_CUSTOMER') && $user->getCustomer() !== null) {
            $rows = $this->entityManager->getRepository(CustomerVehicle::class)->findBy([
                'customer' => $user->getCustomer(),
            ]);

            return array_values(array_map(
                static fn (CustomerVehicle $cv): string => (string) $cv->getVehicle()->getId(),
                $rows,
            ));
        }

        if ($user->hasRole('ROLE_DRIVER')) {
            $routes = $this->entityManager->getRepository(Route::class)->findBy(['driver' => $user]);
            $ids = [];
            foreach ($routes as $route) {
                $vehicle = $route->getVehicle();
                if ($vehicle !== null) {
                    $ids[] = (string) $vehicle->getId();
                }
            }

            return array_values(array_unique($ids));
        }

        return [];
    }

    public function canAccessVehicle(User $user, string $vehicleId): bool
    {
        if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_OPERATOR')) {
            return true;
        }

        return in_array($vehicleId, $this->vehicleIdsFor($user), true);
    }
}
