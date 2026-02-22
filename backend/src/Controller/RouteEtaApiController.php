<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Route as RouteEntity;
use App\Entity\User;
use App\Http\ApiErrorResponder;
use App\Repository\RouteRepository;
use App\Service\EtaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_DRIVER')]
class RouteEtaApiController extends AbstractController
{
    #[Route('/api/routes/{publicId}/etas', name: 'api_routes_etas', methods: ['GET'])]
    public function etas(
        string $publicId,
        RouteRepository $routeRepository,
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

        return $this->json(['items' => $items]);
    }
}
