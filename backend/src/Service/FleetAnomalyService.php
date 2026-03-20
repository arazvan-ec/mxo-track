<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\Vehicle;
use App\Entity\VehiclePosition;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Detects fleet anomalies by sending position data to the ML sidecar.
 */
final class FleetAnomalyService
{
    /** Maximum number of recent positions to send for analysis. */
    private const int MAX_POSITIONS = 500;

    public function __construct(
        private readonly MlApiClient $mlClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Check for anomalies on a vehicle's recent positions against a route.
     *
     * @return list<array{type: string, severity: string, lat: float, lng: float, timestamp: string, details: string}>
     */
    public function checkAnomaly(int $vehicleId, int $routeId): array
    {
        $vehicle = $this->em->getRepository(Vehicle::class)->find($vehicleId);
        if ($vehicle === null) {
            $this->logger->warning('FleetAnomalyService: vehicle not found', ['vehicleId' => $vehicleId]);

            return [];
        }

        $route = $this->em->getRepository(Route::class)->find($routeId);
        if ($route === null) {
            $this->logger->warning('FleetAnomalyService: route not found', ['routeId' => $routeId]);

            return [];
        }

        $positions = $this->loadRecentPositions($vehicle);
        $plannedStops = $this->loadPlannedStops($route);

        if (\count($positions) === 0) {
            return [];
        }

        $payload = [
            'vehicle_id' => $vehicleId,
            'positions' => $positions,
            'planned_stops' => $plannedStops,
        ];

        $response = $this->mlClient->post('/predict/fleet-anomaly', $payload);

        if ($response === null || !isset($response['anomalies'])) {
            $this->logger->warning('Failed to fetch anomaly data from ML sidecar', [
                'vehicleId' => $vehicleId,
                'routeId' => $routeId,
            ]);

            return [];
        }

        /** @var list<array{type: string, severity: string, lat: float, lng: float, timestamp: string, details: string}> */
        return $response['anomalies'];
    }

    /**
     * @return list<array{lat: float, lng: float, speed: float, timestamp: string}>
     */
    private function loadRecentPositions(Vehicle $vehicle): array
    {
        /** @var list<VehiclePosition> $positions */
        $positions = $this->em->createQueryBuilder()
            ->select('p')
            ->from(VehiclePosition::class, 'p')
            ->where('p.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('p.deviceTime', 'DESC')
            ->setMaxResults(self::MAX_POSITIONS)
            ->getQuery()
            ->getResult();

        // Reverse to chronological order
        $positions = array_reverse($positions);

        return array_map(static fn (VehiclePosition $p): array => [
            'lat' => $p->getLat(),
            'lng' => $p->getLng(),
            'speed' => $p->getSpeed(),
            'timestamp' => $p->getDeviceTime()->format(\DateTimeInterface::ATOM),
        ], $positions);
    }

    /**
     * @return list<array{lat: float, lng: float, sequence: int}>
     */
    private function loadPlannedStops(Route $route): array
    {
        /** @var list<RouteStop> $stops */
        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($stops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $result[] = [
                    'lat' => $stop->getLatitude(),
                    'lng' => $stop->getLongitude(),
                    'sequence' => $stop->getSequence(),
                ];
            }
        }

        return $result;
    }
}
