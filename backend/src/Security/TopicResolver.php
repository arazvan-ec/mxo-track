<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

class TopicResolver
{
    /**
     * @return list<string>
     */
    public function resolveForUser(User $user, array $allowedVehicleIds = []): array
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_OPERATOR', $roles, true)) {
            return ['/*'];
        }

        if (in_array('ROLE_CUSTOMER', $roles, true) && $user->getCustomer() !== null) {
            $customerId = (string) $user->getCustomer()?->getId();
            $vehicleTopics = array_map(
                static fn (string $id): string => sprintf('/vehicles/%s/position', $id),
                array_values(array_unique($allowedVehicleIds))
            );

            return [
                ...$vehicleTopics,
                sprintf('/customers/%s/routes', $customerId),
                sprintf('/customers/%s/shipments', $customerId),
            ];
        }

        if (in_array('ROLE_DRIVER', $roles, true)) {
            $topics = [];
            foreach (array_unique($allowedVehicleIds) as $id) {
                $topics[] = sprintf('/vehicles/%s/position', $id);
            }
            return $topics;
        }

        return [];
    }
}
