<?php

declare(strict_types=1);

namespace App\Dto;

final class RouteAnalysisResult
{
    public function __construct(
        public readonly string $routePublicId,
        public readonly string $routeName,
        public readonly ?string $vehicleName,
        public readonly ?string $driverName,
        public readonly ?int $actualDurationMinutes,
        public readonly float $sequenceAdherence,
        public readonly ?float $avgActualServiceTimeSeconds,
        /** @var list<StopAnalysis> */
        public readonly array $stops,
        /** @var list<string> */
        public readonly array $recommendations,
    ) {}
}
