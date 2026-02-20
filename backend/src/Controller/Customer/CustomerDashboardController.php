<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use App\Entity\Customer;
use App\Entity\CustomerVehicle;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/customer')]
#[IsGranted('ROLE_CUSTOMER')]
class CustomerDashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] private readonly string $mercurePublicUrl,
    ) {}

    #[SymfonyRoute('/dashboard', name: 'customer_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        $customer = $user->getCustomer();

        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException('No tiene un almacen asociado.');
        }

        // Total shipments (auto-filtered by CustomerTenantFilter for ROLE_CUSTOMER)
        $totalShipments = (int) $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Shipment::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        // Active routes (manual filter by customer — Route is not CustomerScoped)
        $activeRoutes = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r')
            ->where('r.customer = :customer')
            ->andWhere('r.status = :active')
            ->setParameter('customer', $customer)
            ->setParameter('active', RouteStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        // Pending deliveries
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

        // Completed today
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

        // Exceptions
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

        // Active routes with vehicle + driver (last 5)
        $activeRoutesList = $this->em->createQueryBuilder()
            ->select('r', 'v', 'd')
            ->from(Route::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.driver', 'd')
            ->where('r.customer = :customer')
            ->andWhere('r.status = :active')
            ->setParameter('customer', $customer)
            ->setParameter('active', RouteStatus::ACTIVE)
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Stop progress for active routes
        $activeRouteProgress = [];
        if (\count($activeRoutesList) > 0) {
            $rows = $this->em->createQueryBuilder()
                ->select(
                    'IDENTITY(rs.route) as routeId',
                    'COUNT(rs.id) as total',
                    'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as delivered',
                )
                ->from(RouteStop::class, 'rs')
                ->where('rs.route IN (:routes)')
                ->setParameter('routes', $activeRoutesList)
                ->setParameter('delivered', RouteStopStatus::DELIVERED)
                ->groupBy('rs.route')
                ->getQuery()
                ->getResult();

            foreach ($rows as $row) {
                $activeRouteProgress[$row['routeId']] = [
                    'total' => (int) $row['total'],
                    'delivered' => (int) $row['delivered'],
                ];
            }
        }

        // Customer vehicles with last position
        $vehiclesWithPosition = $this->em->createQueryBuilder()
            ->select('cv', 'v', 'vlp')
            ->from(CustomerVehicle::class, 'cv')
            ->join('cv.vehicle', 'v')
            ->leftJoin(VehicleLastPosition::class, 'vlp', 'WITH', 'vlp.vehicle = v')
            ->where('cv.customer = :customer')
            ->andWhere('v.isActive = true')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getResult();

        return $this->render('customer/dashboard.html.twig', [
            'customer' => $customer,
            'kpis' => [
                'total_shipments' => $totalShipments,
                'active_routes' => $activeRoutes,
                'pending_deliveries' => $pendingDeliveries,
                'completed_today' => $completedToday,
                'exceptions' => $exceptions,
            ],
            'activeRoutes' => $activeRoutesList,
            'activeRouteProgress' => $activeRouteProgress,
            'vehiclesWithPosition' => $vehiclesWithPosition,
            'mercure_public_url' => $this->mercurePublicUrl,
        ]);
    }

    #[SymfonyRoute('/dashboard/kpis', name: 'customer_dashboard_kpis', methods: ['GET'])]
    public function kpis(): JsonResponse
    {
        $user = $this->getUser();
        $customer = $user->getCustomer();

        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'No customer'], 403);
        }

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

        return $this->json([
            'total_shipments' => $totalShipments,
            'active_routes' => $activeRoutes,
            'pending_deliveries' => $pendingDeliveries,
            'completed_today' => $completedToday,
            'exceptions' => $exceptions,
        ]);
    }
}
