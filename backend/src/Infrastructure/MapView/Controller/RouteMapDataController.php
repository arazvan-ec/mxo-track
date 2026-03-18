<?php

declare(strict_types=1);

namespace App\Infrastructure\MapView\Controller;

use App\Entity\User;
use App\Repository\RouteRepository;
use App\View\MapViewOptions;
use App\View\RouteViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class RouteMapDataController extends AbstractController
{
    #[Route('/api/map/route/{publicId}', name: 'api_map_route_data', methods: ['GET'])]
    public function __invoke(
        string $publicId,
        RouteRepository $routeRepository,
        RouteViewService $routeViewService,
    ): JsonResponse {
        $route = $routeRepository->findOneByPublicId($publicId);

        if ($route === null) {
            throw new NotFoundHttpException('Route not found');
        }

        /** @var User $user */
        $user = $this->getUser();
        $role = $user->getRoles()[0] ?? 'ROLE_USER';

        $options = new MapViewOptions(
            showOptimizationMetrics: $role === 'ROLE_ADMIN',
            showTimingBreakdown: $role === 'ROLE_ADMIN',
            showVehicleTracking: true,
            showPolylines: true,
            vehiclePublicId: $route->getVehicle()?->getPublicIdString(),
        );

        $mapViewData = $routeViewService->buildSingleRouteView($route, $role, $options);

        return $this->json($mapViewData->toArray());
    }
}
