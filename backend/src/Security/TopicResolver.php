<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

class TopicResolver
{
    /**
     * @param list<string> $allowedVehiclePublicIds
     *
     * @return list<string>
     */
    public function resolveForUser(User $user, array $allowedVehiclePublicIds = []): array
    {
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return ['*'];
        }

        if (in_array('ROLE_CUSTOMER', $roles, true) && $user->getCustomer() !== null) {
            $customerPublicId = $user->getCustomer()?->getPublicIdString();
            $vehicleTopics = array_map(
                static fn (string $publicId): string => sprintf('/vehicles/%s/position', $publicId),
                array_values(array_unique($allowedVehiclePublicIds))
            );

            return [
                ...$vehicleTopics,
                sprintf('/customers/%s/routes', $customerPublicId),
                sprintf('/customers/%s/shipments', $customerPublicId),
                sprintf('/users/%s/notifications', $user->getId()),
            ];
        }

        if (in_array('ROLE_DRIVER', $roles, true)) {
            $topics = [sprintf('/users/%s/notifications', $user->getId())];
            foreach (array_unique($allowedVehiclePublicIds) as $publicId) {
                $topics[] = sprintf('/vehicles/%s/position', $publicId);
            }

            return $topics;
        }

        return [];
    }
}
