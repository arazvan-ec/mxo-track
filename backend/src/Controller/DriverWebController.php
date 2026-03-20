<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\User;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/driver')]
#[IsGranted('ROLE_DRIVER')]
final class DriverWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[SymfonyRoute('/routes', name: 'driver_routes_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $driver */
        $driver = $this->getUser();

        $routes = $this->em->createQueryBuilder()
            ->select('r', 'v', 'c')
            ->from(Route::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.customer', 'c')
            ->where('r.driver = :driver')
            ->setParameter('driver', $driver)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        // Stop counts per route
        $stopCounts = [];
        if (count($routes) > 0) {
            $rows = $this->em->createQueryBuilder()
                ->select(
                    'IDENTITY(rs.route) as routeId',
                    'COUNT(rs.id) as total',
                    'SUM(CASE WHEN rs.status = :delivered THEN 1 ELSE 0 END) as delivered',
                    'SUM(CASE WHEN rs.status = :exception THEN 1 ELSE 0 END) as exceptions',
                )
                ->from(RouteStop::class, 'rs')
                ->where('rs.route IN (:routes)')
                ->setParameter('routes', $routes)
                ->setParameter('delivered', RouteStopStatus::DELIVERED)
                ->setParameter('exception', RouteStopStatus::EXCEPTION)
                ->groupBy('rs.route')
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

        return $this->render('driver/routes/index.html.twig', [
            'routes' => $routes,
            'stopCounts' => $stopCounts,
        ]);
    }

    #[SymfonyRoute('/routes/{publicId}', name: 'driver_routes_show', methods: ['GET'])]
    public function show(string $publicId): Response
    {
        return $this->redirect('/app/driver/routes/' . $publicId);
    }
}
