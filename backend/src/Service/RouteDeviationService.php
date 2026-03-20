<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\DeviationCheckResult;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Repository\RouteSnapshotRepositoryInterface;
use App\Routing\PolylineDecoder;

final class RouteDeviationService
{
    private const float DEVIATION_THRESHOLD_METERS = 500.0;
    private const float EARTH_RADIUS_METERS = 6_371_000.0;

    public function __construct(
        private readonly RouteSnapshotRepositoryInterface $snapshotRepo,
    ) {}

    public function checkDeviation(Route $route, float $lat, float $lng): ?DeviationCheckResult
    {
        $snapshot = $this->snapshotRepo->findByRoute($route);
        if ($snapshot === null || $snapshot->getPolyline() === null) {
            return null;
        }

        $points = PolylineDecoder::decode($snapshot->getPolyline());
        if (\count($points) < 2) {
            return null;
        }

        $minDistance = $this->minimumDistanceToPolyline($lat, $lng, $points);

        return new DeviationCheckResult(
            isDeviated: $minDistance > self::DEVIATION_THRESHOLD_METERS,
            distanceMeters: $minDistance,
            thresholdMeters: self::DEVIATION_THRESHOLD_METERS,
        );
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     */
    private function minimumDistanceToPolyline(float $lat, float $lng, array $points): float
    {
        $minDistance = PHP_FLOAT_MAX;

        for ($i = 0, $count = \count($points) - 1; $i < $count; $i++) {
            $distance = $this->distanceToSegment(
                $lat,
                $lng,
                $points[$i][0],
                $points[$i][1],
                $points[$i + 1][0],
                $points[$i + 1][1],
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
            }

            // Early exit if already on-route
            if ($minDistance < 50.0) {
                return $minDistance;
            }
        }

        return $minDistance;
    }

    /**
     * Distance from a point to a line segment using Haversine-based projection.
     */
    private function distanceToSegment(
        float $pLat,
        float $pLng,
        float $aLat,
        float $aLng,
        float $bLat,
        float $bLng,
    ): float {
        // Project point P onto line segment AB
        $abLat = $bLat - $aLat;
        $abLng = $bLng - $aLng;
        $apLat = $pLat - $aLat;
        $apLng = $pLng - $aLng;

        $abLenSq = $abLat * $abLat + $abLng * $abLng;

        if ($abLenSq < 1e-12) {
            // A and B are the same point
            return $this->haversine($pLat, $pLng, $aLat, $aLng);
        }

        // Parameter t of the projection of P onto AB, clamped to [0, 1]
        $t = ($apLat * $abLat + $apLng * $abLng) / $abLenSq;
        $t = max(0.0, min(1.0, $t));

        // Closest point on segment
        $closestLat = $aLat + $t * $abLat;
        $closestLng = $aLng + $t * $abLng;

        return $this->haversine($pLat, $pLng, $closestLat, $closestLng);
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * atan2(sqrt($a), sqrt(1 - $a));
    }
}
