<?php

declare(strict_types=1);

namespace App\Domain\MapView\Publisher;

use App\Domain\MapView\Model\MapUpdate;
use App\Domain\MapView\Model\VehiclePosition;

interface MapPublisherInterface
{
    public function publishRouteUpdate(MapUpdate $update): void;

    public function publishVehiclePosition(VehiclePosition $position): void;
}
