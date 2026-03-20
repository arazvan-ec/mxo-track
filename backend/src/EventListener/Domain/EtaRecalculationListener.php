<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\DeviationDetected;
use App\Domain\Event\DeviationEnded;
use App\Domain\Event\EtaChanged;
use App\Domain\Event\VehiclePositionReceived;
use App\Domain\Route\Model\Route;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use App\Repository\VehicleRepository;
use App\Service\EtaService;
use App\Service\RouteDeviationService;
use App\Service\RouteSnapshotManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Recalculates ETAs and detects route deviations on vehicle position updates.
 * Mercure publishing is handled by MapEventProjector.
 */
final class EtaRecalculationListener
{
    private const int ETA_CHANGE_THRESHOLD_MINUTES = 5;
    private const int THROTTLE_SECONDS = 30;

    /** @var array<string, float> route ID => last calculation microtime */
    private array $lastCalculatedAt = [];

    /** @var array<string, bool> route ID => currently deviated */
    private array $deviationState = [];

    public function __construct(
        private readonly VehicleRepository $vehicleRepo,
        private readonly EntityManagerInterface $em,
        private readonly EtaService $etaService,
        private readonly RouteDeviationService $deviationService,
        private readonly RouteSnapshotManager $snapshotManager,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    #[AsEventListener]
    public function onVehiclePositionReceived(VehiclePositionReceived $event): void
    {
        $vehicle = $this->vehicleRepo->findOneByPublicId($event->vehiclePublicId);
        if (!$vehicle instanceof Vehicle) {
            return;
        }

        $activeRoute = $this->em->getRepository(Route::class)->findOneBy([
            'vehicle' => $vehicle,
            'status' => RouteStatus::ACTIVE,
        ]);

        if ($activeRoute === null) {
            return;
        }

        // Throttle: skip if last calculation was < 30s ago
        $routeId = $activeRoute->getId();
        if ($routeId !== null && isset($this->lastCalculatedAt[$routeId])) {
            if ((microtime(true) - $this->lastCalculatedAt[$routeId]) < self::THROTTLE_SECONDS) {
                return;
            }
        }

        $etas = $this->etaService->calculateEtas($activeRoute);
        if ($etas === []) {
            return;
        }

        if ($routeId !== null) {
            $this->lastCalculatedAt[$routeId] = microtime(true);
        }

        $previousMinutes = $this->snapshotManager->updateEtas($activeRoute, $etas);
        $this->em->flush();

        // Check for significant ETA changes
        if ($previousMinutes !== null) {
            $currentMinutes = [];
            foreach ($etas as $stopId => $data) {
                $currentMinutes[$stopId] = $data['remainingMinutes'];
            }

            $maxDelta = $this->calculateMaxDelta($previousMinutes, $currentMinutes);

            if ($maxDelta >= self::ETA_CHANGE_THRESHOLD_MINUTES) {
                $this->dispatcher->dispatch(new EtaChanged(
                    routePublicId: $activeRoute->getPublicIdString(),
                    previousEtas: $previousMinutes,
                    currentEtas: $currentMinutes,
                    maxDeltaMinutes: $maxDelta,
                ));
            }
        }

        // Check for route deviation
        $this->checkDeviation($activeRoute, $event);
    }

    /**
     * @param array<string, int> $previous
     * @param array<string, int> $current
     */
    private function calculateMaxDelta(array $previous, array $current): int
    {
        $maxDelta = 0;

        foreach ($current as $stopId => $minutes) {
            if (isset($previous[$stopId])) {
                $delta = abs($minutes - $previous[$stopId]);
                if ($delta > $maxDelta) {
                    $maxDelta = $delta;
                }
            }
        }

        return $maxDelta;
    }

    private function checkDeviation(Route $activeRoute, VehiclePositionReceived $event): void
    {
        $result = $this->deviationService->checkDeviation(
            $activeRoute,
            $event->latitude,
            $event->longitude,
        );

        if ($result === null) {
            return;
        }

        $routeKey = $activeRoute->getId() ?? $activeRoute->getPublicIdString();
        $wasDeviated = $this->deviationState[$routeKey] ?? false;

        if ($result->isDeviated && !$wasDeviated) {
            // Transition: on-route → off-route
            $this->deviationState[$routeKey] = true;

            $this->dispatcher->dispatch(new DeviationDetected(
                routePublicId: $activeRoute->getPublicIdString(),
                vehiclePublicId: $event->vehiclePublicId,
                latitude: $event->latitude,
                longitude: $event->longitude,
                distanceMeters: $result->distanceMeters,
                thresholdMeters: $result->thresholdMeters,
            ));
        } elseif (!$result->isDeviated && $wasDeviated) {
            // Transition: off-route → on-route
            $this->deviationState[$routeKey] = false;

            $this->dispatcher->dispatch(new DeviationEnded(
                routePublicId: $activeRoute->getPublicIdString(),
                vehiclePublicId: $event->vehiclePublicId,
            ));
        }
    }

}
