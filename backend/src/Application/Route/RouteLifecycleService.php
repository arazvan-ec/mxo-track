<?php

declare(strict_types=1);

namespace App\Application\Route;

use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteStarted;
use App\Entity\Route;
use App\Entity\User;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class RouteLifecycleService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouteRepository $routeRepo,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotOwnedException
     */
    public function startRoute(string $routePublicId, User $driver): Route
    {
        $route = $this->resolveRouteForDriver($routePublicId, $driver);

        $route->start();
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

        $route->finish();
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
