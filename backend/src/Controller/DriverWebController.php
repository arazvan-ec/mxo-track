<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/driver')]
#[IsGranted('ROLE_DRIVER')]
final class DriverWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteRepository $routeRepository,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] private readonly string $mercurePublicUrl,
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
        /** @var User $driver */
        $driver = $this->getUser();

        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route || $route->getDriver()?->getId() !== $driver->getId()) {
            throw $this->createNotFoundException('Ruta no encontrada.');
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

        // JSON for map
        $stopsJson = json_encode(array_map(static fn (RouteStop $stop) => [
            'public_id' => $stop->getPublicIdString(),
            'lat' => $stop->getLatitude(),
            'lng' => $stop->getLongitude(),
            'status' => $stop->getStatus()->value,
            'address' => $stop->getAddress(),
            'sequence' => $stop->getSequence(),
            'recipientName' => $stop->getRecipientName(),
            'recipientPhone' => $stop->getRecipientPhone(),
            'notes' => $stop->getNotes(),
            'deliveredAt' => $stop->getDeliveredAt()?->format('d/m/Y H:i'),
        ], $stops), JSON_THROW_ON_ERROR);

        // Vehicle last position
        $vehiclePositionJson = 'null';
        $vehiclePublicId = '';
        $vehicle = $route->getVehicle();

        if ($vehicle !== null) {
            $vehiclePublicId = $vehicle->getPublicIdString();
            $lastPosition = $this->em->getRepository(VehicleLastPosition::class)->findOneBy(['vehicle' => $vehicle]);

            if ($lastPosition instanceof VehicleLastPosition) {
                $vehiclePositionJson = json_encode([
                    'lat' => $lastPosition->getLat(),
                    'lng' => $lastPosition->getLng(),
                ], JSON_THROW_ON_ERROR);
            }
        }

        return $this->render('driver/routes/show.html.twig', [
            'route' => $route,
            'stops' => $stops,
            'stops_json' => $stopsJson,
            'vehicle_position_json' => $vehiclePositionJson,
            'vehicle_public_id' => $vehiclePublicId,
            'mercure_public_url' => $this->mercurePublicUrl,
        ]);
    }
}
