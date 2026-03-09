<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Route;
use App\Repository\RouteRepository;
use App\Service\RouteComparisonService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/routes')]
#[IsGranted('ROLE_ADMIN')]
class RouteAnalysisController extends AbstractController
{
    public function __construct(
        private readonly RouteRepository $routeRepository,
        private readonly RouteComparisonService $comparisonService,
    ) {
    }

    #[SymfonyRoute('/{publicId}/analysis', name: 'admin_routes_analysis', methods: ['GET'])]
    public function analysis(string $publicId): Response
    {
        $route = $this->routeRepository->findOneByPublicId($publicId);

        if (!$route instanceof Route) {
            throw $this->createNotFoundException('Ruta no encontrada.');
        }

        $comparison = $this->comparisonService->compare($route);

        return $this->render('admin/route/analysis.html.twig', [
            'route' => $route,
            'comparison' => $comparison,
            'comparisonJson' => json_encode($comparison, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            'aiAnalysis' => $route->getAiAnalysis(),
        ]);
    }
}
