<?php

declare(strict_types=1);

namespace App\Application\Route;

use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteStarted;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\ValueObject\RouteId;
use App\Entity\User;
use App\Entity\VehicleInspection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class RouteLifecycleService
{
    public function __construct(
        private RouteRepositoryInterface $routeRepo,
        private EventDispatcherInterface $eventDispatcher,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @throws RouteNotFoundException
     * @throws RouteNotOwnedException
     * @throws InspectionNotCompletedException
     */
    public function startRoute(string $routePublicId, User $driver): Route
    {
        $route = $this->resolveRouteForDriver($routePublicId, $driver);

        $inspection = $this->findInspectionForRoute($routePublicId);
        if (!$inspection instanceof VehicleInspection || !$inspection->allItemsChecked()) {
            throw new InspectionNotCompletedException();
        }

        $route->start();
        $this->routeRepo->save($route);

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
        $this->routeRepo->save($route);

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
        $route = $this->routeRepo->findById(new RouteId($routePublicId));
        if ($route === null) {
            throw new RouteNotFoundException($routePublicId);
        }

        if ($route->driverId() !== (int) $driver->getId()) {
            throw new RouteNotOwnedException();
        }

        return $route;
    }

    /**
     * Cross-context query: VehicleInspection is pragmatic context.
     * Uses EM with join to avoid depending on the Doctrine Route entity.
     */
    private function findInspectionForRoute(string $routePublicId): ?VehicleInspection
    {
        return $this->em->createQueryBuilder()
            ->select('vi')
            ->from(VehicleInspection::class, 'vi')
            ->join('vi.route', 'r')
            ->where('r.publicId = :pid')
            ->setParameter('pid', Ulid::fromString($routePublicId))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
