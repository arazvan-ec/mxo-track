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
use App\Service\EtaService;
use App\View\MapViewOptions;
use App\View\RouteViewService;
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
        private readonly EtaService $etaService,
        private readonly RouteViewService $routeViewService,
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

        // Build vehicle tracking data
        $vehiclePublicId = null;
        $vehiclePosition = null;
        $vehicle = $route->getVehicle();

        if ($vehicle !== null) {
            $vehiclePublicId = $vehicle->getPublicIdString();
            $lastPosition = $this->em->getRepository(VehicleLastPosition::class)->findOneBy(['vehicle' => $vehicle]);

            if ($lastPosition instanceof VehicleLastPosition) {
                $vehiclePosition = [
                    'lat' => $lastPosition->getLat(),
                    'lng' => $lastPosition->getLng(),
                ];
            }
        }

        // Build map view data via RouteViewService
        $mapOptions = new MapViewOptions(
            showVehicleTracking: $vehicle !== null,
            showStopStatus: true,
            vehiclePublicId: $vehiclePublicId,
            vehiclePosition: $vehiclePosition,
        );

        $mapView = $this->routeViewService->buildSingleRouteView($route, 'ROLE_DRIVER', $mapOptions);
        $mapView = $mapView->withMercureUrl($this->mercurePublicUrl);

        // Calculate ETAs for active routes
        $etas = [];
        $etasJson = '{}';
        if ($route->getStatus() === RouteStatus::ACTIVE) {
            $etas = $this->etaService->calculateEtas($route);
            $etasJsonData = [];
            foreach ($etas as $stopPubId => $data) {
                $etasJsonData[$stopPubId] = [
                    'eta' => $data['eta']->format(\DATE_ATOM),
                    'eta_formatted' => $data['eta']->format('H:i'),
                    'remaining_minutes' => $data['remainingMinutes'],
                    'distance_km' => $data['distanceKm'],
                ];
            }
            $etasJson = json_encode($etasJsonData, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR);
        }

        return $this->render('driver/routes/show.html.twig', [
            'route' => $route,
            'stops' => $stops,
            'mapViewJson' => $mapView->toJson(),
            'mercure_public_url' => $this->mercurePublicUrl,
            'etas' => $etas,
            'etas_json' => $etasJson,
            'route_public_id' => $route->getPublicIdString(),
        ]);
    }
}
