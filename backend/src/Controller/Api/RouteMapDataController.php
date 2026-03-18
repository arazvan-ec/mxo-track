<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Route;
use App\Entity\User;
use App\Entity\VehicleLastPosition;
use App\Repository\RouteRepository;
use App\View\MapViewOptions;
use App\View\RouteViewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class RouteMapDataController extends AbstractController
{
    public function __construct(
        private readonly RouteRepository $routeRepository,
        private readonly RouteViewService $routeViewService,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] private readonly string $mercurePublicUrl,
    ) {}

    #[SymfonyRoute('/api/map/route/{publicId}', name: 'api_map_route', methods: ['GET'])]
    public function __invoke(string $publicId): JsonResponse
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            return $this->json(['error' => 'Route not found'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        $role = $this->resolveRole($user);

        // Verify access for non-admin users
        if ($role === 'ROLE_CUSTOMER') {
            $customer = $user->getCustomer();
            if ($customer === null || $route->getCustomer()?->getId() !== $customer->getId()) {
                return $this->json(['error' => 'Access denied'], 403);
            }
        } elseif ($role === 'ROLE_DRIVER') {
            if ($route->getDriver()?->getId() !== $user->getId()) {
                return $this->json(['error' => 'Access denied'], 403);
            }
        }

        // Build vehicle tracking data
        $vehiclePublicId = null;
        $vehiclePosition = null;
        $vehicle = $route->getVehicle();

        if ($vehicle !== null) {
            $vehiclePublicId = $vehicle->getPublicIdString();
            $lastPosition = $this->em->getRepository(VehicleLastPosition::class)->findOneBy([
                'vehicle' => $vehicle,
            ]);

            if ($lastPosition instanceof VehicleLastPosition) {
                $vehiclePosition = [
                    'lat' => $lastPosition->getLat(),
                    'lng' => $lastPosition->getLng(),
                    'speed' => $lastPosition->getSpeed(),
                    'course' => $lastPosition->getCourse(),
                ];
            }
        }

        $isAdmin = $role === 'ROLE_ADMIN';
        $mapOptions = new MapViewOptions(
            showOptimizationMetrics: $isAdmin,
            showTimingBreakdown: $isAdmin,
            showVehicleTracking: $vehicle !== null,
            showStopStatus: true,
            showOriginalOrder: $isAdmin,
            showPolylines: true,
            comparisonMode: $isAdmin ? 'planned_vs_actual' : null,
            vehiclePublicId: $vehiclePublicId,
            vehiclePosition: $vehiclePosition,
        );

        $mapView = $this->routeViewService->buildSingleRouteView($route, $role, $mapOptions);
        $mapView = $mapView->withMercureUrl($this->mercurePublicUrl);

        return $this->json($mapView->toArray());
    }

    private function resolveRole(User $user): string
    {
        if ($user->hasRole('ROLE_ADMIN')) {
            return 'ROLE_ADMIN';
        }

        if ($user->hasRole('ROLE_CUSTOMER')) {
            return 'ROLE_CUSTOMER';
        }

        if ($user->hasRole('ROLE_DRIVER')) {
            return 'ROLE_DRIVER';
        }

        return 'ROLE_USER';
    }
}
