<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Route as RouteEntity;
use App\Entity\RouteStop;
use App\Http\ApiErrorResponder;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Routes')]
#[Route('/api/v1/routes', name: 'api_v1_routes_')]
class RouteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteRepository $routeRepository,
        private readonly ApiErrorResponder $errorResponder,
    ) {
    }

    #[OA\Get(summary: 'List routes', description: 'Returns a paginated list of delivery routes. Optionally filter by status.')]
    #[OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number (default: 1)', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page (1-100, default: 20)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Parameter(name: 'status', in: 'query', required: false, description: 'Filter by route status', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of routes',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(properties: [
                        new OA\Property(property: 'public_id', type: 'string'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(property: 'driver', type: 'string', nullable: true),
                        new OA\Property(property: 'vehicle', type: 'string', nullable: true),
                        new OA\Property(property: 'start_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'end_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'total_distance_km', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'estimated_duration_minutes', type: 'integer', nullable: true),
                    ]),
                ),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'page', type: 'integer'),
                new OA\Property(property: 'limit', type: 'integer'),
            ],
        ),
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $offset = ($page - 1) * $limit;

        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(RouteEntity::class, 'r')
            ->orderBy('r.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $status = $request->query->get('status');
        if ($status !== null && $status !== '') {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        $routes = $qb->getQuery()->getResult();

        $total = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(RouteEntity::class, 'r')
            ->getQuery()
            ->getSingleScalarResult();

        $items = array_map(static fn (RouteEntity $r): array => [
            'public_id' => $r->getPublicIdString(),
            'name' => $r->getName(),
            'status' => $r->getStatus()->value,
            'driver' => $r->getDriver()?->getName(),
            'vehicle' => $r->getVehicle()?->getName(),
            'start_at' => $r->getStartAt()?->format(\DATE_ATOM),
            'end_at' => $r->getEndAt()?->format(\DATE_ATOM),
            'total_distance_km' => $r->getTotalDistanceKm(),
            'estimated_duration_minutes' => $r->getEstimatedDurationMinutes(),
        ], $routes);

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int) ceil($total / $limit),
        ]);
    }

    #[OA\Get(summary: 'Get route detail', description: 'Returns full details of a route including all stops.')]
    #[OA\Parameter(name: 'publicId', in: 'path', required: true, description: 'Route ULID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Route details with stops',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'public_id', type: 'string'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'driver', type: 'string', nullable: true),
                new OA\Property(property: 'vehicle', type: 'string', nullable: true),
                new OA\Property(property: 'start_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'end_at', type: 'string', format: 'date-time', nullable: true),
                new OA\Property(property: 'total_weight_kg', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'total_volume_m3', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'total_parcels', type: 'integer', nullable: true),
                new OA\Property(property: 'total_distance_km', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'estimated_duration_minutes', type: 'integer', nullable: true),
                new OA\Property(
                    property: 'stops',
                    type: 'array',
                    items: new OA\Items(properties: [
                        new OA\Property(property: 'public_id', type: 'string'),
                        new OA\Property(property: 'sequence', type: 'integer'),
                        new OA\Property(property: 'address', type: 'string', nullable: true),
                        new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'recipient_name', type: 'string', nullable: true),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(property: 'delivered_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'is_origin', type: 'boolean'),
                        new OA\Property(property: 'shipment_public_id', type: 'string', nullable: true),
                    ]),
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'Route not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'route_not_found'),
                new OA\Property(property: 'message', type: 'string', example: 'Route not found.'),
            ],
        ),
    )]
    #[Route('/{publicId}', name: 'detail', methods: ['GET'])]
    public function detail(string $publicId): JsonResponse
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);
        if (!$route instanceof RouteEntity) {
            return $this->errorResponder->notFound('route_not_found', 'Route not found.');
        }

        $stops = $this->em->getRepository(RouteStop::class)->findBy(
            ['route' => $route],
            ['sequence' => 'ASC'],
        );

        $stopData = array_map(static fn (RouteStop $s): array => [
            'public_id' => $s->getPublicIdString(),
            'sequence' => $s->getSequence(),
            'address' => $s->getAddress(),
            'latitude' => $s->getLatitude(),
            'longitude' => $s->getLongitude(),
            'recipient_name' => $s->getRecipientName(),
            'status' => $s->getStatus()->value,
            'delivered_at' => $s->getDeliveredAt()?->format(\DATE_ATOM),
            'is_origin' => $s->isOrigin(),
            'shipment_public_id' => $s->getShipment()?->getPublicIdString(),
        ], $stops);

        return new JsonResponse([
            'public_id' => $route->getPublicIdString(),
            'name' => $route->getName(),
            'status' => $route->getStatus()->value,
            'driver' => $route->getDriver()?->getName(),
            'vehicle' => $route->getVehicle()?->getName(),
            'start_at' => $route->getStartAt()?->format(\DATE_ATOM),
            'end_at' => $route->getEndAt()?->format(\DATE_ATOM),
            'total_weight_kg' => $route->getTotalWeightKg(),
            'total_volume_m3' => $route->getTotalVolumeM3(),
            'total_parcels' => $route->getTotalParcels(),
            'total_distance_km' => $route->getTotalDistanceKm(),
            'estimated_duration_minutes' => $route->getEstimatedDurationMinutes(),
            'stops' => $stopData,
        ]);
    }
}
