<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use Doctrine\ORM\EntityManagerInterface;

final class RouteOptimizationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Optimizes the stop order of a route using nearest-neighbor heuristic.
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

        // Separate origin stop from delivery stops
        $originStop = null;
        $deliveryStops = [];

        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                $originStop = $stop;
            } else {
                $deliveryStops[] = $stop;
            }
        }

        // Calculate distance before optimization
        $distanceBefore = $this->calculateTotalDistance($stops);

        // If we have fewer than 2 delivery stops, no optimization needed
        if (\count($deliveryStops) < 2) {
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
                'distanceAfter' => $distanceBefore,
            ];
        }

        // Determine start point for nearest-neighbor
        $startLat = null;
        $startLng = null;

        if ($originStop !== null && $originStop->getLatitude() !== null && $originStop->getLongitude() !== null) {
            $startLat = $originStop->getLatitude();
            $startLng = $originStop->getLongitude();
        } elseif (\count($deliveryStops) > 0) {
            // Use first delivery stop as start
            $startLat = $deliveryStops[0]->getLatitude();
            $startLng = $deliveryStops[0]->getLongitude();
        }

        // Apply nearest-neighbor heuristic
        $optimizedDeliveries = $this->nearestNeighbor($deliveryStops, $startLat, $startLng);

        // Build result with origin first
        $result = [];
        $seq = 0;

        if ($originStop !== null) {
            $result[] = ['stop' => $originStop, 'newSequence' => $seq];
            $seq++;
        }

        foreach ($optimizedDeliveries as $stop) {
            $result[] = ['stop' => $stop, 'newSequence' => $seq];
            $seq++;
        }

        // Calculate distance after optimization
        $optimizedStops = array_map(static fn (array $item): RouteStop => $item['stop'], $result);
        $distanceAfter = $this->calculateTotalDistance($optimizedStops);

        return [
            'optimized' => $result,
            'distanceBefore' => $distanceBefore,
            'distanceAfter' => $distanceAfter,
        ];
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
     * Returns null if coordinates are missing.
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
     * Nearest-neighbor heuristic: from the start point, always visit the closest unvisited stop.
     *
     * @param list<RouteStop> $stops
     * @return list<RouteStop>
     */
    private function nearestNeighbor(array $stops, ?float $startLat, ?float $startLng): array
    {
        if (\count($stops) === 0) {
            return [];
        }

        // If no start coordinates, return stops unchanged
        if ($startLat === null || $startLng === null) {
            return $stops;
        }

        $unvisited = $stops;
        $ordered = [];
        $currentLat = $startLat;
        $currentLng = $startLng;

        while (\count($unvisited) > 0) {
            $nearestIndex = 0;
            $nearestDistance = PHP_FLOAT_MAX;

            foreach ($unvisited as $index => $stop) {
                if ($stop->getLatitude() === null || $stop->getLongitude() === null) {
                    // Stops without coordinates go to the end; treat as very far
                    $distance = PHP_FLOAT_MAX - 1;
                } else {
                    $distance = $this->calculateDistance(
                        $currentLat,
                        $currentLng,
                        $stop->getLatitude(),
                        $stop->getLongitude(),
                    );
                }

                if ($distance < $nearestDistance) {
                    $nearestDistance = $distance;
                    $nearestIndex = $index;
                }
            }

            $nearest = $unvisited[$nearestIndex];
            $ordered[] = $nearest;

            if ($nearest->getLatitude() !== null && $nearest->getLongitude() !== null) {
                $currentLat = $nearest->getLatitude();
                $currentLng = $nearest->getLongitude();
            }

            array_splice($unvisited, $nearestIndex, 1);
        }

        return $ordered;
    }

    /**
     * Farthest-first optimization: start from the farthest point and work back to origin.
     * Better for delivery routes where you want to deliver the farthest point first
     * and return via the closest stops.
     *
     * @return array{
     *     optimized: list<array{stop: RouteStop, newSequence: int}>,
     *     distanceBefore: float,
     *     distanceAfter: float,
     * }
     */
    public function optimizeFarthestFirst(Route $route): array
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
            return $this->buildResultNoOptimization($originStop, $deliveryStops, $distanceBefore);
        }

        $startLat = $originStop?->getLatitude();
        $startLng = $originStop?->getLongitude();

        if ($startLat === null || $startLng === null) {
            return $this->buildResultNoOptimization($originStop, $deliveryStops, $distanceBefore);
        }

        // Sort by distance from origin (farthest first)
        $stopsWithDistance = [];
        foreach ($deliveryStops as $stop) {
            $dist = 0.0;
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $dist = $this->calculateDistance($startLat, $startLng, $stop->getLatitude(), $stop->getLongitude());
            }
            $stopsWithDistance[] = ['stop' => $stop, 'distance' => $dist];
        }

        usort($stopsWithDistance, static fn (array $a, array $b): int => $b['distance'] <=> $a['distance']);

        // Now from the farthest point, use nearest-neighbor to return efficiently
        $farthestFirst = array_map(static fn (array $item): RouteStop => $item['stop'], $stopsWithDistance);
        $optimizedDeliveries = $this->nearestNeighborFromFirst($farthestFirst);

        // Build result
        $result = [];
        $seq = 0;

        if ($originStop !== null) {
            $result[] = ['stop' => $originStop, 'newSequence' => $seq];
            $seq++;
        }

        foreach ($optimizedDeliveries as $stop) {
            $result[] = ['stop' => $stop, 'newSequence' => $seq];
            $seq++;
        }

        $optimizedStops = array_map(static fn (array $item): RouteStop => $item['stop'], $result);
        $distanceAfter = $this->calculateTotalDistance($optimizedStops);

        return [
            'optimized' => $result,
            'distanceBefore' => $distanceBefore,
            'distanceAfter' => $distanceAfter,
        ];
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
     * @param list<RouteStop> $stops
     * @return array{
     *     optimized: list<array{stop: RouteStop, newSequence: int}>,
     *     distanceBefore: float,
     *     distanceAfter: float,
     * }
     */
    private function buildResultNoOptimization(?RouteStop $originStop, array $stops, float $distance): array
    {
        $result = [];
        $seq = 0;

        if ($originStop !== null) {
            $result[] = ['stop' => $originStop, 'newSequence' => $seq];
            $seq++;
        }

        foreach ($stops as $stop) {
            $result[] = ['stop' => $stop, 'newSequence' => $seq];
            $seq++;
        }

        return [
            'optimized' => $result,
            'distanceBefore' => $distance,
            'distanceAfter' => $distance,
        ];
    }

    /**
     * From the first stop in the list, apply nearest-neighbor to the remaining.
     *
     * @param list<RouteStop> $stops
     * @return list<RouteStop>
     */
    private function nearestNeighborFromFirst(array $stops): array
    {
        if (\count($stops) <= 1) {
            return $stops;
        }

        $first = $stops[0];
        $rest = \array_slice($stops, 1);

        $startLat = $first->getLatitude();
        $startLng = $first->getLongitude();

        if ($startLat === null || $startLng === null) {
            return $stops;
        }

        $optimizedRest = $this->nearestNeighbor($rest, $startLat, $startLng);

        return array_merge([$first], $optimizedRest);
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
