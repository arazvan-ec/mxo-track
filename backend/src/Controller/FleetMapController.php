<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Service\VisibilityScopeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class FleetMapController extends AbstractController
{
    #[SymfonyRoute('/fleet/map', name: 'fleet_map', methods: ['GET'])]
    public function __invoke(
        EntityManagerInterface $em,
        VisibilityScopeService $visibilityScope,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] string $mercurePublicUrl,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Load active routes first — only vehicles with active routes are shown on the map
        $activeRoutes = $em->createQueryBuilder()
            ->select('r', 'v', 'd')
            ->from(Route::class, 'r')
            ->leftJoin('r.vehicle', 'v')
            ->leftJoin('r.driver', 'd')
            ->where('r.status IN (:statuses)')
            ->setParameter('statuses', [RouteStatus::ACTIVE, RouteStatus::PLANNED])
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        // Collect vehicles assigned to active routes
        $routeVehicles = [];
        foreach ($activeRoutes as $route) {
            $vehicle = $route->getVehicle();
            if ($vehicle !== null && $vehicle->isActive()) {
                $routeVehicles[$vehicle->getId()] = $vehicle;
            }
        }

        // Apply visibility scope for non-admin users
        if (!$user->hasRole('ROLE_ADMIN')) {
            $allowedIds = array_flip($visibilityScope->vehicleIdsFor($user));
            $routeVehicles = array_filter($routeVehicles, static fn (Vehicle $v) => isset($allowedIds[$v->getId()]));
        }

        // Build vehicle data with last positions
        $vehiclesData = [];
        foreach ($routeVehicles as $vehicle) {
            $lastPos = $em->getRepository(VehicleLastPosition::class)->findOneBy(['vehicle' => $vehicle]);
            $vehiclesData[] = [
                'public_id' => $vehicle->getPublicIdString(),
                'name' => $vehicle->getName(),
                'last_position' => $lastPos !== null ? [
                    'lat' => $lastPos->getLat(),
                    'lng' => $lastPos->getLng(),
                    'speed' => $lastPos->getSpeed(),
                    'course' => $lastPos->getCourse(),
                    'device_time' => $lastPos->getDeviceTime()->format('H:i'),
                ] : null,
            ];
        }

        $routesData = [];
        foreach ($activeRoutes as $route) {
            $stops = $em->createQueryBuilder()
                ->select('rs')
                ->from(RouteStop::class, 'rs')
                ->where('rs.route = :route')
                ->setParameter('route', $route)
                ->orderBy('rs.sequence', 'ASC')
                ->getQuery()
                ->getResult();

            $stopsData = array_map(static fn (RouteStop $s) => [
                'lat' => $s->getLatitude(),
                'lng' => $s->getLongitude(),
                'address' => $s->getAddress(),
                'sequence' => $s->getSequence(),
                'status' => $s->getStatus()->value,
                'recipient' => $s->getRecipientName(),
            ], $stops);

            // Count stats
            $total = count($stops);
            $delivered = count(array_filter($stops, static fn (RouteStop $s) => $s->getStatus() === RouteStopStatus::DELIVERED));

            $routesData[] = [
                'public_id' => $route->getPublicIdString(),
                'name' => $route->getName(),
                'status' => $route->getStatus()->value,
                'vehicle_public_id' => $route->getVehicle()?->getPublicIdString(),
                'vehicle_name' => $route->getVehicle()?->getName(),
                'driver_email' => $route->getDriver()?->getEmail(),
                'stops' => $stopsData,
                'total_stops' => $total,
                'delivered_stops' => $delivered,
            ];
        }

        return $this->render('tracking/map.html.twig', [
            'mercure_public_url' => $mercurePublicUrl,
            'vehicles_json' => json_encode($vehiclesData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
            'routes_json' => json_encode($routesData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
        ]);
    }

    #[SymfonyRoute('/api/fleet/summary', name: 'api_fleet_summary', methods: ['GET'])]
    public function summary(EntityManagerInterface $em): JsonResponse
    {
        $activeRoutes = $em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r')
            ->where('r.status = :status')
            ->setParameter('status', RouteStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        $totalVehicles = $em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(Vehicle::class, 'v')
            ->where('v.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();

        $pendingStops = $em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('r.status = :status')
            ->andWhere('rs.status = :pending')
            ->setParameter('status', RouteStatus::ACTIVE)
            ->setParameter('pending', RouteStopStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'active_routes' => (int) $activeRoutes,
            'total_vehicles' => (int) $totalVehicles,
            'pending_stops' => (int) $pendingStops,
        ]);
    }
}
