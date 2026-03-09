<?php

declare(strict_types=1);

namespace App\Application\Fleet;

use App\Entity\Customer;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Service\VisibilityScopeService;
use Doctrine\ORM\EntityManagerInterface;

final readonly class FleetOverviewService
{
    public function __construct(
        private EntityManagerInterface $em,
        private VisibilityScopeService $visibilityScope,
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

        // Build vehicle data with last positions
        $vehiclesData = [];
        foreach ($routeVehicles as $vehicle) {
            $lastPos = $this->em->getRepository(VehicleLastPosition::class)->findOneBy(['vehicle' => $vehicle]);
            $vehiclesData[] = [
                'public_id' => $vehicle->getPublicIdString(),
                'name' => $vehicle->getName(),
                'last_position' => $lastPos !== null ? [
                    'lat' => $lastPos->getLat(),
                    'lng' => $lastPos->getLng(),
                    'speed' => $lastPos->getSpeed(),
                    'course' => $lastPos->getCourse(),
                    'device_time' => $lastPos->getDeviceTime()->format('H:i'),
                ] : null,
            ];
        }

        // Build route data with stops
        $routesData = [];
        foreach ($activeRoutes as $route) {
            $stops = $this->em->createQueryBuilder()
                ->select('rs')
                ->from(RouteStop::class, 'rs')
                ->where('rs.route = :route')
                ->setParameter('route', $route)
                ->orderBy('rs.sequence', 'ASC')
                ->getQuery()
                ->getResult();

            $stopsData = array_map(static fn (RouteStop $s) => [
                'lat' => $s->getLatitude(),
                'lng' => $s->getLongitude(),
                'address' => $s->getAddress(),
                'sequence' => $s->getSequence(),
                'status' => $s->getStatus()->value,
                'recipient' => $s->getRecipientName(),
            ], $stops);

            $total = count($stops);
            $delivered = count(array_filter($stops, static fn (RouteStop $s) => $s->getStatus() === RouteStopStatus::DELIVERED));

            $routesData[] = [
                'public_id' => $route->getPublicIdString(),
                'name' => $route->getName(),
                'status' => $route->getStatus()->value,
                'vehicle_public_id' => $route->getVehicle()?->getPublicIdString(),
                'vehicle_name' => $route->getVehicle()?->getName(),
                'driver_email' => $route->getDriver()?->getEmail(),
                'stops' => $stopsData,
                'total_stops' => $total,
                'delivered_stops' => $delivered,
            ];
        }

        return new FleetMapData(vehicles: $vehiclesData, routes: $routesData);
    }

    public function getFleetSummary(): FleetSummary
    {
        $activeRoutes = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', RouteStatus::ACTIVE)
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
