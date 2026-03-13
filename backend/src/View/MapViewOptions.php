<?php

declare(strict_types=1);

namespace App\View;

final class MapViewOptions
{
    /**
     * @param array{lat: float, lng: float, speed?: float, course?: float}|null $vehiclePosition
     * @param array<int, array<string, mixed>>|null $optimizationLog
     */
    public function __construct(
        public readonly bool $showOptimizationMetrics = false,
        public readonly bool $showTimingBreakdown = false,
        public readonly bool $showVehicleTracking = false,
        public readonly bool $showStopStatus = true,
        public readonly bool $showCapacityValidation = false,
        public readonly bool $showOriginalOrder = false,
        public readonly bool $showPolylines = true,
        public readonly bool $showOptimizationLog = false,
        public readonly ?string $comparisonMode = null,
        public readonly ?string $vehiclePublicId = null,
        public readonly ?array $vehiclePosition = null,
        public readonly ?array $optimizationLog = null,
    ) {}
}
