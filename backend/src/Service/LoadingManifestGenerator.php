<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\LoadingManifestItem;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use Doctrine\ORM\EntityManagerInterface;

final class LoadingManifestGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Generate a LIFO loading manifest for a route.
     *
     * Delivery stops are returned in reverse sequence order so that the last
     * delivery is loaded first (closest to the truck door).
     *
     * @return list<LoadingManifestItem>
     */
    public function generateManifest(Route $route): array
    {
        /** @var list<RouteStop> $stops */
        $stops = $this->em->createQuery(
            'SELECT rs FROM App\Domain\Route\Model\RouteStop rs
             WHERE rs.route = :route AND rs.isOrigin = false AND rs.shipment IS NOT NULL
             ORDER BY rs.sequence ASC'
        )
            ->setParameter('route', $route)
            ->getResult();

        $reversed = array_reverse($stops);

        $manifest = [];
        $loadingOrder = 1;

        foreach ($reversed as $stop) {
            $shipment = $stop->getShipment();

            $warnings = [];
            $skills = $shipment->getRequiredSkills();
            if (!empty($skills)) {
                $warnings[] = 'Requiere: ' . implode(', ', array_map(fn ($s) => $s->value, $skills));
            }
            if ($shipment->getTotalWeightKg() !== null && $shipment->getTotalWeightKg() > 50.0) {
                $warnings[] = 'Paquete pesado (' . number_format($shipment->getTotalWeightKg(), 1) . ' kg) — usar carretilla';
            }

            $manifest[] = new LoadingManifestItem(
                loadingOrder: $loadingOrder,
                deliverySequence: $stop->getSequence(),
                shipmentPublicId: $shipment->getPublicIdString(),
                shipmentReference: $shipment->getReference(),
                recipientName: $stop->getRecipientName(),
                address: $stop->getAddress(),
                recipientPhone: $stop->getRecipientPhone(),
                weightKg: $shipment->getTotalWeightKg(),
                volumeM3: $shipment->getTotalVolumeM3(),
                parcels: $shipment->getTotalParcels(),
                serviceTimeSeconds: $shipment->getServiceTimeSeconds(),
                requiredSkills: array_map(fn ($s) => $s->value, $skills),
                aiNotes: $stop->getAiNotes(),
                warnings: $warnings,
            );

            $loadingOrder++;
        }

        return $manifest;
    }
}
