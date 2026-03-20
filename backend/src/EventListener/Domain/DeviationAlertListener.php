<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\DeviationDetected;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;
use App\Repository\RouteRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class DeviationAlertListener
{
    public function __construct(
        private RouteRepository $routeRepo,
        private RealtimePublisherInterface $publisher,
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

        try {
            $this->publisher->publish(new SseMessage(
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
                topics: [sprintf('/routes/%s/deviation', $event->routePublicId)],
                type: 'deviation_detected',
            ));
        } catch (\Throwable) {
            // Don't break the event flow on publish failure
        }
    }
}
