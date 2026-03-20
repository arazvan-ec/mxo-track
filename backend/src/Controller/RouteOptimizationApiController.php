<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Route\BuildRoutesInput;
use App\Application\Route\RouteNotFoundException;
use App\Application\Route\RoutePlanningService;
use App\Domain\Route\Model\Route as RouteEntity;
use App\Enum\RouteStatus;
use App\Http\ApiErrorResponder;
use App\Repository\RouteRepository;
use App\Service\DeliveryNoteGenerator;
use App\Service\RouteCapacityValidator;
use App\Service\RouteOptimizationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/api')]
class RouteOptimizationApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteOptimizationService $optimizer,
        private readonly RouteCapacityValidator $capacityValidator,
        private readonly RoutePlanningService $routePlanningService,
        private readonly DeliveryNoteGenerator $deliveryNoteGenerator,
        private readonly ApiErrorResponder $errorResponder,
        private readonly RealtimePublisherInterface $publisher,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Optimize stop order for a route using VROOM + OSRM.
     */
    #[Route('/routes/{publicId}/optimize', name: 'api_route_optimize', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function optimizeRoute(string $publicId): JsonResponse
    {
        try {
            $result = $this->routePlanningService->optimizeRoute($publicId, apply: true);
        } catch (RouteNotFoundException) {
            return $this->errorResponder->notFound('Ruta no encontrada.');
        }

        return new JsonResponse($result->toArray());
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

        if (\count($shipmentIds) === 0 || \count($vehicleIds) === 0) {
            return $this->errorResponder->badRequest('Se requieren shipment_ids y vehicle_ids.');
        }

        try {
            $result = $this->routePlanningService->buildRoutes(new BuildRoutesInput(
                shipmentPublicIds: $shipmentIds,
                vehiclePublicIds: $vehicleIds,
                originPublicId: $data['origin_id'] ?? null,
                maxStopsPerRoute: (int) ($data['max_stops_per_route'] ?? 30),
            ));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponder->badRequest($e->getMessage());
        }

        return new JsonResponse($result->toArray());
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

    /**
     * Re-optimize pending stops on an active route using the driver's current position.
     */
    #[Route('/routes/{publicId}/reoptimize', name: 'api_route_reoptimize', methods: ['POST'])]
    #[IsGranted('ROLE_OPERATOR')]
    public function reoptimizeRoute(string $publicId, Request $request): JsonResponse
    {
        $route = $this->findRouteByPublicId($publicId);
        if ($route === null) {
            return $this->errorResponder->notFound('ROUTE_NOT_FOUND', 'Ruta no encontrada.');
        }

        if ($route->getStatus() !== RouteStatus::ACTIVE) {
            return $this->errorResponder->badRequest('ROUTE_NOT_ACTIVE', 'Solo se pueden reoptimizar rutas activas.');
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = [];
        }

        $currentLat = isset($data['currentLat']) ? (float) $data['currentLat'] : null;
        $currentLng = isset($data['currentLng']) ? (float) $data['currentLng'] : null;

        if ($currentLat !== null && $currentLng !== null) {
            if ($currentLat < -90.0 || $currentLat > 90.0 || $currentLng < -180.0 || $currentLng > 180.0) {
                return $this->errorResponder->badRequest('INVALID_COORDINATES', 'currentLat must be in [-90, 90] and currentLng must be in [-180, 180].');
            }
        }

        $result = $this->optimizer->reoptimizePendingStops($route, $currentLat, $currentLng);
        $this->optimizer->applyOptimizedOrder($result['optimized']);

        $distanceBefore = $result['distanceBefore'];
        $distanceAfter = $result['distanceAfter'];
        $improvement = $distanceBefore > 0 ? (1 - $distanceAfter / $distanceBefore) * 100 : 0;

        $this->eventDispatcher->dispatch(new \App\Domain\Event\RouteReoptimized(
            routePublicId: $publicId,
            improvementPercent: $improvement,
            distanceKm: $distanceAfter,
            durationMinutes: $result['durationMinutes'],
            pendingStopsCount: \count($result['optimized']),
        ));

        // Publish realtime update
        try {
            $this->publisher->publish(new SseMessage(
                data: [
                    'type' => 'route_reoptimized',
                    'route_public_id' => $publicId,
                    'distance_before' => $result['distanceBefore'],
                    'distance_after' => $result['distanceAfter'],
                    'duration_minutes' => $result['durationMinutes'],
                    'stops_reordered' => \count($result['optimized']),
                ],
                topics: [sprintf('/routes/%s/updates', $publicId)],
            ));
        } catch (\Throwable) {
            // Don't break the flow on publish failure
        }

        return new JsonResponse([
            'status' => 'reoptimized',
            'distance_before_km' => round($result['distanceBefore'], 2),
            'distance_after_km' => round($result['distanceAfter'], 2),
            'duration_minutes' => $result['durationMinutes'],
            'stops_reordered' => \count($result['optimized']),
        ]);
    }

    private function findRouteByPublicId(string $publicId): ?RouteEntity
    {
        return $this->em->getRepository(RouteEntity::class)
            ->findOneBy(['publicId' => $publicId]);
    }
}
