<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Route\RoutePlanningService;
use App\Entity\Customer;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Form\RouteStopType;
use App\Form\RouteType;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use App\Service\RouteOptimizationService;
use App\View\MapViewOptions;
use App\View\RouteViewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/routes')]
#[IsGranted('ROLE_ADMIN')]
class RouteAdminController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteRepository $routeRepository,
        private readonly RouteStopRepository $routeStopRepository,
        private readonly RouteOptimizationService $optimizationService,
        private readonly RoutePlanningService $routePlanningService,
        private readonly RouteViewService $routeViewService,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] private readonly string $mercurePublicUrl,
    ) {}

    #[SymfonyRoute('', name: 'admin_routes_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;
        $currentStatus = $request->query->getString('status', '');
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

        // Apply filters to both query builders
        if ($currentStatus !== '' && RouteStatus::tryFrom($currentStatus) !== null) {
            $qb->andWhere('r.status = :status')->setParameter('status', $currentStatus);
            $countQb->andWhere('r.status = :status')->setParameter('status', $currentStatus);
        }

        if ($dateFrom !== '') {
            try {
                $from = new \DateTimeImmutable($dateFrom . ' 00:00:00');
                $qb->andWhere('r.startAt >= :dateFrom')->setParameter('dateFrom', $from);
                $countQb->andWhere('r.startAt >= :dateFrom')->setParameter('dateFrom', $from);
            } catch (\Exception) {
                // ignore invalid date
            }
        }

        if ($dateTo !== '') {
            try {
                $to = new \DateTimeImmutable($dateTo . ' 23:59:59');
                $qb->andWhere('r.startAt <= :dateTo')->setParameter('dateTo', $to);
                $countQb->andWhere('r.startAt <= :dateTo')->setParameter('dateTo', $to);
            } catch (\Exception) {
                // ignore invalid date
            }
        }

        if ($driverId !== '') {
            $qb->andWhere('d.id = :driverId')->setParameter('driverId', $driverId);
            $countQb->leftJoin('r.driver', 'cd')->andWhere('cd.id = :driverId')->setParameter('driverId', $driverId);
        }

        if ($customerId !== '') {
            $qb->andWhere('c.id = :customerId')->setParameter('customerId', $customerId);
            $countQb->leftJoin('r.customer', 'cc')->andWhere('cc.id = :customerId')->setParameter('customerId', $customerId);
        }

        $routes = $qb->getQuery()->getResult();

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));

        // Stop counts per route
        $stopCounts = [];
        if (\count($routes) > 0) {
            $rows = $this->em->createQueryBuilder()
                ->select('IDENTITY(s.route) as routeId, COUNT(s.id) as total, SUM(CASE WHEN s.status = :delivered THEN 1 ELSE 0 END) as delivered')
                ->from(RouteStop::class, 's')
                ->where('s.route IN (:routes)')
                ->setParameter('routes', $routes)
                ->setParameter('delivered', RouteStopStatus::DELIVERED)
                ->groupBy('s.route')
                ->getQuery()
                ->getResult();

            foreach ($rows as $row) {
                $stopCounts[$row['routeId']] = [
                    'total' => (int) $row['total'],
                    'delivered' => (int) $row['delivered'],
                ];
            }
        }

        // Load drivers and customers for filter dropdowns
        $drivers = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where("JSON_TEXT(u.roles) LIKE :driverRole")
            ->setParameter('driverRole', '%ROLE_DRIVER%')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        $customers = $this->em->createQueryBuilder()
            ->select('cust')
            ->from(Customer::class, 'cust')
            ->where('cust.isActive = true')
            ->orderBy('cust.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Build filter params for pagination links
        $filterParams = array_filter([
            'status' => $currentStatus,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'driver' => $driverId,
            'customer' => $customerId,
        ], fn(string $v): bool => $v !== '');

        return $this->render('admin/route/index.html.twig', [
            'routes' => $routes,
            'stopCounts' => $stopCounts,
            'page' => $page,
            'totalPages' => $totalPages,
            'currentStatus' => $currentStatus,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'driverId' => $driverId,
            'customerId' => $customerId,
            'drivers' => $drivers,
            'customers' => $customers,
            'filterParams' => $filterParams,
        ]);
    }

    #[SymfonyRoute('/{publicId}/show', name: 'admin_routes_show', methods: ['GET'])]
    public function show(string $publicId): Response
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            throw $this->createNotFoundException('Ruta no encontrada.');
        }

        // Build vehicle tracking data
        $vehiclePublicId = null;
        $vehiclePosition = null;
        $vehicle = $route->getVehicle();

        if ($vehicle !== null) {
            $vehiclePublicId = $vehicle->getPublicIdString();
            $lastPosition = $this->em->getRepository(\App\Entity\VehicleLastPosition::class)->findOneBy([
                'vehicle' => $vehicle,
            ]);

            if ($lastPosition instanceof \App\Entity\VehicleLastPosition) {
                $vehiclePosition = [
                    'lat' => $lastPosition->getLat(),
                    'lng' => $lastPosition->getLng(),
                    'speed' => $lastPosition->getSpeed(),
                    'course' => $lastPosition->getCourse(),
                ];
            }
        }

        $mapOptions = new MapViewOptions(
            showOptimizationMetrics: true,
            showTimingBreakdown: true,
            showVehicleTracking: $vehicle !== null,
            showStopStatus: true,
            vehiclePublicId: $vehiclePublicId,
            vehiclePosition: $vehiclePosition,
        );

        $mapView = $this->routeViewService->buildSingleRouteView($route, 'ROLE_ADMIN', $mapOptions);
        $mapView = $mapView->withMercureUrl($this->mercurePublicUrl);

        $mapArray = $mapView->toArray();
        $viewStops = $mapArray['routes'][0]['stops'] ?? [];

        // Load stop count for progress display
        $stopCounts = $this->em->createQueryBuilder()
            ->select('COUNT(s.id) as total, SUM(CASE WHEN s.status = :delivered THEN 1 ELSE 0 END) as delivered')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->getQuery()
            ->getSingleResult();

        return $this->render('admin/route/show.html.twig', [
            'route' => $route,
            'viewStops' => $viewStops,
            'mapViewJson' => $mapView->toJson(),
            'metrics' => $mapArray['routes'][0]['metrics'] ?? null,
            'timing' => $mapArray['routes'][0]['timing'] ?? null,
            'stopCounts' => [
                'total' => (int) ($stopCounts['total'] ?? 0),
                'delivered' => (int) ($stopCounts['delivered'] ?? 0),
            ],
        ]);
    }

    #[SymfonyRoute('/new', name: 'admin_routes_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $route = new Route('');
        $form = $this->createForm(RouteType::class, $route);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($route);
            $this->routePlanningService->createOriginStopIfNeeded($route);
            $this->em->flush();

            $this->addFlash('success', 'Ruta creada correctamente.');

            return $this->redirectToRoute('admin_routes_edit', [
                'publicId' => $route->getPublicIdString(),
            ]);
        }

        return $this->render('admin/route/form.html.twig', [
            'form' => $form,
            'route' => $route,
        ]);
    }

    #[SymfonyRoute('/{publicId}/edit', name: 'admin_routes_edit', methods: ['GET', 'POST'])]
    public function edit(string $publicId, Request $request): Response
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            throw $this->createNotFoundException('Ruta no encontrada.');
        }

        $form = $this->createForm(RouteType::class, $route);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->routePlanningService->syncOriginStop($route);
            $this->em->flush();

            $this->addFlash('success', 'Ruta actualizada correctamente.');

            return $this->redirectToRoute('admin_routes_edit', [
                'publicId' => $route->getPublicIdString(),
            ]);
        }

        // Load stops ordered by sequence
        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();

        // Stop add form
        $stopForm = $this->createForm(RouteStopType::class, null, [
            'action' => $this->generateUrl('admin_routes_stop_add', [
                'publicId' => $route->getPublicIdString(),
            ]),
        ]);

        // Calculate distances between consecutive stops
        $segmentDistances = [];
        $totalDistance = 0.0;
        for ($i = 1, $count = \count($stops); $i < $count; $i++) {
            $dist = $this->optimizationService->distanceBetweenStops($stops[$i - 1], $stops[$i]);
            $segmentDistances[$stops[$i]->getPublicIdString()] = $dist;
            if ($dist !== null) {
                $totalDistance += $dist;
            }
        }

        return $this->render('admin/route/form.html.twig', [
            'form' => $form,
            'route' => $route,
            'stops' => $stops,
            'stopForm' => $stopForm,
            'segmentDistances' => $segmentDistances,
            'totalDistance' => $totalDistance,
        ]);
    }

    #[SymfonyRoute('/{publicId}/delete', name: 'admin_routes_delete', methods: ['POST'])]
    public function delete(string $publicId, Request $request): Response
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            throw $this->createNotFoundException('Ruta no encontrada.');
        }

        if (!$this->isCsrfTokenValid('delete-route-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_routes_index');
        }

        $route->setStatus(RouteStatus::CANCELLED);
        $this->em->flush();

        $this->addFlash('success', 'Ruta cancelada correctamente.');

        return $this->redirectToRoute('admin_routes_index');
    }

    #[SymfonyRoute('/{publicId}/stops/add', name: 'admin_routes_stop_add', methods: ['POST'])]
    public function addStop(string $publicId, Request $request): Response
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            throw $this->createNotFoundException('Ruta no encontrada.');
        }

        $stopForm = $this->createForm(RouteStopType::class);
        $stopForm->handleRequest($request);

        if ($stopForm->isSubmitted() && $stopForm->isValid()) {
            $this->routePlanningService->addStop($route->getPublicIdString(), $stopForm->getData());
            $this->addFlash('success', 'Parada anadida correctamente.');
        } else {
            $this->addFlash('error', 'Error al anadir la parada. Revisa los datos.');
        }

        return $this->redirectToRoute('admin_routes_edit', [
            'publicId' => $route->getPublicIdString(),
        ]);
    }

    #[SymfonyRoute('/{publicId}/stops/{stopPublicId}/delete', name: 'admin_routes_stop_delete', methods: ['POST'])]
    public function deleteStop(string $publicId, string $stopPublicId, Request $request): Response
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            throw $this->createNotFoundException('Ruta no encontrada.');
        }

        $stop = $this->routeStopRepository->findOneByPublicId($stopPublicId);

        if (!$stop instanceof RouteStop) {
            throw $this->createNotFoundException('Parada no encontrada.');
        }

        if ($stop->getRoute()->getId() !== $route->getId()) {
            throw $this->createNotFoundException('Parada no encontrada.');
        }

        if (!$this->isCsrfTokenValid('delete-stop-' . $stopPublicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_routes_edit', [
                'publicId' => $route->getPublicIdString(),
            ]);
        }

        $this->em->remove($stop);
        $this->em->flush();

        $this->addFlash('success', 'Parada eliminada correctamente.');

        return $this->redirectToRoute('admin_routes_edit', [
            'publicId' => $route->getPublicIdString(),
        ]);
    }

    #[SymfonyRoute('/{publicId}/optimize', name: 'admin_routes_optimize', methods: ['POST'])]
    public function optimize(string $publicId, Request $request): JsonResponse
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            return new JsonResponse(['error' => 'Ruta no encontrada.'], 404);
        }

        if (!$this->isCsrfTokenValid('optimize-route-' . $publicId, $request->request->getString('_token', $request->headers->get('X-CSRF-Token', '')))) {
            return new JsonResponse(['error' => 'Token CSRF invalido.'], 403);
        }

        $result = $this->optimizationService->optimizeStopOrder($route);

        $confirm = $request->request->getBoolean('confirm', false);

        if ($confirm) {
            $this->optimizationService->applyOptimizedOrder($result['optimized']);

            return new JsonResponse([
                'ok' => true,
                'applied' => true,
                'distanceBefore' => round($result['distanceBefore'], 2),
                'distanceAfter' => round($result['distanceAfter'], 2),
            ]);
        }

        // Preview mode: return the proposed order without applying
        $preview = [];
        foreach ($result['optimized'] as $item) {
            $stop = $item['stop'];
            $preview[] = [
                'publicId' => $stop->getPublicIdString(),
                'address' => $stop->getAddress(),
                'currentSequence' => $stop->getSequence(),
                'newSequence' => $item['newSequence'],
                'isOrigin' => $stop->isOrigin(),
            ];
        }

        return new JsonResponse([
            'ok' => true,
            'applied' => false,
            'distanceBefore' => round($result['distanceBefore'], 2),
            'distanceAfter' => round($result['distanceAfter'], 2),
            'stops' => $preview,
        ]);
    }

    #[SymfonyRoute('/{publicId}/stops/reorder', name: 'admin_routes_stops_reorder', methods: ['POST'])]
    public function reorderStops(string $publicId, Request $request): JsonResponse
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            return new JsonResponse(['error' => 'Ruta no encontrada.'], 404);
        }

        if (!$this->isCsrfTokenValid('reorder-stops-' . $publicId, $request->request->getString('_token', $request->headers->get('X-CSRF-Token', '')))) {
            return new JsonResponse(['error' => 'Token CSRF invalido.'], 403);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalido.'], 400);
        }

        if (!isset($payload['order']) || !\is_array($payload['order'])) {
            return new JsonResponse(['error' => 'Se requiere el campo "order".'], 400);
        }

        $this->routePlanningService->reorderStops($publicId, $payload['order']);

        return new JsonResponse(['ok' => true]);
    }
}
