<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\LoadingManifestItem;
use App\Entity\Route;
use App\Entity\RouteStop;
use Doctrine\ORM\EntityManagerInterface;

final class LoadingManifestGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return list<LoadingManifestItem>
     */
    public function generateManifest(Route $route): array
    {
        /** @var RouteStop[] $stops */
        $stops = $this->em->createQuery(
            'SELECT rs FROM App\Entity\RouteStop rs
             WHERE rs.route = :route AND rs.isOrigin = false AND rs.shipment IS NOT NULL
             ORDER BY rs.sequence ASC'
        )
            ->setParameter('route', $route)
            ->getResult();

        // Reverse: last delivery loaded first (LIFO)
        $reversed = array_reverse($stops);

        $manifest = [];
        $loadingOrder = 1;

        foreach ($reversed as $stop) {
            $shipment = $stop->getShipment();
            if ($shipment === null) {
                continue;
            }

            $manifest[] = new LoadingManifestItem(
                loadingOrder: $loadingOrder,
                deliverySequence: $stop->getSequence(),
                shipmentPublicId: (string) $shipment->getPublicId(),
                shipmentReference: $shipment->getReference(),
                recipientName: $stop->getRecipientName(),
                address: $stop->getAddress(),
                recipientPhone: $stop->getRecipientPhone(),
                weightKg: null,
                volumeM3: null,
                parcels: null,
            );

            $loadingOrder++;
        }

        return $manifest;
    }
}
