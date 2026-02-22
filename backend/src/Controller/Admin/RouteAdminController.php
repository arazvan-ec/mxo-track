<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Form\RouteStopType;
use App\Form\RouteType;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {}

    #[SymfonyRoute('', name: 'admin_routes_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;
        $currentStatus = $request->query->getString('status', '');

        $qb = $this->em->createQueryBuilder()
            ->select('r', 'v', 'd')
            ->from(Route::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.driver', 'd')
            ->orderBy('r.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($currentStatus !== '' && RouteStatus::tryFrom($currentStatus) !== null) {
            $qb->where('r.status = :status')
                ->setParameter('status', $currentStatus);
        }

        $routes = $qb->getQuery()->getResult();

        // Count query
        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r');

        if ($currentStatus !== '' && RouteStatus::tryFrom($currentStatus) !== null) {
            $countQb->where('r.status = :status')
                ->setParameter('status', $currentStatus);
        }

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

        return $this->render('admin/route/index.html.twig', [
            'routes' => $routes,
            'stopCounts' => $stopCounts,
            'page' => $page,
            'totalPages' => $totalPages,
            'currentStatus' => $currentStatus,
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
            $this->createOriginStopIfNeeded($route);
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
            $this->syncOriginStop($route);
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
            $data = $stopForm->getData();

            // Calculate next sequence
            $maxSequence = $this->em->createQueryBuilder()
                ->select('MAX(s.sequence)')
                ->from(RouteStop::class, 's')
                ->where('s.route = :route')
                ->setParameter('route', $route)
                ->getQuery()
                ->getSingleScalarResult();

            $nextSequence = $maxSequence !== null ? ((int) $maxSequence) + 1 : 1;

            $stop = new RouteStop($route, $nextSequence, $data['address']);

            if (isset($data['latitude']) && $data['latitude'] !== null) {
                $stop->setLatitude((float) $data['latitude']);
            }
            if (isset($data['longitude']) && $data['longitude'] !== null) {
                $stop->setLongitude((float) $data['longitude']);
            }
            if (isset($data['recipientName']) && $data['recipientName'] !== null) {
                $stop->setRecipientName($data['recipientName']);
            }
            if (isset($data['recipientPhone']) && $data['recipientPhone'] !== null) {
                $stop->setRecipientPhone($data['recipientPhone']);
            }
            if (isset($data['notes']) && $data['notes'] !== null) {
                $stop->setNotes($data['notes']);
            }

            $this->em->persist($stop);
            $this->em->flush();

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

        // Load all stops for this route
        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->getQuery()
            ->getResult();

        // Index by publicId
        $stopsMap = [];
        foreach ($stops as $stop) {
            $stopsMap[$stop->getPublicIdString()] = $stop;
        }

        // Apply the new order
        foreach ($payload['order'] as $seq => $stopPublicId) {
            if (isset($stopsMap[$stopPublicId])) {
                $stopsMap[$stopPublicId]->setSequence((int) $seq);
            }
        }

        $this->em->flush();

        return new JsonResponse(['ok' => true]);
    }

    private function createOriginStopIfNeeded(Route $route): void
    {
        $origin = $route->getOriginLocation();
        if ($origin === null) {
            return;
        }

        $stop = new RouteStop($route, 0, $origin->getAddress());
        $stop->setLatitude($origin->getLatitude());
        $stop->setLongitude($origin->getLongitude());
        $stop->setOrigin(true);
        $this->em->persist($stop);
    }

    private function syncOriginStop(Route $route): void
    {
        // Remove existing origin stop
        $existingOrigin = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->andWhere('s.isOrigin = true')
            ->setParameter('route', $route)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingOrigin !== null) {
            $this->em->remove($existingOrigin);
        }

        // Create new one if origin location is set
        $this->createOriginStopIfNeeded($route);
    }
}
