<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Route;
use App\Entity\User;
use App\Repository\RouteRepository;
use App\Service\DriverScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/route-planner')]
#[IsGranted('ROLE_ADMIN')]
class RoutePlannerController extends AbstractController
{
    public function __construct(
        private readonly RouteRepository $routeRepository,
        private readonly DriverScoringService $driverScoringService,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Route planner wizard page.
     */
    #[SymfonyRoute('', name: 'admin_route_planner_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/route_planner/index.html.twig');
    }

    /**
     * Suggest drivers for a given route, ranked by multi-criteria score.
     *
     * Query params:
     *   - route_id: publicId of the route to score drivers for
     */
    #[SymfonyRoute('/suggest-drivers', name: 'admin_route_planner_suggest_drivers', methods: ['GET'])]
    public function suggestDrivers(Request $request): JsonResponse
    {
        $routePublicId = $request->query->getString('route_id', '');

        if ($routePublicId === '') {
            return new JsonResponse(['error' => 'Se requiere el parametro route_id.'], 400);
        }

        $route = $this->routeRepository->findOneByPublicId($routePublicId);

        if (!$route instanceof Route) {
            return new JsonResponse(['error' => 'Ruta no encontrada.'], 404);
        }

        $scores = $this->driverScoringService->scoreDriversForRoute($route);

        $result = [];
        foreach ($scores as $entry) {
            /** @var \App\Entity\User $driver */
            $driver = $entry['driver'];
            $breakdown = $entry['breakdown'];

            // Determine the top criterion
            $topCriterion = $this->getTopCriterion($breakdown);

            $result[] = [
                'driver_public_id' => $driver->getPublicIdString(),
                'driver_name' => $driver->getName() ?? $driver->getEmail(),
                'driver_email' => $driver->getEmail(),
                'score' => $entry['score'],
                'breakdown' => $breakdown,
                'top_criterion' => $topCriterion,
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Confirm route planning and optionally assign drivers to routes.
     *
     * Accepts JSON body:
     *   { "driver_assignments": { "<route_public_id>": "<driver_public_id>", ... } }
     */
    #[SymfonyRoute('/confirm', name: 'admin_route_planner_confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('route-planner-confirm', $csrfToken)) {
            return new JsonResponse(['error' => 'Token CSRF invalido.'], 403);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalido.'], 400);
        }

        /** @var array<string, string> $driverAssignments */
        $driverAssignments = $payload['driver_assignments'] ?? [];

        $assigned = 0;
        $errors = [];

        foreach ($driverAssignments as $routePublicId => $driverPublicId) {
            if (!\is_string($routePublicId) || !\is_string($driverPublicId) || $driverPublicId === '') {
                continue;
            }

            $route = $this->routeRepository->findOneByPublicId($routePublicId);
            if (!$route instanceof Route) {
                $errors[] = sprintf('Ruta "%s" no encontrada.', $routePublicId);
                continue;
            }

            $driver = $this->em->getRepository(User::class)->findOneBy([
                'publicId' => $driverPublicId,
            ]);

            if (!$driver instanceof User) {
                $errors[] = sprintf('Conductor "%s" no encontrado.', $driverPublicId);
                continue;
            }

            $route->setDriver($driver);
            $assigned++;
        }

        $this->em->flush();

        return new JsonResponse([
            'ok' => true,
            'assigned' => $assigned,
            'errors' => $errors,
        ]);
    }

    /**
     * Determine which criterion contributes most to the driver's score.
     *
     * @param array{zone: float, rating: float, workload: float, skills: float} $breakdown
     */
    private function getTopCriterion(array $breakdown): string
    {
        $labels = [
            'zone' => 'Zona',
            'rating' => 'Valoracion',
            'workload' => 'Disponibilidad',
            'skills' => 'Habilidades',
        ];

        $maxKey = 'zone';
        $maxVal = 0.0;

        foreach ($breakdown as $key => $val) {
            if ($val > $maxVal) {
                $maxVal = $val;
                $maxKey = $key;
            }
        }

        return $labels[$maxKey] ?? $maxKey;
    }
}
