<?php

declare(strict_types=1);

namespace App\Controller\Operator;

use App\Application\Fleet\FleetOverviewService;
use App\Entity\Route as RouteEntity;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Service\AdminMetricsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/operator')]
#[IsGranted('ROLE_ADMIN')]
final class OperatorDashboardController extends AbstractController
{
    public function __construct(
        private readonly AdminMetricsService $metricsService,
        private readonly EntityManagerInterface $em,
        private readonly FleetOverviewService $fleetOverview,
    ) {}

    #[Route('', name: 'operator_dashboard')]
    public function dashboard(): Response
    {
        $metrics = $this->metricsService->collect();

        return $this->render('operator/dashboard.html.twig', [
            'metrics' => $metrics,
        ]);
    }

    #[Route('/dashboard/live', name: 'operator_dashboard_live', methods: ['GET'])]
    public function live(
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] string $mercurePublicUrl,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $todayStart = new \DateTimeImmutable('today midnight');

        // Active routes with vehicle and driver
        $activeRoutes = $this->em->createQueryBuilder()
            ->select('r', 'v', 'd')
            ->from(RouteEntity::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.driver', 'd')
            ->where('r.status IN (:statuses)')
            ->setParameter('statuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED])
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        // Stop counts per route (total, delivered, exception)
        $stopCounts = [];
        if (\count($activeRoutes) > 0) {
            $rows = $this->em->createQueryBuilder()
                ->select(
                    'IDENTITY(s.route) as routeId',
                    'COUNT(s.id) as total',
                    'SUM(CASE WHEN s.status = :delivered THEN 1 ELSE 0 END) as delivered',
                    'SUM(CASE WHEN s.status = :exception THEN 1 ELSE 0 END) as exceptions',
                )
                ->from(RouteStop::class, 's')
                ->where('s.route IN (:routes)')
                ->setParameter('routes', $activeRoutes)
                ->setParameter('delivered', RouteStopStatus::DELIVERED)
                ->setParameter('exception', RouteStopStatus::EXCEPTION)
                ->groupBy('s.route')
                ->getQuery()
                ->getResult();

            foreach ($rows as $row) {
                $stopCounts[$row['routeId']] = [
                    'total' => (int) $row['total'],
                    'delivered' => (int) $row['delivered'],
                    'exceptions' => (int) $row['exceptions'],
                ];
            }
        }

        // Today's stats across all routes
        $deliveriesToday = (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->where('rs.status = :delivered')
            ->andWhere('rs.deliveredAt >= :todayStart')
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('todayStart', $todayStart)
            ->getQuery()
            ->getSingleScalarResult();

        $exceptionsToday = (int) $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :exception')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->setParameter('statuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED, RouteStatus::DONE])
            ->getQuery()
            ->getSingleScalarResult();

        // Total stops across active routes for completion rate
        $totalActiveStops = 0;
        $totalDeliveredStops = 0;
        foreach ($stopCounts as $counts) {
            $totalActiveStops += $counts['total'];
            $totalDeliveredStops += $counts['delivered'];
        }

        $completionRate = $totalActiveStops > 0
            ? round(($totalDeliveredStops / $totalActiveStops) * 100, 1)
            : 0.0;

        // Fleet map data for the embedded map
        $mapData = $this->fleetOverview->getFleetMapData($user);

        return $this->render('operator/dashboard_live.html.twig', [
            'activeRoutes' => $activeRoutes,
            'stopCounts' => $stopCounts,
            'deliveriesToday' => $deliveriesToday,
            'exceptionsToday' => $exceptionsToday,
            'completionRate' => $completionRate,
            'totalActiveRoutes' => \count($activeRoutes),
            'mercure_public_url' => $mercurePublicUrl,
            'vehicles_json' => json_encode($mapData->vehicles, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR),
            'routes_json' => json_encode($mapData->routes, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR),
        ]);
    }
}
