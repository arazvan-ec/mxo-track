<?php

declare(strict_types=1);

namespace App\Application\Fleet;

use App\Domain\Route\Model\RouteMapOptions;
use App\Domain\Route\Service\RouteMapProjection;
use App\Entity\Customer;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Enum\VehicleSkill;
use App\Service\VisibilityScopeService;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FleetOverviewService
{
    public function __construct(
        private EntityManagerInterface $em,
        private VisibilityScopeService $visibilityScope,
        private RouteMapProjection $routeMapProjection,
    ) {}

    public function getFleetMapData(User $user): FleetMapData
    {
        $activeRoutes = $this->em->createQueryBuilder()
            ->select('r', 'v', 'd')
            ->from(Route::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.driver', 'd')
            ->where('r.status IN (:statuses)')
            ->setParameter('statuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED])
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        // Collect vehicles assigned to active routes
        $routeVehicles = [];
        foreach ($activeRoutes as $route) {
            $vehicle = $route->getVehicle();
            if ($vehicle !== null && $vehicle->isActive()) {
                $routeVehicles[$vehicle->getId()] = $vehicle;
            }
        }

        // Apply visibility scope for non-admin users
        if (!$user->hasRole('ROLE_ADMIN')) {
            $allowedIds = array_flip($this->visibilityScope->vehicleIdsFor($user));
            $routeVehicles = array_filter($routeVehicles, static fn (Vehicle $v) => isset($allowedIds[$v->getId()]));
        }

        // Build vehicle-to-route mapping for enrichment
        $vehicleRouteMap = [];
        foreach ($activeRoutes as $route) {
            $v = $route->getVehicle();
            if ($v !== null) {
                $vehicleRouteMap[$v->getId()] = [
                    'route_name' => $route->getName(),
                    'driver_name' => $route->getDriver()?->getName() ?? $route->getDriver()?->getEmail(),
                ];
            }
        }

        // Build vehicle data with last positions, skills, route/driver info
        $vehiclesData = [];
        foreach ($routeVehicles as $vehicle) {
            $lastPos = $this->em->getRepository(VehicleLastPosition::class)->findOneBy(['vehicle' => $vehicle]);
            $skills = $vehicle->getSkills();
            $primarySkill = $skills[0] ?? null;
            $routeInfo = $vehicleRouteMap[$vehicle->getId()] ?? ['route_name' => null, 'driver_name' => null];

            $vehiclesData[] = [
                'public_id' => $vehicle->getPublicIdString(),
                'name' => $vehicle->getName(),
                'skills' => array_map(static fn (VehicleSkill $s) => $s->name, $skills),
                'marker_color' => $primarySkill?->markerColor() ?? VehicleSkill::defaultMarkerColor(),
                'route_name' => $routeInfo['route_name'],
                'driver_name' => $routeInfo['driver_name'],
                'last_position' => $lastPos !== null ? [
                    'lat' => $lastPos->getLat(),
                    'lng' => $lastPos->getLng(),
                    'speed' => $lastPos->getSpeed(),
                    'course' => $lastPos->getCourse(),
                    'device_time' => $lastPos->getDeviceTime()->format('H:i'),
                ] : null,
            ];
        }

        // Project route map data via domain service (single snapshot query)
        $routeViews = $this->routeMapProjection->projectRoutes($activeRoutes, RouteMapOptions::minimal());

        $routesData = [];
        foreach ($routeViews as $view) {
            $data = $view->toArray();
            // Add fleet-specific counts from projected stops
            $totalStops = \count($view->stops);
            $deliveredStops = \count(array_filter($view->stops, static fn ($s) => $s->status === 'DELIVERED'));
            $data['totalStops'] = $totalStops;
            $data['deliveredStops'] = $deliveredStops;
            $routesData[] = $data;
        }

        return new FleetMapData(vehicles: $vehiclesData, routes: $routesData);
    }

    public function getFleetSummary(): FleetSummary
    {
        $activeRoutes = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r')
            ->where('r.status IN (:statuses)')
            ->setParameter('statuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED])
            ->getQuery()
            ->getSingleScalarResult();

        $totalVehicles = (int) $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(Vehicle::class, 'v')
            ->where('v.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();

        $pendingStops = (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('r.status = :status')
            ->andWhere('rs.status = :pending')
            ->setParameter('status', RouteStatus::ACTIVE)
            ->setParameter('pending', RouteStopStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return new FleetSummary(
            activeRoutes: $activeRoutes,
            totalVehicles: $totalVehicles,
            pendingStops: $pendingStops,
        );
    }

    public function getCustomerKpis(Customer $customer): CustomerKpis
    {
        $totalShipments = (int) $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Shipment::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        $activeRoutes = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r')
            ->where('r.customer = :customer')
            ->andWhere('r.status = :active')
            ->setParameter('customer', $customer)
            ->setParameter('active', RouteStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        $pendingDeliveries = (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('r.customer = :customer')
            ->andWhere('rs.status = :pending')
            ->setParameter('customer', $customer)
            ->setParameter('pending', RouteStopStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $todayStart = new \DateTimeImmutable('today midnight');

        $completedToday = (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('r.customer = :customer')
            ->andWhere('rs.status = :delivered')
            ->andWhere('rs.deliveredAt >= :todayStart')
            ->setParameter('customer', $customer)
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('todayStart', $todayStart)
            ->getQuery()
            ->getSingleScalarResult();

        $exceptions = (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('r.customer = :customer')
            ->andWhere('rs.status = :exception')
            ->setParameter('customer', $customer)
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->getQuery()
            ->getSingleScalarResult();

        return new CustomerKpis(
            totalShipments: $totalShipments,
            activeRoutes: $activeRoutes,
            pendingDeliveries: $pendingDeliveries,
            completedToday: $completedToday,
            exceptions: $exceptions,
        );
    }

    /**
     * @return array<string, array{total: int, delivered: int}>
     */
    public function getActiveRoutesProgress(Customer $customer, int $limit = 5): array
    {
        $routes = $this->em->createQueryBuilder()
            ->select('r', 'v', 'd')
            ->from(Route::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.driver', 'd')
            ->where('r.customer = :customer')
            ->andWhere('r.status = :active')
            ->setParameter('customer', $customer)
            ->setParameter('active', RouteStatus::ACTIVE)
            ->orderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        if (\count($routes) === 0) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select(
                'IDENTITY(rs.route) as routeId',
                'COUNT(rs.id) as total',
                'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as delivered',
            )
            ->from(RouteStop::class, 'rs')
            ->where('rs.route IN (:routes)')
            ->setParameter('routes', $routes)
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->groupBy('rs.route')
            ->getQuery()
            ->getResult();

        $progress = [];
        foreach ($rows as $row) {
            $progress[$row['routeId']] = [
                'total' => (int) $row['total'],
                'delivered' => (int) $row['delivered'],
            ];
        }

        return $progress;
    }
}
