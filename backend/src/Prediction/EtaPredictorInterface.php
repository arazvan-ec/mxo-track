<?php

declare(strict_types=1);

namespace App\Prediction;

interface EtaPredictorInterface
{
    /**
     * Predict the estimated time of arrival for a single route stop.
     *
     * @param string $routeStopId Public ID of the route stop
     * @param array{latitude: float, longitude: float} $currentPosition Current vehicle position
     * @param array<string, mixed> $trafficData Optional traffic context data
     */
    public function predictEta(string $routeStopId, array $currentPosition, array $trafficData = []): EtaPrediction;

    /**
     * Predict ETAs for multiple route stops in a single batch call.
     *
     * @param list<array{routeStopId: string, currentPosition: array{latitude: float, longitude: float}, trafficData?: array<string, mixed>}> $stops
     * @return list<EtaPrediction>
     */
    public function predictBatchEta(array $stops): array;
}
