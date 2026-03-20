<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteStarted;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Domain\Route\Model\Route;
use App\Repository\RouteRepository;
use App\Service\RouteSnapshotManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Updates RouteSnapshot when route progress events occur.
 * Mercure publishing is handled by MapEventProjector.
 */
final readonly class RouteSnapshotListener
{
    public function __construct(
        private RouteSnapshotManager $snapshotManager,
        private RouteRepository $routeRepo,
    ) {}

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $this->handleProgressEvent($event->routePublicId);
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $this->handleProgressEvent($event->routePublicId);
    }

    #[AsEventListener]
    public function onRouteStarted(RouteStarted $event): void
    {
        $this->handleProgressEvent($event->routePublicId);
    }

    #[AsEventListener]
    public function onRouteCompleted(RouteCompleted $event): void
    {
        $this->handleProgressEvent($event->routePublicId);
    }

    private function handleProgressEvent(string $routePublicId): void
    {
        $route = $this->routeRepo->findOneByPublicId($routePublicId);
        if (!$route instanceof Route) {
            return;
        }

        $this->snapshotManager->updateStopStates($route);
    }
}
