<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\RouteRepository;

class TopicResolver
{
    public function __construct(
        private readonly RouteRepository $routeRepo,
    ) {}

    /**
     * @param list<string> $allowedVehiclePublicIds
     *
     * @return list<string>
     */
    public function resolveForUser(User $user, array $allowedVehiclePublicIds = []): array
    {
        $roles = $user->getRoles();

        // ROLE_ADMIN and ROLE_OPERATOR get full access (role hierarchy: ADMIN > OPERATOR > CUSTOMER)
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_OPERATOR', $roles, true)) {
            return ['*'];
        }

        if (in_array('ROLE_CUSTOMER', $roles, true) && $user->getCustomer() !== null) {
            $topics = [
                sprintf('/map/users/%s/notifications', $user->getId()),
            ];

            foreach (array_values(array_unique($allowedVehiclePublicIds)) as $publicId) {
                $topics[] = sprintf('/map/vehicles/%s/position', $publicId);
            }

            $customerRoutePublicIds = $this->routeRepo->findActiveRoutePublicIdsForCustomer($user->getCustomer());
            foreach ($customerRoutePublicIds as $routePublicId) {
                $topics[] = sprintf('/map/routes/%s/updates', $routePublicId);
            }

            return $topics;
        }

        if (in_array('ROLE_DRIVER', $roles, true)) {
            $topics = [
                sprintf('/map/users/%s/notifications', $user->getId()),
            ];

            foreach (array_unique($allowedVehiclePublicIds) as $publicId) {
                $topics[] = sprintf('/map/vehicles/%s/position', $publicId);
            }

            $assignedRoutePublicIds = $this->routeRepo->findActiveRoutePublicIdsForDriver($user);
            foreach ($assignedRoutePublicIds as $routePublicId) {
                $topics[] = sprintf('/map/routes/%s/updates', $routePublicId);
            }

            return $topics;
        }

        return [];
    }
}
