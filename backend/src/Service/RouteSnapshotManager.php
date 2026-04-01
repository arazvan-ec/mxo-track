<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\Repository\RouteSnapshotRepositoryInterface;
use App\Domain\Route\Model\RouteStop;
use App\Routing\Coordinate;
use App\Routing\RoutingEngineInterface;

/**
 * Manages RouteSnapshot lifecycle: creates after build/optimize,
 * updates stop states on progress events, refreshes polylines.
 */
final class RouteSnapshotManager
{
    public function __construct(
        private readonly RouteStopRepositoryInterface $stopRepo,
        private readonly RoutingEngineInterface $routingEngine,
        private readonly RouteCapacityValidator $capacityValidator,
        private readonly RouteSnapshotRepositoryInterface $snapshotRepo,
    ) {}

    /**
     * Creates or updates the full snapshot after build/optimize.
     * Calls OSRM once for the polyline.
     *
     * @param array<int, array<string, mixed>>|null $originalStopOrder
     */
    public function createSnapshot(
        Route $route,
        ?float $distanceBeforeKm = null,
        ?float $distanceAfterKm = null,
        ?array $originalStopOrder = null,
    ): RouteSnapshot {
        $snapshot = $this->snapshotRepo->findByRoute($route);
        $isNew = false;

        if ($snapshot === null) {
            $snapshot = new RouteSnapshot($route);
            $isNew = true;
        }

        $stops = $this->getStopsForRoute($route);

        // Polyline + timing from OSRM
        $waypoints = $this->buildWaypoints($stops);

        if (\count($waypoints) >= 2) {
            $routeResult = $this->routingEngine->routeWithWaypoints($waypoints);
            $snapshot->setPolyline($routeResult->geometry);

            if ($distanceAfterKm === null) {
                $distanceAfterKm = $routeResult->totalDistanceKm;
            }

            $deliveryCount = $this->countDeliveryStops($stops);
            $drivingMinutes = (int) round($routeResult->totalDurationSeconds / 60.0);
            $deliveryMinutes = $deliveryCount * 5;
            $snapshot->setDrivingTimeMinutes($drivingMinutes);
            $snapshot->setDeliveryTimeMinutes($deliveryMinutes);
            $snapshot->setTotalTimeMinutes($drivingMinutes + $deliveryMinutes);
        }

        // Metrics
        if ($distanceBeforeKm !== null) {
            $snapshot->setDistanceBeforeKm($distanceBeforeKm);
        }
        if ($distanceAfterKm !== null) {
            $snapshot->setDistanceAfterKm($distanceAfterKm);
        }
        if ($distanceBeforeKm !== null && $distanceBeforeKm > 0 && $distanceAfterKm !== null) {
            $savings = round((1 - $distanceAfterKm / $distanceBeforeKm) * 100, 1);
            $snapshot->setSavingsPercent($savings);
        }

        // Original stop order
        if ($originalStopOrder !== null) {
            $snapshot->setOriginalStopOrder($originalStopOrder);
        }

        // Stop states
        $this->buildStopStates($snapshot, $stops);

        // Capacity validation
        $validation = $this->capacityValidator->validate($route);
        $snapshot->setCapacityValidation($validation);

        $snapshot->touch();

        if ($isNew) {
            $this->snapshotRepo->save($snapshot);
        }

        return $snapshot;
    }

    public function findByRoute(Route $route): ?RouteSnapshot
    {
        return $this->snapshotRepo->findByRoute($route);
    }

    /**
     * Updates only the stopStates from current RouteStop entities.
     * Fast path — no OSRM calls.
     */
    public function updateStopStates(Route $route): RouteSnapshot
    {
        $snapshot = $this->snapshotRepo->findByRoute($route);

        if ($snapshot === null) {
            $snapshot = new RouteSnapshot($route);
            $this->snapshotRepo->save($snapshot);
        }

        $stops = $this->getStopsForRoute($route);
        $this->buildStopStates($snapshot, $stops);

        return $snapshot;
    }

    /**
     * Updates ETAs in the snapshot. Returns previous ETAs for comparison.
     *
     * @param array<string, array{eta: \DateTimeImmutable, remainingMinutes: int, distanceKm: float}> $etas
     * @return array<string, int>|null Previous ETAs as stop_public_id => minutes (null if no previous)
     */
    public function updateEtas(Route $route, array $etas): ?array
    {
        $snapshot = $this->snapshotRepo->findByRoute($route);

        if ($snapshot === null) {
            $snapshot = new RouteSnapshot($route);
            $this->snapshotRepo->save($snapshot);
        }

        $previousEtas = $snapshot->getEtas();
        $previousMinutes = null;
        if ($previousEtas !== null) {
            $previousMinutes = [];
            foreach ($previousEtas as $stopId => $data) {
                $previousMinutes[$stopId] = $data['minutes'];
            }
        }

        $etaData = [];
        foreach ($etas as $stopPublicId => $data) {
            $etaData[$stopPublicId] = [
                'eta' => $data['eta']->format(\DateTimeInterface::ATOM),
                'minutes' => $data['remainingMinutes'],
                'distance_km' => $data['distanceKm'],
            ];
        }

        $snapshot->setEtas($etaData);

        return $previousMinutes;
    }

    /**
     * Refreshes the polyline after stop reordering.
     */
    public function refreshPolyline(Route $route): RouteSnapshot
    {
        $snapshot = $this->snapshotRepo->findByRoute($route);

        if ($snapshot === null) {
            return $this->createSnapshot($route);
        }

        $stops = $this->getStopsForRoute($route);
        $waypoints = $this->buildWaypoints($stops);

        if (\count($waypoints) >= 2) {
            $routeResult = $this->routingEngine->routeWithWaypoints($waypoints);
            $snapshot->setPolyline($routeResult->geometry);
            $snapshot->setDistanceAfterKm($routeResult->totalDistanceKm);

            $deliveryCount = $this->countDeliveryStops($stops);
            $drivingMinutes = (int) round($routeResult->totalDurationSeconds / 60.0);
            $deliveryMinutes = $deliveryCount * 5;
            $snapshot->setDrivingTimeMinutes($drivingMinutes);
            $snapshot->setDeliveryTimeMinutes($deliveryMinutes);
            $snapshot->setTotalTimeMinutes($drivingMinutes + $deliveryMinutes);
        }

        $snapshot->touch();

        return $snapshot;
    }

    /**
     * @param list<RouteStop> $stops
     */
    private function buildStopStates(RouteSnapshot $snapshot, array $stops): void
    {
        $states = [];
        foreach ($stops as $stop) {
            try {
                $publicId = $stop->getPublicId()->toRfc4122();
            } catch (\Error) {
                $publicId = null;
            }

            $state = [
                'publicId' => $publicId,
                'sequence' => $stop->getSequence(),
                'address' => $stop->getAddress(),
                'recipientName' => $stop->getRecipientName(),
                'lat' => $stop->getLatitude(),
                'lng' => $stop->getLongitude(),
                'isOrigin' => $stop->isOrigin(),
                'status' => $stop->getStatus()->value,
                'shipmentPublicId' => $stop->getShipment()?->getPublicIdString(),
            ];

            if ($stop->getDeliveredAt() !== null) {
                $state['deliveredAt'] = $stop->getDeliveredAt()->format(\DateTimeInterface::ATOM);
            }

            if ($stop->getExceptionCode() !== null) {
                $state['exceptionCode'] = $stop->getExceptionCode()->value;
                $state['exceptionNotes'] = $stop->getExceptionNotes();
            }

            $states[] = $state;
        }

        $snapshot->setStopStates($states);
    }

    /**
     * @param list<RouteStop> $stops
     * @return list<Coordinate>
     */
    private function buildWaypoints(array $stops): array
    {
        $waypoints = [];
        foreach ($stops as $stop) {
            if ($stop->getLatitude() !== null && $stop->getLongitude() !== null) {
                $waypoints[] = new Coordinate($stop->getLatitude(), $stop->getLongitude());
            }
        }

        return $waypoints;
    }

    /**
     * @param list<RouteStop> $stops
     */
    private function countDeliveryStops(array $stops): int
    {
        $count = 0;
        foreach ($stops as $stop) {
            if (!$stop->isOrigin()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<RouteStop>
     */
    private function getStopsForRoute(Route $route): array
    {
        return $this->stopRepo->findByRoute($route);
    }
}
