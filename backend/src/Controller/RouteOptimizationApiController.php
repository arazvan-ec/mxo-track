<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Route\BuildRoutesInput;
use App\Application\Route\RouteNotFoundException;
use App\Application\Route\RoutePlanningService;
use App\Entity\Route as RouteEntity;
use App\Http\ApiErrorResponder;
use App\Repository\RouteRepository;
use App\Service\DeliveryNoteGenerator;
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
        private readonly RoutePlanningService $routePlanningService,
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

    private function findRouteByPublicId(string $publicId): ?RouteEntity
    {
        return $this->em->getRepository(RouteEntity::class)
            ->findOneBy(['publicId' => $publicId]);
    }
}
