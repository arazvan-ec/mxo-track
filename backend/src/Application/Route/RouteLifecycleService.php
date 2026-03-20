<?php

declare(strict_types=1);

namespace App\Application\Route;

use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteStarted;
use App\Domain\Route\Repository\RouteEventRepositoryInterface;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteEvent;
use App\Entity\User;
use App\Entity\VehicleInspection;
use App\Enum\RouteEventType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class RouteLifecycleService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouteRepositoryInterface $routeRepo,
        private RouteEventRepositoryInterface $eventRepo,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotOwnedException
     * @throws InspectionNotCompletedException
     */
    public function startRoute(string $routePublicId, User $driver): Route
    {
        $route = $this->resolveRouteForDriver($routePublicId, $driver);

        $inspection = $this->em->getRepository(VehicleInspection::class)->findOneBy(['route' => $route]);
        if (!$inspection instanceof VehicleInspection || !$inspection->allItemsChecked()) {
            throw new InspectionNotCompletedException();
        }

        // Event-first: create event → apply → persist
        $routeEvent = new RouteEvent(
            route: $route,
            eventType: RouteEventType::STARTED,
            actorType: 'driver',
            payload: ['driver_user_id' => (int) $driver->getId()],
        );
        $route->apply($routeEvent);
        $this->eventRepo->save($routeEvent);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new RouteStarted(
            routePublicId: $routePublicId,
            driverUserId: (int) $driver->getId(),
        ));

        return $route;
    }

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotOwnedException
     */
    public function finishRoute(string $routePublicId, User $driver): Route
    {
        $route = $this->resolveRouteForDriver($routePublicId, $driver);

        // Event-first: create event → apply → persist
        $routeEvent = new RouteEvent(
            route: $route,
            eventType: RouteEventType::COMPLETED,
            actorType: 'driver',
            payload: ['driver_user_id' => (int) $driver->getId()],
        );
        $route->apply($routeEvent);
        $this->eventRepo->save($routeEvent);
        $this->em->flush();

        $this->eventDispatcher->dispatch(new RouteCompleted(
            routePublicId: $routePublicId,
            driverUserId: (int) $driver->getId(),
        ));

        return $route;
    }

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotOwnedException
     */
    private function resolveRouteForDriver(string $routePublicId, User $driver): Route
    {
        $route = $this->routeRepo->findOneByPublicId($routePublicId);
        if (!$route instanceof Route) {
            throw new RouteNotFoundException($routePublicId);
        }

        if ($route->getDriver()?->getId() !== $driver->getId()) {
            throw new RouteNotOwnedException();
        }

        return $route;
    }
}
