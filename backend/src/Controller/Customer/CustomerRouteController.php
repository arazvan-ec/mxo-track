<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use App\Entity\Customer;
use App\Entity\RoutePerformanceMetric;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/customer/routes')]
#[IsGranted('ROLE_CUSTOMER')]
class CustomerRouteController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[SymfonyRoute('', name: 'customer_routes_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $customer = $this->getUser()->getCustomer();

        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException('No tiene un cliente asociado.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;
        $currentStatus = $request->query->getString('status', '');

        $qb = $this->em->createQueryBuilder()
            ->select('r', 'v', 'd')
            ->from(Route::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.driver', 'd')
            ->where('r.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('r.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($currentStatus !== '' && RouteStatus::tryFrom($currentStatus) !== null) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $currentStatus);
        }

        $routes = $qb->getQuery()->getResult();

        // Count query
        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r')
            ->where('r.customer = :customer')
            ->setParameter('customer', $customer);

        if ($currentStatus !== '' && RouteStatus::tryFrom($currentStatus) !== null) {
            $countQb->andWhere('r.status = :status')
                ->setParameter('status', $currentStatus);
        }

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));

        // Stop counts per route
        $stopCounts = [];
        if (\count($routes) > 0) {
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

            foreach ($rows as $row) {
                $stopCounts[$row['routeId']] = [
                    'total' => (int) $row['total'],
                    'delivered' => (int) $row['delivered'],
                ];
            }
        }

        // Performance metrics per route
        $routeMetrics = [];
        if (\count($routes) > 0) {
            $metrics = $this->em->getRepository(RoutePerformanceMetric::class)->findBy(['route' => $routes]);
            foreach ($metrics as $m) {
                $routeMetrics[$m->getRoute()->getId()] = $m;
            }
        }

        return $this->render('customer/route/index.html.twig', [
            'routes' => $routes,
            'stopCounts' => $stopCounts,
            'routeMetrics' => $routeMetrics,
            'page' => $page,
            'totalPages' => $totalPages,
            'currentStatus' => $currentStatus,
        ]);
    }

    #[SymfonyRoute('/{publicId}', name: 'customer_routes_show', methods: ['GET'])]
    public function show(string $publicId): Response
    {
        return $this->redirect('/app/customer/routes/' . $publicId);
    }
}
