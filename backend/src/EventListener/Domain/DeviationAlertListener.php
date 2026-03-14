<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\DeviationDetected;
use App\Entity\RealtimeEvent;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class DeviationAlertListener
{
    public function __construct(
        private RouteRepository $routeRepo,
        private EntityManagerInterface $em,
    ) {}

    #[AsEventListener]
    public function onDeviationDetected(DeviationDetected $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if ($route === null) {
            return;
        }

        $customer = $route->getCustomer();
        if ($customer === null) {
            return;
        }

        $realtimeEvent = new RealtimeEvent(
            customer: $customer,
            topic: sprintf('/routes/%s/deviation', $event->routePublicId),
            data: [
                'route_public_id' => $event->routePublicId,
                'route_name' => $route->getName(),
                'vehicle_public_id' => $event->vehiclePublicId,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'distance_meters' => round($event->distanceMeters, 1),
                'message' => sprintf(
                    'Vehículo se desvió %.0fm de la ruta "%s"',
                    $event->distanceMeters,
                    $route->getName(),
                ),
            ],
            eventType: 'deviation_detected',
        );

        $this->em->persist($realtimeEvent);
        $this->em->flush();
    }
}
