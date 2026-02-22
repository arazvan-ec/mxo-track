<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RouteStop;
use App\Entity\ShipmentEvent;
use App\Entity\VehicleLastPosition;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicTrackingController extends AbstractController
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/track/{trackingToken}', name: 'public_tracking', methods: ['GET'])]
    public function track(string $trackingToken): Response
    {
        // Validate token format to prevent enumeration
        if (!preg_match('/^TRK-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $trackingToken)) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        // Disable tenant filter for public access (no user session)
        $filters = $this->em->getFilters();
        if ($filters->isEnabled('customer_tenant')) {
            $filters->disable('customer_tenant');
        }

        $shipment = $this->shipmentRepository->findOneByTrackingToken($trackingToken);

        if ($shipment === null) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        // Load events for timeline
        $events = $this->em->createQueryBuilder()
            ->select('se')
            ->from(ShipmentEvent::class, 'se')
            ->where('se.shipment = :shipment')
            ->setParameter('shipment', $shipment)
            ->orderBy('se.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Determine current status
        $latestEvent = null;
        if (\count($events) > 0) {
            $latestEvent = $events[\count($events) - 1];
        }

        // Find associated route stop to get route + vehicle info
        $routeStop = $this->em->createQueryBuilder()
            ->select('rs')
            ->from(RouteStop::class, 'rs')
            ->where('rs.shipment = :shipment')
            ->setParameter('shipment', $shipment)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Get approximate vehicle position if route is active (anonymized to ~500m)
        $approximatePosition = null;
        if ($routeStop !== null) {
            $route = $routeStop->getRoute();
            if ($route->getStatus()->value === 'ACTIVE' && $route->getVehicle() !== null) {
                $lastPos = $this->em->createQueryBuilder()
                    ->select('vlp')
                    ->from(VehicleLastPosition::class, 'vlp')
                    ->where('vlp.vehicle = :vehicle')
                    ->setParameter('vehicle', $route->getVehicle())
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($lastPos !== null) {
                    // Anonymize to ~500m: round to 3 decimal places + random offset
                    $approximatePosition = [
                        'lat' => round($lastPos->getLat(), 3),
                        'lng' => round($lastPos->getLng(), 3),
                    ];
                }
            }
        }

        return $this->render('tracking/public.html.twig', [
            'shipment' => $shipment,
            'events' => $events,
            'latestEvent' => $latestEvent,
            'approximatePosition' => $approximatePosition,
            'routeActive' => $routeStop !== null && $routeStop->getRoute()->getStatus()->value === 'ACTIVE',
        ]);
    }
}
