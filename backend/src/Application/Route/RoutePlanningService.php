<?php

declare(strict_types=1);

namespace App\Application\Route;

use App\Domain\Event\RouteOptimized;
use App\Domain\Event\RoutesBuilt;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use App\Service\RouteBuilder;
use App\Service\RouteCapacityValidator;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class RoutePlanningService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouteBuilder $routeBuilder,
        private RouteOptimizationService $optimizationService,
        private RouteCapacityValidator $capacityValidator,
        private RouteRepository $routeRepo,
        private RouteStopRepository $stopRepo,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Build routes from shipments distributed across vehicles.
     */
    public function buildRoutes(BuildRoutesInput $input): BuildRoutesResult
    {
        $shipments = $this->em->getRepository(Shipment::class)
            ->createQueryBuilder('s')
            ->where('s.publicId IN (:ids)')
            ->setParameter('ids', $input->shipmentPublicIds)
            ->getQuery()
            ->getResult();

        $vehicles = $this->em->getRepository(Vehicle::class)
            ->createQueryBuilder('v')
            ->where('v.publicId IN (:ids)')
            ->setParameter('ids', $input->vehiclePublicIds)
            ->getQuery()
            ->getResult();

        if (\count($shipments) === 0) {
            throw new \InvalidArgumentException('No valid shipments found.');
        }

        $customer = $shipments[0]->getCustomer();

        $origin = null;
        if ($input->originPublicId !== null) {
            $origin = $this->em->getRepository(CustomerLocation::class)
                ->findOneBy(['publicId' => $input->originPublicId]);
        }

        $results = $this->routeBuilder->buildRoutes(
            $shipments,
            $vehicles,
            $customer,
            $origin,
            $input->maxStopsPerRoute,
        );

        $this->em->flush();

        $routePublicIds = [];
        $response = [];
        foreach ($results as $result) {
            $route = $result['route'];
            $routePublicIds[] = $route->getPublicIdString();
            $response[] = [
                'route' => [
                    'publicId' => $route->getPublicIdString(),
                    'name' => $route->getName(),
                    'vehicle' => $route->getVehicle()?->getName(),
                    'totalDistanceKm' => $route->getTotalDistanceKm(),
                    'estimatedDurationMinutes' => $route->getEstimatedDurationMinutes(),
                ],
                'stopsCount' => \count($result['stops']),
                'validation' => $result['validation'],
            ];
        }

        $this->eventDispatcher->dispatch(new RoutesBuilt(
            routePublicIds: $routePublicIds,
            shipmentCount: \count($shipments),
            vehicleCount: \count($vehicles),
        ));

        return new BuildRoutesResult(
            routesCreated: \count($results),
            routes: $response,
        );
    }

    /**
     * Optimize stop order for a route.
     *
     * @throws RouteNotFoundException
     */
    public function optimizeRoute(string $routePublicId, bool $apply = false): OptimizationResult
    {
        $route = $this->routeRepo->findOneByPublicId($routePublicId);
        if (!$route instanceof Route) {
            throw new RouteNotFoundException($routePublicId);
        }

        $result = $this->optimizationService->optimizeStopOrder($route);
        $distanceBefore = $result['distanceBefore'];
        $distanceAfter = $result['distanceAfter'];
        $improvement = $distanceBefore > 0
            ? (1 - $distanceAfter / $distanceBefore) * 100
            : 0;

        $stops = [];
        if ($apply) {
            $this->optimizationService->applyOptimizedOrder($result['optimized']);
            $route->setTotalDistanceKm($distanceAfter);
            $route->setEstimatedDurationMinutes($result['durationMinutes']);
            $this->em->flush();

            $this->eventDispatcher->dispatch(new RouteOptimized(
                routePublicId: $routePublicId,
                improvementPercent: $improvement,
                distanceKm: $distanceAfter,
                durationMinutes: $result['durationMinutes'],
            ));
        } else {
            foreach ($result['optimized'] as $item) {
                $stop = $item['stop'];
                $stops[] = [
                    'publicId' => $stop->getPublicIdString(),
                    'address' => $stop->getAddress(),
                    'currentSequence' => $stop->getSequence(),
                    'newSequence' => $item['newSequence'],
                    'isOrigin' => $stop->isOrigin(),
                ];
            }
        }

        return new OptimizationResult(
            applied: $apply,
            distanceBefore: $distanceBefore,
            distanceAfter: $distanceAfter,
            improvementPercent: $improvement,
            durationMinutes: $result['durationMinutes'] ?? null,
            stops: $stops,
        );
    }

    /**
     * Reorder stops for a route.
     *
     * @param array<int, string> $order Map of sequence index → stop publicId
     * @throws RouteNotFoundException
     */
    public function reorderStops(string $routePublicId, array $order): void
    {
        $route = $this->routeRepo->findOneByPublicId($routePublicId);
        if (!$route instanceof Route) {
            throw new RouteNotFoundException($routePublicId);
        }

        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->getQuery()
            ->getResult();

        $stopsMap = [];
        foreach ($stops as $stop) {
            $stopsMap[$stop->getPublicIdString()] = $stop;
        }

        foreach ($order as $seq => $stopPublicId) {
            if (isset($stopsMap[$stopPublicId])) {
                $stopsMap[$stopPublicId]->setSequence((int) $seq);
            }
        }

        $this->em->flush();
    }

    /**
     * Add a stop to a route at the next sequence position.
     *
     * @param array<string, mixed> $data Stop data (address, latitude, longitude, recipientName, recipientPhone, notes)
     * @throws RouteNotFoundException
     */
    public function addStop(string $routePublicId, array $data): RouteStop
    {
        $route = $this->routeRepo->findOneByPublicId($routePublicId);
        if (!$route instanceof Route) {
            throw new RouteNotFoundException($routePublicId);
        }

        $maxSequence = $this->em->createQueryBuilder()
            ->select('MAX(s.sequence)')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->getQuery()
            ->getSingleScalarResult();

        $nextSequence = $maxSequence !== null ? ((int) $maxSequence) + 1 : 1;

        $stop = new RouteStop($route, $nextSequence, $data['address']);

        if (isset($data['latitude']) && $data['latitude'] !== null) {
            $stop->setLatitude((float) $data['latitude']);
        }
        if (isset($data['longitude']) && $data['longitude'] !== null) {
            $stop->setLongitude((float) $data['longitude']);
        }
        if (isset($data['recipientName']) && $data['recipientName'] !== null) {
            $stop->setRecipientName($data['recipientName']);
        }
        if (isset($data['recipientPhone']) && $data['recipientPhone'] !== null) {
            $stop->setRecipientPhone($data['recipientPhone']);
        }
        if (isset($data['notes']) && $data['notes'] !== null) {
            $stop->setNotes($data['notes']);
        }

        $this->em->persist($stop);
        $this->em->flush();

        return $stop;
    }

    /**
     * Remove a stop from a route.
     *
     * @throws RouteNotFoundException
     */
    public function removeStop(string $routePublicId, string $stopPublicId): void
    {
        $route = $this->routeRepo->findOneByPublicId($routePublicId);
        if (!$route instanceof Route) {
            throw new RouteNotFoundException($routePublicId);
        }

        $stop = $this->stopRepo->findOneByPublicId($stopPublicId);
        if (!$stop instanceof RouteStop) {
            return;
        }

        if ($stop->getRoute()->getId() !== $route->getId()) {
            return;
        }

        $this->em->remove($stop);
        $this->em->flush();
    }

    /**
     * Synchronize origin stop with the route's origin location.
     * Removes existing origin stop and recreates from CustomerLocation.
     */
    public function syncOriginStop(Route $route): void
    {
        $existingOrigin = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->andWhere('s.isOrigin = true')
            ->setParameter('route', $route)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingOrigin !== null) {
            $this->em->remove($existingOrigin);
        }

        $this->createOriginStopIfNeeded($route);
    }

    /**
     * Create origin stop from the route's CustomerLocation if set.
     */
    public function createOriginStopIfNeeded(Route $route): void
    {
        $origin = $route->getOriginLocation();
        if ($origin === null) {
            return;
        }

        $stop = new RouteStop($route, 0, $origin->getAddress());
        $stop->setLatitude($origin->getLatitude());
        $stop->setLongitude($origin->getLongitude());
        $stop->setOrigin(true);
        $this->em->persist($stop);
    }
}
