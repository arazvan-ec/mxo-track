<?php

declare(strict_types=1);

namespace App\Application\Route;

use App\Domain\Event\RouteOptimized;
use App\Domain\Event\RoutesBuilt;
use App\Domain\Event\StopsReordered;
use App\Domain\Route\Model\RouteMapOptions;
use App\Domain\Route\Service\RouteMapProjection;
use App\Entity\CustomerLocation;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\Vehicle;
use App\Enum\OptimizationOperation;
use App\Enum\OptimizationStepCategory;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Provider\ProviderFactoryRegistry;
use App\Provider\ServiceType;
use App\RouteOptimization\RouteOptimizerInterface;
use App\Service\OptimizationLogger;
use App\Service\RouteBuilder;
use App\Service\RouteCapacityValidator;
use App\Service\RouteOptimizationService;
use App\Service\RouteSnapshotManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class RoutePlanningService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouteBuilder $routeBuilder,
        private RouteOptimizationService $optimizationService,
        private RouteCapacityValidator $capacityValidator,
        private RouteRepositoryInterface $routeRepo,
        private RouteStopRepositoryInterface $stopRepo,
        private EventDispatcherInterface $eventDispatcher,
        private OptimizationLogger $optimizationLogger,
        private RouteSnapshotManager $snapshotManager,
        private RouteMapProjection $routeMapProjection,
        private ProviderFactoryRegistry $providerFactoryRegistry,
    ) {}

    /**
     * Build routes from shipments distributed across vehicles.
     */
    public function buildRoutes(BuildRoutesInput $input): BuildRoutesResult
    {
        $this->optimizationLogger->startOperation(OptimizationOperation::BUILD_ROUTES, [
            'shipmentCount' => \count($input->shipmentPublicIds),
            'vehicleCount' => \count($input->vehiclePublicIds),
            'originPublicId' => $input->originPublicId,
            'maxStopsPerRoute' => $input->maxStopsPerRoute,
        ]);

        $shipmentUlids = [];
        foreach ($input->shipmentPublicIds as $id) {
            try {
                $shipmentUlids[] = Ulid::fromString((string) $id);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        if ($shipmentUlids === []) {
            throw new \InvalidArgumentException('No valid shipment IDs provided.');
        }
        $shipments = $this->em->getRepository(Shipment::class)
            ->createQueryBuilder('s')
            ->where('s.publicId IN (:ids)')
            ->setParameter('ids', array_map(fn(Ulid $u) => $u->toRfc4122(), $shipmentUlids))
            ->getQuery()
            ->getResult();

        $vehicleUlids = [];
        foreach ($input->vehiclePublicIds as $id) {
            try {
                $vehicleUlids[] = Ulid::fromString((string) $id);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        if ($vehicleUlids === []) {
            throw new \InvalidArgumentException('No valid vehicle IDs provided.');
        }
        $vehicles = $this->em->getRepository(Vehicle::class)
            ->createQueryBuilder('v')
            ->where('v.publicId IN (:ids)')
            ->setParameter('ids', array_map(fn(Ulid $u) => $u->toRfc4122(), $vehicleUlids))
            ->getQuery()
            ->getResult();

        $this->optimizationLogger->logStep(
            OptimizationStepCategory::JOB_MAPPING,
            sprintf('Resolvidos %d shipments y %d vehiculos desde base de datos', \count($shipments), \count($vehicles)),
            ['resolvedShipments' => \count($shipments), 'resolvedVehicles' => \count($vehicles)],
        );

        // Filter out shipments with invalid or missing coordinates
        $totalBeforeFilter = \count($shipments);
        $shipments = array_values(array_filter($shipments, static function (Shipment $s): bool {
            $lat = $s->getLatitude();
            $lng = $s->getLongitude();

            return $lat !== null && $lng !== null
                && $lat >= -90.0 && $lat <= 90.0
                && $lng >= -180.0 && $lng <= 180.0;
        }));

        $filteredOut = $totalBeforeFilter - \count($shipments);
        if ($filteredOut > 0) {
            $this->optimizationLogger->logStep(
                OptimizationStepCategory::JOB_MAPPING,
                sprintf('Filtrados %d shipments sin coordenadas validas', $filteredOut),
                ['filteredOut' => $filteredOut],
            );
        }

        if (\count($shipments) === 0) {
            throw new \InvalidArgumentException('No valid shipments found.');
        }

        $customer = $shipments[0]->getCustomer();

        $origin = null;
        if ($input->originPublicId !== null) {
            $origin = $this->em->getRepository(CustomerLocation::class)
                ->findOneBy(['publicId' => $input->originPublicId]);
        }

        $optimizerOverride = null;
        if ($input->optimizerName !== null) {
            $optimizerOverride = $this->providerFactoryRegistry->createByName($input->optimizerName);
            if (!$optimizerOverride instanceof RouteOptimizerInterface) {
                throw new \InvalidArgumentException(sprintf('Provider "%s" is not a route optimizer.', $input->optimizerName));
            }
        }

        $results = $this->routeBuilder->buildRoutes(
            $shipments,
            $vehicles,
            $customer,
            $origin,
            $input->maxStopsPerRoute,
            $optimizerOverride,
        );

        $this->em->flush();

        // Project route map data via domain service
        $routes = array_map(static fn (array $r) => $r['route'], $results);
        $routeViews = $this->routeMapProjection->projectRoutes($routes, new RouteMapOptions(includeValidation: true));

        $routePublicIds = [];
        $response = [];
        foreach ($routeViews as $index => $view) {
            $routePublicIds[] = $view->publicId;
            $data = $view->toArray();
            $data['stopsCount'] = \count($view->stops);
            $response[] = $data;
        }

        $this->eventDispatcher->dispatch(new RoutesBuilt(
            routePublicIds: $routePublicIds,
            shipmentCount: \count($shipments),
            vehicleCount: \count($vehicles),
        ));

        $resultSummary = [
            'routesCreated' => \count($results),
            'shipmentCount' => \count($shipments),
            'vehicleCount' => \count($vehicles),
        ];

        $firstRoute = $results[0]['route'] ?? null;
        $this->optimizationLogger->finishOperation(
            $resultSummary,
            $firstRoute instanceof Route ? $firstRoute : null,
            $customer,
        );
        $this->em->flush();

        return new BuildRoutesResult(
            routesCreated: \count($results),
            routes: $response,
            optimizationLog: $this->optimizationLogger->getLogData(),
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

        $this->optimizationLogger->startOperation(OptimizationOperation::OPTIMIZE_STOPS, [
            'routePublicId' => $routePublicId,
            'apply' => $apply,
        ]);

        $result = $this->optimizationService->optimizeStopOrder($route);
        $distanceBefore = $result['distanceBefore'];
        $distanceAfter = $result['distanceAfter'];
        $improvement = $distanceBefore > 0
            ? (1 - $distanceAfter / $distanceBefore) * 100
            : 0;

        $stops = [];
        if ($apply) {
            // Capture original order before applying optimization
            $originalStopOrder = [];
            foreach ($result['optimized'] as $item) {
                $stop = $item['stop'];
                $originalStopOrder[] = [
                    'sequence' => $stop->getSequence(),
                    'address' => $stop->getAddress(),
                    'recipientName' => $stop->getRecipientName(),
                    'lat' => $stop->getLatitude(),
                    'lng' => $stop->getLongitude(),
                    'isOrigin' => $stop->isOrigin(),
                ];
            }

            $this->optimizationService->applyOptimizedOrder($result['optimized']);
            $route->setTotalDistanceKm($distanceAfter);
            $route->setEstimatedDurationMinutes($result['durationMinutes']);
            $this->em->flush();

            // Create/update RouteSnapshot with optimization results
            $this->snapshotManager->createSnapshot(
                $route,
                distanceBeforeKm: $distanceBefore,
                distanceAfterKm: $distanceAfter,
                originalStopOrder: $originalStopOrder,
            );
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

        $this->optimizationLogger->finishOperation(
            ['distanceBeforeKm' => round($distanceBefore, 2), 'distanceAfterKm' => round($distanceAfter, 2),
             'improvementPercent' => round($improvement, 1), 'durationMinutes' => $result['durationMinutes'], 'applied' => $apply],
            $route,
            $route->getCustomer(),
        );
        $this->em->flush();

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

        $this->eventDispatcher->dispatch(new StopsReordered(
            routePublicId: $routePublicId,
            order: $order,
        ));
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
