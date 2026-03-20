<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Route\Model\Route as RouteEntity;
use App\Http\ApiErrorResponder;
use App\Repository\RouteRepository;
use App\Service\RouteAnalysisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OPERATOR')]
class RouteAnalysisController extends AbstractController
{
    #[Route('/api/routes/{publicId}/analysis', name: 'api_routes_analysis', methods: ['GET'])]
    public function analysis(
        string $publicId,
        RouteRepository $routeRepository,
        RouteAnalysisService $analysisService,
        ApiErrorResponder $errorResponder,
    ): JsonResponse {
        $route = $routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        if ($route->getStatus() !== \App\Enum\RouteStatus::DONE) {
            return new JsonResponse([
                'error' => [
                    'code' => 'route_not_done',
                    'message' => 'Route analysis is only available for completed routes.',
                ],
            ], 422);
        }

        $result = $analysisService->analyzeRouteExecution($route);

        return $this->json($result);
    }
}
