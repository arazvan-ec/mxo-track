<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\VehiclePosition;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Compares planned route (from RouteStops) with actual GPS track (from VehiclePositions).
 *
 * Returns polylines, distance metrics, and deviation data for visual analysis.
 */
final class RouteComparisonService
{
    private const float EARTH_RADIUS_KM = 6371.0;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Compare planned route vs actual GPS track.
     *
     * @return array{
     *     plannedPolyline: list<array{float, float}>,
     *     actualPolyline: list<array{float, float}>,
     *     deviationKm: float,
     *     extraTimeMinutes: float,
     *     plannedDistanceKm: float,
     *     actualDistanceKm: float,
     *     stops: list<array{sequence: int, address: string, lat: float|null, lng: float|null, status: string, isOrigin: bool}>,
     * }
     */
    public function compare(Route $route): array
    {
        $stops = $this->getStopsInSequence($route);

        if ($stops === []) {
            return ['warning' => 'no_stops'];
        }

        $positions = $this->getActualPositions($route);

        if ($positions === []) {
            return ['warning' => 'no_positions'];
        }

        $plannedPolyline = $this->buildPlannedPolyline($stops);
        $actualPolyline = $this->buildActualPolyline($positions);

        $plannedDistanceKm = $this->polylineDistance($plannedPolyline);
        $actualDistanceKm = $this->polylineDistance($actualPolyline);

        $deviationKm = abs($actualDistanceKm - $plannedDistanceKm);

        $extraTimeMinutes = $this->calculateExtraTime($route);

        $stopsData = [];
        foreach ($stops as $stop) {
            $stopsData[] = [
                'sequence' => $stop->getSequence(),
                'address' => $stop->getAddress(),
                'lat' => $stop->getLatitude(),
                'lng' => $stop->getLongitude(),
                'status' => $stop->getStatus()->value,
                'isOrigin' => $stop->isOrigin(),
            ];
        }

        return [
            'plannedPolyline' => $plannedPolyline,
            'actualPolyline' => $actualPolyline,
            'deviationKm' => round($deviationKm, 2),
            'extraTimeMinutes' => round($extraTimeMinutes, 1),
            'plannedDistanceKm' => round($plannedDistanceKm, 2),
            'actualDistanceKm' => round($actualDistanceKm, 2),
            'stops' => $stopsData,
        ];
    }

    /**
     * @return list<RouteStop>
     */
    private function getStopsInSequence(Route $route): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<VehiclePosition>
     */
    private function getActualPositions(Route $route): array
    {
        $vehicle = $route->getVehicle();

        if ($vehicle === null) {
            return [];
        }

        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(VehiclePosition::class, 'p')
            ->where('p.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('p.deviceTime', 'ASC');

        // Filter by route time range if available
        if ($route->getStartAt() !== null) {
            $qb->andWhere('p.deviceTime >= :startAt')
               ->setParameter('startAt', $route->getStartAt());
        }

        if ($route->getEndAt() !== null) {
            $qb->andWhere('p.deviceTime <= :endAt')
               ->setParameter('endAt', $route->getEndAt());
        }

        // Also include positions directly linked to this route
        if ($route->getStartAt() !== null || $route->getEndAt() !== null) {
            $directQb = $this->em->createQueryBuilder()
                ->select('p2')
                ->from(VehiclePosition::class, 'p2')
                ->where('p2.route = :route')
                ->setParameter('route', $route)
                ->orderBy('p2.deviceTime', 'ASC');

            $directPositions = $directQb->getQuery()->getResult();
            $timePositions = $qb->getQuery()->getResult();

            // Merge and deduplicate by id
            $merged = [];
            foreach (array_merge($timePositions, $directPositions) as $pos) {
                $merged[$pos->getId()] = $pos;
            }

            // Sort by deviceTime
            $result = array_values($merged);
            usort($result, fn (VehiclePosition $a, VehiclePosition $b) => $a->getDeviceTime() <=> $b->getDeviceTime());

            return $result;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<RouteStop> $stops
     * @return list<array{float, float}>
     */
    private function buildPlannedPolyline(array $stops): array
    {
        $polyline = [];

        foreach ($stops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $polyline[] = [$stop->getLatitude(), $stop->getLongitude()];
            }
        }

        return $polyline;
    }

    /**
     * @param list<VehiclePosition> $positions
     * @return list<array{float, float}>
     */
    private function buildActualPolyline(array $positions): array
    {
        $polyline = [];

        foreach ($positions as $position) {
            $polyline[] = [$position->getLat(), $position->getLng()];
        }

        return $polyline;
    }

    /**
     * Calculate total distance of a polyline using Haversine formula.
     *
     * @param list<array{float, float}> $polyline
     */
    private function polylineDistance(array $polyline): float
    {
        $distance = 0.0;
        $count = \count($polyline);

        for ($i = 1; $i < $count; $i++) {
            $distance += $this->haversine(
                $polyline[$i - 1][0],
                $polyline[$i - 1][1],
                $polyline[$i][0],
                $polyline[$i][1],
            );
        }

        return $distance;
    }

    /**
     * Haversine distance between two points in kilometers.
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Calculate extra time compared to estimated duration.
     */
    private function calculateExtraTime(Route $route): float
    {
        $estimatedMinutes = $route->getEstimatedDurationMinutes();

        if ($estimatedMinutes === null || $route->getStartAt() === null || $route->getEndAt() === null) {
            return 0.0;
        }

        $actualSeconds = $route->getEndAt()->getTimestamp() - $route->getStartAt()->getTimestamp();
        $actualMinutes = $actualSeconds / 60.0;

        return $actualMinutes - (float) $estimatedMinutes;
    }

    private function isValidCoordinate(float $lat, float $lng): bool
    {
        return $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0;
    }
}
