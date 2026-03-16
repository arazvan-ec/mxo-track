<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Route\Event\RouteCompleted;
use App\Domain\Route\Event\RouteStarted;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Entity\Route;
use App\Repository\RouteRepository;
use App\Service\RouteSnapshotManager;
use App\View\RouteViewService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Updates RouteSnapshot and publishes MapViewData via Mercure
 * when route progress events occur (delivery, exception, start, complete).
 */
final readonly class RouteSnapshotListener
{
    public function __construct(
        private RouteSnapshotManager $snapshotManager,
        private RouteViewService $viewService,
        private HubInterface $hub,
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
        $this->publishRouteViewUpdate($route);
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
            } catch (Throwable) {
                // Don't break the flow on Mercure failure
            }
        }
    }
}
