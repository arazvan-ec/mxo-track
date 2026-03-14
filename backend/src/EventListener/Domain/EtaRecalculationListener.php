<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\EtaChanged;
use App\Domain\Event\VehiclePositionReceived;
use App\Entity\Route;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use App\Repository\VehicleRepository;
use App\Service\EtaService;
use App\Service\RouteSnapshotManager;
use App\View\MapViewData;
use App\View\MapViewOptions;
use App\View\RouteViewService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class EtaRecalculationListener
{
    private const int ETA_CHANGE_THRESHOLD_MINUTES = 5;
    private const int THROTTLE_SECONDS = 30;

    /** @var array<string, float> route ID => last calculation microtime */
    private array $lastCalculatedAt = [];

    public function __construct(
        private readonly VehicleRepository $vehicleRepo,
        private readonly EntityManagerInterface $em,
        private readonly EtaService $etaService,
        private readonly RouteSnapshotManager $snapshotManager,
        private readonly RouteViewService $viewService,
        private readonly HubInterface $hub,
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

        // Publish updated MapViewData via Mercure
        $this->publishRouteViewUpdate($activeRoute);
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

    private function publishRouteViewUpdate(Route $route): void
    {
        $roles = ['ROLE_ADMIN', 'ROLE_CUSTOMER', 'ROLE_DRIVER'];

        foreach ($roles as $role) {
            try {
                $mapData = $this->viewService->buildSingleRouteView($route, $role);
                $this->hub->publish(new Update(
                    sprintf('/routes/%s/view/%s', $route->getPublicIdString(), strtolower(str_replace('ROLE_', '', $role))),
                    $mapData->toJson(),
                ));
            } catch (\Throwable) {
                // Don't break on Mercure failure
            }
        }
    }
}
