<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Route;
use App\Repository\RouteRepository;
use App\Service\DriverScoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/route-planner')]
#[IsGranted('ROLE_ADMIN')]
class RoutePlannerController extends AbstractController
{
    public function __construct(
        private readonly RouteRepository $routeRepository,
        private readonly DriverScoringService $driverScoringService,
    ) {}

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
