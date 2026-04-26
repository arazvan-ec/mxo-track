<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Entity\Customer;
use App\Entity\User;
use App\Enum\RouteStatus;
use App\Service\Admin\FilterDefinition;
use App\Service\Admin\ListFilterApplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/api/admin/routes')]
#[IsGranted('ROLE_ADMIN')]
class RouteListApiController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 25;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ListFilterApplier $filterApplier,
        private readonly RouteStopRepositoryInterface $stopRepository,
    ) {}

    #[SymfonyRoute('', name: 'api_admin_routes_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', self::ITEMS_PER_PAGE)));
        $status = $request->query->getString('status', '');
        $dateFrom = $request->query->getString('date_from', '');
        $dateTo = $request->query->getString('date_to', '');
        $driverId = $request->query->getString('driver', '');
        $customerId = $request->query->getString('customer', '');

        $qb = $this->em->createQueryBuilder()
            ->select('r', 'v', 'd', 'c')
            ->from(Route::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.driver', 'd')
            ->leftJoin('r.customer', 'c')
            ->orderBy('r.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r');

        $this->filterApplier->apply($qb, $countQb, [
            FilterDefinition::enum('r.status', 'status', $status, RouteStatus::class),
            FilterDefinition::dateFrom('r.startAt', 'dateFrom', $dateFrom),
            FilterDefinition::dateTo('r.startAt', 'dateTo', $dateTo),
            FilterDefinition::entity('d.id', 'driverId', $driverId)
                ->withCountJoin('r.driver', 'cd'),
            FilterDefinition::entity('c.id', 'customerId', $customerId)
                ->withCountJoin('r.customer', 'cc'),
        ]);

        /** @var Route[] $routes */
        $routes = $qb->getQuery()->getResult();
        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));

        // Per-route aggregations via the domain repository (DDD boundary:
        // the controller depends on the interface, not on EntityManager for
        // RouteStop access).
        $stopCounts = [];
        $nextStops = [];
        $histograms = [];
        if (\count($routes) > 0) {
            $stopCounts = $this->stopRepository->countsByRoutes($routes);
            $nextStops = $this->stopRepository->findNextPendingStopsByRoutes($routes);
            // Histogram is bucketed in the server's configured timezone so the
            // "today" window and the hour labels stay internally consistent.
            // See docs/superpowers/execution-logs/2026-04-23-routes-widget-audit-fixes.md
            // for the cross-TZ asymmetry follow-up.
            $appTz = new \DateTimeZone(date_default_timezone_get());
            $histograms = $this->stopRepository->findDeliveryHistogramsByRoutes(
                $routes,
                $appTz,
                new \DateTimeImmutable('now', $appTz),
            );
        }

        $items = [];
        foreach ($routes as $route) {
            $counts = $stopCounts[$route->getId()] ?? ['total' => 0, 'delivered' => 0];
            $nextStop = $nextStops[$route->getId()] ?? null;
            $histogram = $histograms[$route->getId()] ?? null;
            $items[] = [
                'publicId' => $route->getPublicIdString(),
                'name' => $route->getName(),
                'customerName' => $route->getCustomer()?->getName(),
                'vehicleName' => $route->getVehicle()?->getName(),
                'driverName' => $route->getDriver()?->getName(),
                'driverEmail' => $route->getDriver()?->getEmail(),
                'status' => $route->getStatus()->value,
                'deliveredStops' => $counts['delivered'],
                'totalStops' => $counts['total'],
                'totalDistanceKm' => $route->getTotalDistanceKm(),
                'estimatedDurationMinutes' => $route->getEstimatedDurationMinutes(),
                'totalWeightKg' => $route->getTotalWeightKg(),
                'totalParcels' => $route->getTotalParcels(),
                'nextStop' => $nextStop,
                'deliveryHistogram' => $histogram,
            ];
        }

        return $this->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $totalPages,
        ]);
    }

    #[SymfonyRoute('/filters', name: 'api_admin_routes_filters', methods: ['GET'])]
    public function filters(): JsonResponse
    {
        $drivers = $this->em->createQueryBuilder()
            ->select('u.id, u.email, u.name')
            ->from(User::class, 'u')
            ->where("JSON_TEXT(u.roles) LIKE :driverRole")
            ->setParameter('driverRole', '%ROLE_DRIVER%')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        $customers = $this->em->createQueryBuilder()
            ->select('cust.id, cust.name')
            ->from(Customer::class, 'cust')
            ->where('cust.isActive = true')
            ->orderBy('cust.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'drivers' => array_map(fn (array $d) => [
                'id' => $d['id'],
                'name' => $d['name'] ?? $d['email'],
                'email' => $d['email'],
            ], $drivers),
            'customers' => array_map(fn (array $c) => [
                'id' => $c['id'],
                'name' => $c['name'],
            ], $customers),
        ]);
    }
}
