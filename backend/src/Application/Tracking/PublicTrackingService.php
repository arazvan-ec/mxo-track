<?php

declare(strict_types=1);

namespace App\Application\Tracking;

use App\Entity\RouteStop;
use App\Entity\ShipmentEvent;
use App\Entity\VehicleLastPosition;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PublicTrackingService
{
    public function __construct(
        private ShipmentRepository $shipmentRepo,
        private EntityManagerInterface $em,
    ) {}

    public function trackByToken(string $trackingToken): ?TrackingInfo
    {
        // Validate token format to prevent enumeration
        if (!preg_match('/^TRK-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $trackingToken)) {
            return null;
        }

        // Disable tenant filter for public access (no user session)
        $filters = $this->em->getFilters();
        if ($filters->isEnabled('customer_tenant')) {
            $filters->disable('customer_tenant');
        }

        $shipment = $this->shipmentRepo->findOneByTrackingToken($trackingToken);

        if ($shipment === null) {
            return null;
        }

        // Load events timeline
        $events = $this->em->createQueryBuilder()
            ->select('se')
            ->from(ShipmentEvent::class, 'se')
            ->where('se.shipment = :shipment')
            ->setParameter('shipment', $shipment)
            ->orderBy('se.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $latestEvent = \count($events) > 0 ? $events[\count($events) - 1] : null;

        // Find associated route stop
        $routeStop = $this->em->createQueryBuilder()
            ->select('rs')
            ->from(RouteStop::class, 'rs')
            ->where('rs.shipment = :shipment')
            ->setParameter('shipment', $shipment)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Get approximate vehicle position if route is active (anonymized to ~111m)
        $approximatePosition = null;
        $routeActive = false;

        if ($routeStop !== null) {
            $route = $routeStop->getRoute();
            $routeActive = $route->getStatus()->value === 'ACTIVE';

            if ($routeActive && $route->getVehicle() !== null) {
                $lastPos = $this->em->createQueryBuilder()
                    ->select('vlp')
                    ->from(VehicleLastPosition::class, 'vlp')
                    ->where('vlp.vehicle = :vehicle')
                    ->setParameter('vehicle', $route->getVehicle())
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($lastPos !== null) {
                    $approximatePosition = [
                        'lat' => round($lastPos->getLat(), 3),
                        'lng' => round($lastPos->getLng(), 3),
                    ];
                }
            }
        }

        return new TrackingInfo(
            shipment: $shipment,
            events: $events,
            latestEvent: $latestEvent,
            approximatePosition: $approximatePosition,
            routeActive: $routeActive,
        );
    }
}
