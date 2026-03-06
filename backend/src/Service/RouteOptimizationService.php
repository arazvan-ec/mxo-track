<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Re-optimizes the stop order of an existing route using VROOM.
 * Uses OSRM for real road distances and durations.
 */
final class RouteOptimizationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VroomApiClient $vroomClient,
        private readonly OsrmClient $osrmClient,
    ) {}

    /**
     * Optimizes the stop order of a route using VROOM + OSRM.
     *
     * @return array{
     *     optimized: list<array{stop: RouteStop, newSequence: int}>,
     *     distanceBefore: float,
     *     distanceAfter: float,
     *     durationMinutes: int,
     * }
     */
    public function optimizeStopOrder(Route $route): array
    {
        $stops = $this->getStopsForRoute($route);

        $originStop = null;
        $deliveryStops = [];

        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                $originStop = $stop;
            } else {
                $deliveryStops[] = $stop;
            }
        }

        $distanceBefore = $this->calculateTotalRoadDistance($stops);

        if (\count($deliveryStops) < 2) {
            return $this->buildResult($originStop, $deliveryStops, $distanceBefore, $distanceBefore);
        }

        // Build VROOM request: single vehicle with all stops as jobs
        $vehicle = ['id' => 0];

        if ($originStop !== null && $originStop->getLatitude() !== null && $originStop->getLongitude() !== null) {
            $coords = [$originStop->getLongitude(), $originStop->getLatitude()];
            $vehicle['start'] = $coords;
            $vehicle['end'] = $coords;
        }

        $jobs = [];
        $stopMap = [];

        foreach ($deliveryStops as $index => $stop) {
            if ($stop->getLatitude() === null || $stop->getLongitude() === null) {
                continue;
            }

            $jobs[] = [
                'id' => $index,
                'location' => [$stop->getLongitude(), $stop->getLatitude()],
                'service' => 300,
            ];
            $stopMap[$index] = $stop;
        }

        if (\count($jobs) < 2) {
            return $this->buildResult($originStop, $deliveryStops, $distanceBefore, $distanceBefore);
        }

        $vroomResponse = $this->vroomClient->optimize([$vehicle], $jobs);

        // Extract optimized order from VROOM response
        $optimizedDeliveries = [];
        foreach ($vroomResponse['routes'][0]['steps'] ?? [] as $step) {
            if ($step['type'] === 'job' && isset($stopMap[$step['id']])) {
                $optimizedDeliveries[] = $stopMap[$step['id']];
            }
        }

        // Distance and duration from VROOM — real road values
        $distanceAfter = ($vroomResponse['routes'][0]['distance'] ?? 0) / 1000.0;
        $durationMinutes = (int) round(($vroomResponse['routes'][0]['duration'] ?? 0) / 60.0);

        return $this->buildResult($originStop, $optimizedDeliveries, $distanceBefore, $distanceAfter, $durationMinutes);
    }

    /**
     * Applies the optimized order to the route, persisting new sequences.
     *
     * @param list<array{stop: RouteStop, newSequence: int}> $optimized
     */
    public function applyOptimizedOrder(array $optimized): void
    {
        foreach ($optimized as $item) {
            $item['stop']->setSequence($item['newSequence']);
        }

        $this->em->flush();
    }

    /**
     * Gets real road distance between two stops via OSRM.
     */
    public function distanceBetweenStops(RouteStop $a, RouteStop $b): ?float
    {
        if ($a->getLatitude() === null || $a->getLongitude() === null
            || $b->getLatitude() === null || $b->getLongitude() === null) {
            return null;
        }

        $result = $this->osrmClient->getRoute(
            $a->getLatitude(),
            $a->getLongitude(),
            $b->getLatitude(),
            $b->getLongitude(),
        );

        return $result['distanceKm'];
    }

    /**
     * Gets real road distance and duration between two coordinates via OSRM.
     *
     * @return array{distanceKm: float, durationSeconds: float}
     */
    public function getRoadDistance(float $fromLat, float $fromLng, float $toLat, float $toLng): array
    {
        return $this->osrmClient->getRoute($fromLat, $fromLng, $toLat, $toLng);
    }

    /**
     * Estimates route timing using OSRM real road distances and durations.
     *
     * @return array{
     *     totalDistanceKm: float,
     *     drivingTimeMinutes: float,
     *     deliveryTimeMinutes: float,
     *     totalTimeMinutes: float,
     * }
     */
    public function estimateRouteTiming(Route $route, float $avgSpeedKmh = 40.0, float $deliveryMinutesPerStop = 5.0): array
    {
        $stops = $this->getStopsForRoute($route);
        $deliveryCount = 0;

        foreach ($stops as $stop) {
            if (!$stop->isOrigin()) {
                $deliveryCount++;
            }
        }

        // Build waypoints for OSRM route calculation
        $waypoints = [];
        foreach ($stops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $waypoints[] = ['lat' => $stop->getLatitude(), 'lng' => $stop->getLongitude()];
            }
        }

        if (\count($waypoints) < 2) {
            $deliveryTime = $deliveryCount * $deliveryMinutesPerStop;
            return [
                'totalDistanceKm' => 0.0,
                'drivingTimeMinutes' => 0.0,
                'deliveryTimeMinutes' => $deliveryTime,
                'totalTimeMinutes' => $deliveryTime,
            ];
        }

        $osrmResult = $this->osrmClient->getRouteWithWaypoints($waypoints);

        $drivingTime = $osrmResult['totalDurationSeconds'] / 60.0;
        $deliveryTime = $deliveryCount * $deliveryMinutesPerStop;

        return [
            'totalDistanceKm' => round($osrmResult['totalDistanceKm'], 2),
            'drivingTimeMinutes' => round($drivingTime, 1),
            'deliveryTimeMinutes' => $deliveryTime,
            'totalTimeMinutes' => round($drivingTime + $deliveryTime, 1),
        ];
    }

    /**
     * Calculates total road distance for a list of stops via OSRM.
     *
     * @param list<RouteStop> $stops
     */
    private function calculateTotalRoadDistance(array $stops): float
    {
        $waypoints = [];
        foreach ($stops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $waypoints[] = ['lat' => $stop->getLatitude(), 'lng' => $stop->getLongitude()];
            }
        }

        if (\count($waypoints) < 2) {
            return 0.0;
        }

        $result = $this->osrmClient->getRouteWithWaypoints($waypoints);

        return $result['totalDistanceKm'];
    }

    /**
     * @return array{
     *     optimized: list<array{stop: RouteStop, newSequence: int}>,
     *     distanceBefore: float,
     *     distanceAfter: float,
     *     durationMinutes: int,
     * }
     */
    private function buildResult(?RouteStop $originStop, array $deliveryStops, float $distanceBefore, float $distanceAfter, int $durationMinutes = 0): array
    {
        $result = [];
        $seq = 0;

        if ($originStop !== null) {
            $result[] = ['stop' => $originStop, 'newSequence' => $seq];
            $seq++;
        }

        foreach ($deliveryStops as $stop) {
            $result[] = ['stop' => $stop, 'newSequence' => $seq];
            $seq++;
        }

        return [
            'optimized' => $result,
            'distanceBefore' => $distanceBefore,
            'distanceAfter' => $distanceAfter,
            'durationMinutes' => $durationMinutes,
        ];
    }

    /**
     * @return list<RouteStop>
     */
    private function getStopsForRoute(Route $route): array
    {
        /** @var list<RouteStop> */
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
