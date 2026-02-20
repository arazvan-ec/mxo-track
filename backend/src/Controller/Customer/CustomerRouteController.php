<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use App\Entity\Customer;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly RouteRepository $routeRepository,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] private readonly string $mercurePublicUrl,
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

        return $this->render('customer/route/index.html.twig', [
            'routes' => $routes,
            'stopCounts' => $stopCounts,
            'page' => $page,
            'totalPages' => $totalPages,
            'currentStatus' => $currentStatus,
        ]);
    }

    #[SymfonyRoute('/{publicId}', name: 'customer_routes_show', methods: ['GET'])]
    public function show(string $publicId): Response
    {
        $customer = $this->getUser()->getCustomer();

        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException('No tiene un cliente asociado.');
        }

        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            throw $this->createNotFoundException('Ruta no encontrada.');
        }

        // Verify this route belongs to the customer
        if ($route->getCustomer() === null || $route->getCustomer()->getId() !== $customer->getId()) {
            throw $this->createAccessDeniedException('No tiene acceso a esta ruta.');
        }

        // Load stops ordered by sequence
        $stops = $this->em->createQueryBuilder()
            ->select('rs')
            ->from(RouteStop::class, 'rs')
            ->where('rs.route = :route')
            ->setParameter('route', $route)
            ->orderBy('rs.sequence', 'ASC')
            ->getQuery()
            ->getResult();

        // Prepare stops JSON for the map
        $stopsJson = json_encode(array_map(static fn (RouteStop $stop) => [
            'lat' => $stop->getLatitude(),
            'lng' => $stop->getLongitude(),
            'status' => $stop->getStatus()->value,
            'address' => $stop->getAddress(),
            'sequence' => $stop->getSequence(),
            'recipientName' => $stop->getRecipientName(),
            'deliveredAt' => $stop->getDeliveredAt()?->format('d/m/Y H:i'),
        ], $stops), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_THROW_ON_ERROR);

        // Load vehicle last position if route has a vehicle
        $vehiclePositionJson = 'null';
        $vehiclePublicId = '';
        $vehicle = $route->getVehicle();

        if ($vehicle !== null) {
            $vehiclePublicId = $vehicle->getPublicIdString();
            $lastPosition = $this->em->getRepository(VehicleLastPosition::class)->findOneBy([
                'vehicle' => $vehicle,
            ]);

            if ($lastPosition instanceof VehicleLastPosition) {
                $vehiclePositionJson = json_encode([
                    'lat' => $lastPosition->getLat(),
                    'lng' => $lastPosition->getLng(),
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
            }
        }

        return $this->render('customer/route/show.html.twig', [
            'route' => $route,
            'stops' => $stops,
            'stops_json' => $stopsJson,
            'vehicle_position_json' => $vehiclePositionJson,
            'vehicle_public_id' => $vehiclePublicId,
            'mercure_public_url' => $this->mercurePublicUrl,
        ]);
    }
}
