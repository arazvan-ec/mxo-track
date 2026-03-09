<?php

declare(strict_types=1);

namespace App\Application\Route;

final readonly class BuildRoutesInput
{
    /**
     * @param string[] $shipmentPublicIds
     * @param string[] $vehiclePublicIds
     * @param array<string, mixed>|null $clusterHints Optional clustering metadata from zone grouping
     *        Format: list of {centroid: {lat, lng}, shipmentIds: [...], color: string}
     */
    public function __construct(
        public array $shipmentPublicIds,
        public array $vehiclePublicIds,
        public ?string $originPublicId = null,
        public int $maxStopsPerRoute = 30,
        public ?array $clusterHints = null,
    ) {}
}
