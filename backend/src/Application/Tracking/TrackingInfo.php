<?php

declare(strict_types=1);

namespace App\Application\Tracking;

use App\Entity\Shipment;
use App\Entity\ShipmentEvent;

final readonly class TrackingInfo
{
    /**
     * @param ShipmentEvent[] $events
     * @param array{lat: float, lng: float}|null $approximatePosition
     */
    public function __construct(
        public Shipment $shipment,
        public array $events,
        public ?ShipmentEvent $latestEvent,
        public ?array $approximatePosition,
        public bool $routeActive,
    ) {}
}
