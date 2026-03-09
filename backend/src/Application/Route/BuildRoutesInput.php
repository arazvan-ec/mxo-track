<?php

declare(strict_types=1);

namespace App\Application\Route;

final readonly class BuildRoutesInput
{
    /**
     * @param string[] $shipmentPublicIds
     * @param string[] $vehiclePublicIds
     */
    public function __construct(
        public array $shipmentPublicIds,
        public array $vehiclePublicIds,
        public ?string $originPublicId = null,
        public int $maxStopsPerRoute = 30,
    ) {}
}
