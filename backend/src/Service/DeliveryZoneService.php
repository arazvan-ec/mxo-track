<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\DeliveryZone;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Computes delivery zones via the ML sidecar (K-means clustering)
 * and persists them as DeliveryZone entities.
 */
final class DeliveryZoneService
{
    public function __construct(
        private readonly MlApiClient $mlApiClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Compute delivery zones and store them.
     *
     * @return list<DeliveryZone> The newly created zones.
     */
    public function computeZones(?int $customerId = null, int $nClusters = 5): array
    {
        $result = $this->mlApiClient->predict('cluster/delivery-zones', [
            'n_clusters' => $nClusters,
        ]);

        if ($result === null || !isset($result['zones']) || !\is_array($result['zones'])) {
            $this->logger->warning('Delivery zone clustering failed — ML sidecar unavailable or returned invalid data');

            return [];
        }

        $customer = null;
        if ($customerId !== null) {
            $customer = $this->em->getRepository(Customer::class)->find($customerId);
        }

        // Remove old zones for this customer (or global if null)
        $this->removeExistingZones($customer);

        $zones = [];
        foreach ($result['zones'] as $zoneData) {
            $zone = new DeliveryZone(
                name: (string) ($zoneData['suggested_name'] ?? 'Zona'),
                centerLat: (float) ($zoneData['center_lat'] ?? 0.0),
                centerLng: (float) ($zoneData['center_lng'] ?? 0.0),
                radiusKm: (float) ($zoneData['radius_km'] ?? 1.0),
                deliveryCount: (int) ($zoneData['delivery_count'] ?? 0),
            );
            $zone->setCustomer($customer);

            $this->em->persist($zone);
            $zones[] = $zone;
        }

        $this->em->flush();

        $this->logger->info('Computed {count} delivery zones', [
            'count' => \count($zones),
            'customer_id' => $customerId,
        ]);

        return $zones;
    }

    private function removeExistingZones(?Customer $customer): void
    {
        $qb = $this->em->createQueryBuilder()
            ->delete(DeliveryZone::class, 'dz');

        if ($customer !== null) {
            $qb->where('dz.customer = :customer')
                ->setParameter('customer', $customer);
        } else {
            $qb->where('dz.customer IS NULL');
        }

        $qb->getQuery()->execute();
    }
}
