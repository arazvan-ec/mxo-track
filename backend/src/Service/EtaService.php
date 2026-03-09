<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStopStatus;
use App\Routing\RoutingEngineInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class EtaService
{
    public const int DEFAULT_SERVICE_TIME_SECONDS = 300;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RoutingEngineInterface $routingEngine,
    ) {}

    /**
     * Calculates ETAs for each pending stop on a route using OSRM road distances.
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

        // If no vehicle position, try to use origin stop as reference
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

            // Use routing engine for real road distance and duration
            $roadResult = $this->routingEngine->route(
                $currentLat,
                $currentLng,
                $stop->getLatitude(),
                $stop->getLongitude(),
            );

            $accumulatedSeconds += (int) $roadResult->durationSeconds;

            $eta = $currentTime->modify('+' . $accumulatedSeconds . ' seconds');

            $etas[$stop->getPublicIdString()] = [
                'eta' => $eta,
                'remainingMinutes' => (int) ceil($accumulatedSeconds / 60),
                'distanceKm' => round($roadResult->distanceKm, 2),
            ];

            // Move current position to this stop for next calculation
            $currentLat = $stop->getLatitude();
            $currentLng = $stop->getLongitude();

            // Add service time per delivery (from shipment config or default)
            $serviceTime = $stop->getShipment()?->getServiceTimeSeconds() ?? self::DEFAULT_SERVICE_TIME_SECONDS;
            $accumulatedSeconds += $serviceTime;
        }

        return $etas;
    }

    /**
     * Estimates arrival time from one point to another using OSRM.
     */
    public function estimateArrival(
        float $fromLat,
        float $fromLng,
        float $toLat,
        float $toLng,
    ): DateTimeImmutable {
        $roadResult = $this->routingEngine->route($fromLat, $fromLng, $toLat, $toLng);

        return (new DateTimeImmutable())->modify('+' . (int) $roadResult->durationSeconds . ' seconds');
    }
}
