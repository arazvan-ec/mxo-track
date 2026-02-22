<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStopStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class EtaService
{
    private const float DEFAULT_AVG_SPEED_KMH = 30.0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteOptimizationService $optimizationService,
    ) {}

    /**
     * Calculates ETAs for each pending stop on a route.
     *
     * @return array<string, array{eta: DateTimeImmutable, remainingMinutes: int, distanceKm: float}>
     *     Keyed by stop public_id
     */
    public function calculateEtas(Route $route): array
    {
        $vehicle = $route->getVehicle();
        if ($vehicle === null) {
            return [];
        }

        // Get vehicle last known position
        $lastPosition = $this->em->getRepository(VehicleLastPosition::class)
            ->findOneBy(['vehicle' => $vehicle]);

        // Load stops ordered by sequence
        /** @var list<RouteStop> $stops */
        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();

        if (\count($stops) === 0) {
            return [];
        }

        // Determine starting position
        $currentLat = null;
        $currentLng = null;
        $currentTime = new DateTimeImmutable();

        if ($lastPosition instanceof VehicleLastPosition) {
            $currentLat = $lastPosition->getLat();
            $currentLng = $lastPosition->getLng();
        }

        // If no vehicle position, try to use the first non-pending (completed) stop
        // or origin stop as reference
        if ($currentLat === null || $currentLng === null) {
            foreach ($stops as $stop) {
                if ($stop->isOrigin() && $stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                    $currentLat = $stop->getLatitude();
                    $currentLng = $stop->getLongitude();
                    break;
                }
            }
        }

        if ($currentLat === null || $currentLng === null) {
            return [];
        }

        $etas = [];
        $accumulatedSeconds = 0;

        foreach ($stops as $stop) {
            // Skip non-pending stops and origin stops
            if ($stop->getStatus() !== RouteStopStatus::PENDING || $stop->isOrigin()) {
                // If this is a completed stop with coordinates, update current position
                if ($stop->getStatus() === RouteStopStatus::DELIVERED
                    && $stop->getLatitude() !== null
                    && $stop->getLongitude() !== null) {
                    $currentLat = $stop->getLatitude();
                    $currentLng = $stop->getLongitude();
                }
                continue;
            }

            if ($stop->getLatitude() === null || $stop->getLongitude() === null) {
                continue;
            }

            $distanceKm = $this->optimizationService->calculateDistance(
                $currentLat,
                $currentLng,
                $stop->getLatitude(),
                $stop->getLongitude(),
            );

            $travelTimeSeconds = $this->calculateTravelTimeSeconds($distanceKm, self::DEFAULT_AVG_SPEED_KMH);
            $accumulatedSeconds += $travelTimeSeconds;

            $eta = $currentTime->modify('+' . (int) $accumulatedSeconds . ' seconds');

            $etas[$stop->getPublicIdString()] = [
                'eta' => $eta,
                'remainingMinutes' => (int) ceil($accumulatedSeconds / 60),
                'distanceKm' => round($distanceKm, 2),
            ];

            // Move current position to this stop for next calculation
            $currentLat = $stop->getLatitude();
            $currentLng = $stop->getLongitude();

            // Add a small stop time (2 minutes per delivery)
            $accumulatedSeconds += 120;
        }

        return $etas;
    }

    /**
     * Estimates arrival time from one point to another.
     */
    public function estimateArrival(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
        float $avgSpeedKmh = self::DEFAULT_AVG_SPEED_KMH,
    ): DateTimeImmutable {
        $distanceKm = $this->optimizationService->calculateDistance($fromLat, $fromLng, $toLat, $toLng);
        $travelTimeSeconds = $this->calculateTravelTimeSeconds($distanceKm, $avgSpeedKmh);

        return (new DateTimeImmutable())->modify('+' . (int) $travelTimeSeconds . ' seconds');
    }

    /**
     * Calculates travel time in seconds for a given distance and speed.
     */
    private function calculateTravelTimeSeconds(float $distanceKm, float $avgSpeedKmh): float
    {
        if ($avgSpeedKmh <= 0.0) {
            return 0.0;
        }

        return ($distanceKm / $avgSpeedKmh) * 3600;
    }
}
