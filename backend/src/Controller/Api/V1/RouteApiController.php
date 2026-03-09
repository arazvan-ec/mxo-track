<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Route as RouteEntity;
use App\Entity\RouteStop;
use App\Http\ApiErrorResponder;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/routes', name: 'api_v1_routes_')]
class RouteApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteRepository $routeRepository,
        private readonly ApiErrorResponder $errorResponder,
    ) {
    }

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
        ]);
    }

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
