<?php

declare(strict_types=1);

namespace App\Application\Tracking;

use App\Domain\Shipment\Model\Shipment;
use App\Domain\Shipment\Model\ShipmentEvent;

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
        public ?int $etaMinutes = null,
        public ?string $etaTime = null,
        public ?float $etaDistanceKm = null,
    ) {}
}
