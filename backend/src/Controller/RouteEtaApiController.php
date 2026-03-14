<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Route as RouteEntity;
use App\Entity\User;
use App\Http\ApiErrorResponder;
use App\Repository\RouteRepository;
use App\Repository\RouteSnapshotRepository;
use App\Service\EtaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_DRIVER')]
class RouteEtaApiController extends AbstractController
{
    private const int SNAPSHOT_FRESHNESS_SECONDS = 60;

    #[Route('/api/routes/{publicId}/etas', name: 'api_routes_etas', methods: ['GET'])]
    public function etas(
        string $publicId,
        RouteRepository $routeRepository,
        RouteSnapshotRepository $snapshotRepository,
        EtaService $etaService,
        ApiErrorResponder $errorResponder,
    ): JsonResponse {
        $route = $routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        // Verify the driver owns this route
        /** @var User $user */
        $user = $this->getUser();
        if ($route->getDriver()?->getId() !== $user->getId()) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        // Try snapshot first (fast path — no OSRM call)
        $items = $this->trySnapshotEtas($route, $snapshotRepository);

        // Fall back to live calculation if snapshot is stale or missing
        if ($items === null) {
            $etas = $etaService->calculateEtas($route);
            $items = [];
            foreach ($etas as $stopPublicId => $data) {
                $items[$stopPublicId] = [
                    'eta' => $data['eta']->format(\DATE_ATOM),
                    'eta_formatted' => $data['eta']->format('H:i'),
                    'remaining_minutes' => $data['remainingMinutes'],
                    'distance_km' => $data['distanceKm'],
                ];
            }
        }

        return $this->json(['items' => $items]);
    }

    /**
     * @return array<string, array{eta: string, eta_formatted: string, remaining_minutes: int, distance_km: float}>|null
     */
    private function trySnapshotEtas(RouteEntity $route, RouteSnapshotRepository $snapshotRepository): ?array
    {
        $snapshot = $snapshotRepository->findByRoute($route);
        if ($snapshot === null || $snapshot->getEtas() === null) {
            return null;
        }

        // Check freshness
        $age = time() - $snapshot->getUpdatedAt()->getTimestamp();
        if ($age > self::SNAPSHOT_FRESHNESS_SECONDS) {
            return null;
        }

        $items = [];
        foreach ($snapshot->getEtas() as $stopPublicId => $data) {
            $items[$stopPublicId] = [
                'eta' => $data['eta'],
                'eta_formatted' => '',
                'remaining_minutes' => $data['minutes'],
                'distance_km' => $data['distance_km'],
            ];

            // Extract formatted time from ISO date
            try {
                $items[$stopPublicId]['eta_formatted'] = (new \DateTimeImmutable($data['eta']))->format('H:i');
            } catch (\Exception) {
                // keep empty string
            }
        }

        return $items;
    }
}
