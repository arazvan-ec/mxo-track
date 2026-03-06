<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route as RouteEntity;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Http\ApiErrorResponder;
use App\Repository\RouteRepository;
use App\Service\DeliveryNoteGenerator;
use App\Service\RouteBuilder;
use App\Service\RouteCapacityValidator;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class RouteOptimizationApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteOptimizationService $optimizer,
        private readonly RouteCapacityValidator $capacityValidator,
        private readonly RouteBuilder $routeBuilder,
        private readonly DeliveryNoteGenerator $deliveryNoteGenerator,
        private readonly ApiErrorResponder $errorResponder,
    ) {}

    /**
     * Optimize stop order for a route using VROOM + OSRM.
     */
    #[Route('/routes/{publicId}/optimize', name: 'api_route_optimize', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function optimizeRoute(string $publicId): JsonResponse
    {
        $route = $this->findRouteByPublicId($publicId);
        if ($route === null) {
            return $this->errorResponder->notFound('Ruta no encontrada.');
        }

        $result = $this->optimizer->optimizeStopOrder($route);
        $this->optimizer->applyOptimizedOrder($result['optimized']);

        // Update route with VROOM's real road distance
        $route->setTotalDistanceKm($result['distanceAfter']);

        // Estimate duration from VROOM distance + service time
        $deliveryStops = array_filter(
            $result['optimized'],
            static fn (array $item): bool => !$item['stop']->isOrigin(),
        );
        $durationMinutes = (int) round(($result['distanceAfter'] / 40.0) * 60)
            + \count($deliveryStops) * 5;
        $route->setEstimatedDurationMinutes($durationMinutes);

        $this->em->flush();

        return new JsonResponse([
            'distanceBefore' => round($result['distanceBefore'], 2),
            'distanceAfter' => round($result['distanceAfter'], 2),
            'improvement' => $result['distanceBefore'] > 0
                ? round((1 - $result['distanceAfter'] / $result['distanceBefore']) * 100, 1)
                : 0,
            'estimatedDurationMinutes' => $durationMinutes,
            'stops' => array_map(static fn (array $item): array => [
                'publicId' => $item['stop']->getPublicIdString(),
                'sequence' => $item['newSequence'],
                'address' => $item['stop']->getAddress(),
            ], $result['optimized']),
        ]);
    }

    /**
     * Validate route capacity before starting.
     */
    #[Route('/routes/{publicId}/validate-capacity', name: 'api_route_validate_capacity', methods: ['GET'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function validateCapacity(string $publicId): JsonResponse
    {
        $route = $this->findRouteByPublicId($publicId);
        if ($route === null) {
            return $this->errorResponder->notFound('Ruta no encontrada.');
        }

        $validation = $this->capacityValidator->validate($route);
        $this->em->flush();

        return new JsonResponse($validation);
    }

    /**
     * Generate delivery note (albarán) for a route.
     */
    #[Route('/routes/{publicId}/delivery-note', name: 'api_route_delivery_note', methods: ['GET'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function deliveryNote(string $publicId): JsonResponse
    {
        $route = $this->findRouteByPublicId($publicId);
        if ($route === null) {
            return $this->errorResponder->notFound('Ruta no encontrada.');
        }

        $note = $this->deliveryNoteGenerator->generateForRoute($route);

        return new JsonResponse($note);
    }

    /**
     * Build routes from shipments: given a list of shipment IDs and vehicle IDs,
     * distribute shipments across vehicles respecting capacity constraints.
     */
    #[Route('/routes/build', name: 'api_routes_build', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function buildRoutes(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->errorResponder->badRequest('JSON inválido.');
        }

        $shipmentIds = $data['shipment_ids'] ?? [];
        $vehicleIds = $data['vehicle_ids'] ?? [];
        $originId = $data['origin_id'] ?? null;
        $maxStopsPerRoute = $data['max_stops_per_route'] ?? 30;

        if (\count($shipmentIds) === 0 || \count($vehicleIds) === 0) {
            return $this->errorResponder->badRequest('Se requieren shipment_ids y vehicle_ids.');
        }

        // Load entities
        $shipments = $this->em->getRepository(Shipment::class)
            ->createQueryBuilder('s')
            ->where('s.publicId IN (:ids)')
            ->setParameter('ids', $shipmentIds)
            ->getQuery()
            ->getResult();

        $vehicles = $this->em->getRepository(Vehicle::class)
            ->createQueryBuilder('v')
            ->where('v.publicId IN (:ids)')
            ->setParameter('ids', $vehicleIds)
            ->getQuery()
            ->getResult();

        if (\count($shipments) === 0) {
            return $this->errorResponder->badRequest('No se encontraron envíos válidos.');
        }

        // Find customer from first shipment
        $customer = $shipments[0]->getCustomer();

        $origin = null;
        if ($originId !== null) {
            $origin = $this->em->getRepository(CustomerLocation::class)
                ->findOneBy(['publicId' => $originId]);
        }

        $results = $this->routeBuilder->buildRoutes(
            $shipments,
            $vehicles,
            $customer,
            $origin,
            (int) $maxStopsPerRoute,
        );

        $this->em->flush();

        $response = [];
        foreach ($results as $result) {
            $route = $result['route'];
            $response[] = [
                'route' => [
                    'publicId' => $route->getPublicIdString(),
                    'name' => $route->getName(),
                    'vehicle' => $route->getVehicle()?->getName(),
                    'totalDistanceKm' => $route->getTotalDistanceKm(),
                    'estimatedDurationMinutes' => $route->getEstimatedDurationMinutes(),
                ],
                'stopsCount' => \count($result['stops']),
                'validation' => $result['validation'],
            ];
        }

        return new JsonResponse([
            'routesCreated' => \count($results),
            'routes' => $response,
        ]);
    }

    /**
     * Get route timing estimation.
     */
    #[Route('/routes/{publicId}/timing', name: 'api_route_timing', methods: ['GET'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function routeTiming(string $publicId, Request $request): JsonResponse
    {
        $route = $this->findRouteByPublicId($publicId);
        if ($route === null) {
            return $this->errorResponder->notFound('Ruta no encontrada.');
        }

        $avgSpeed = (float) $request->query->get('avg_speed_kmh', '40');
        $deliveryTime = (float) $request->query->get('delivery_minutes', '5');

        $timing = $this->optimizer->estimateRouteTiming($route, $avgSpeed, $deliveryTime);

        return new JsonResponse($timing);
    }

    private function findRouteByPublicId(string $publicId): ?RouteEntity
    {
        return $this->em->getRepository(RouteEntity::class)
            ->findOneBy(['publicId' => $publicId]);
    }
}
