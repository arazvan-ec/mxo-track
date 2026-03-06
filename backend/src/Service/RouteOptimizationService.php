<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Re-optimizes the stop order of an existing route using VROOM.
 * Also provides Haversine-based distance calculations for UI display.
 */
final class RouteOptimizationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VroomApiClient $vroomClient,
    ) {}

    /**
     * Optimizes the stop order of a route using VROOM + OSRM.
     *
     * @return array{
     *     optimized: list<array{stop: RouteStop, newSequence: int}>,
     *     distanceBefore: float,
     *     distanceAfter: float,
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

        $distanceBefore = $this->calculateTotalDistance($stops);

        if (\count($deliveryStops) < 2) {
            return $this->buildResult($originStop, $deliveryStops, $distanceBefore, $distanceBefore);
        }

        // Build VROOM request: single vehicle with all stops as jobs
        $originLat = $originStop?->getLatitude();
        $originLng = $originStop?->getLongitude();

        $vehicle = [
            'id' => 0,
        ];

        if ($originLat !== null && $originLng !== null) {
            $coords = [$originLng, $originLat]; // VROOM uses [lon, lat]
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

        // Distance from VROOM (meters to km)
        $distanceAfter = ($vroomResponse['routes'][0]['distance'] ?? 0) / 1000.0;

        return $this->buildResult($originStop, $optimizedDeliveries, $distanceBefore, $distanceAfter);
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
     * Calculates the Haversine distance between two coordinates in kilometers.
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Calculates total route distance in km from an ordered list of stops.
     *
     * @param list<RouteStop> $stops
     */
    public function calculateTotalDistance(array $stops): float
    {
        $total = 0.0;

        for ($i = 1, $count = \count($stops); $i < $count; $i++) {
            $prev = $stops[$i - 1];
            $curr = $stops[$i];

            if ($prev->getLatitude() !== null && $prev->getLongitude() !== null
                && $curr->getLatitude() !== null && $curr->getLongitude() !== null) {
                $total += $this->calculateDistance(
                    $prev->getLatitude(),
                    $prev->getLongitude(),
                    $curr->getLatitude(),
                    $curr->getLongitude(),
                );
            }
        }

        return $total;
    }

    /**
     * Calculates distance between two consecutive stops.
     */
    public function distanceBetweenStops(RouteStop $a, RouteStop $b): ?float
    {
        if ($a->getLatitude() === null || $a->getLongitude() === null
            || $b->getLatitude() === null || $b->getLongitude() === null) {
            return null;
        }

        return $this->calculateDistance(
            $a->getLatitude(),
            $a->getLongitude(),
            $b->getLatitude(),
            $b->getLongitude(),
        );
    }

    /**
     * Estimates route timing: driving time + delivery time per stop.
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

        $totalDistance = $this->calculateTotalDistance($stops);
        $drivingTime = ($totalDistance / $avgSpeedKmh) * 60;
        $deliveryTime = $deliveryCount * $deliveryMinutesPerStop;

        return [
            'totalDistanceKm' => round($totalDistance, 2),
            'drivingTimeMinutes' => round($drivingTime, 1),
            'deliveryTimeMinutes' => $deliveryTime,
            'totalTimeMinutes' => round($drivingTime + $deliveryTime, 1),
        ];
    }

    /**
     * @return list<array{stop: RouteStop, newSequence: int}>
     */
    private function buildResult(?RouteStop $originStop, array $deliveryStops, float $distanceBefore, float $distanceAfter): array
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
