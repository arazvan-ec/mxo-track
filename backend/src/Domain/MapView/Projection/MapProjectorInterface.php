<?php

declare(strict_types=1);

namespace App\Domain\MapView\Projection;

use App\Domain\Event\VehiclePositionReceived;
use App\Domain\MapView\Model\MapUpdate;
use App\Domain\MapView\Model\VehiclePosition;

interface MapProjectorInterface
{
    /**
     * @return list<MapUpdate>
     */
    public function projectRouteEvent(MapProjectableEventInterface $event): array;

    public function projectVehiclePosition(VehiclePositionReceived $event): VehiclePosition;
}
