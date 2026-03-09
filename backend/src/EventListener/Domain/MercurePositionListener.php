<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\VehiclePositionReceived;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Publishes vehicle position updates to Mercure.
 * This will replace the inline Mercure publish in TraccarIngestionService
 * once TraccarIngestionService dispatches VehiclePositionReceived events.
 */
final readonly class MercurePositionListener
{
    public function __construct(
        private HubInterface $hub,
    ) {}

    #[AsEventListener]
    public function onVehiclePositionReceived(VehiclePositionReceived $event): void
    {
        try {
            $this->hub->publish(new Update(
                sprintf('/vehicles/%s/position', $event->vehiclePublicId),
                json_encode([
                    'vehicleId' => $event->vehiclePublicId,
                    'lat' => $event->latitude,
                    'lng' => $event->longitude,
                    'speed' => $event->speed,
                    'course' => $event->course,
                    'deviceTime' => $event->deviceTime->format(\DATE_ATOM),
                ], JSON_THROW_ON_ERROR),
            ));
        } catch (Throwable) {
            // Don't break ingestion on Mercure failure
        }
    }
}
