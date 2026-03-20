<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Repository;

use App\Entity\Shipment;

interface ShipmentRepositoryInterface
{
    public function findOneByPublicId(string $publicId): ?Shipment;

    public function findOneByTrackingToken(string $trackingToken): ?Shipment;

    public function save(Shipment $shipment): void;

    public function remove(Shipment $shipment): void;

    public function flush(): void;
}
