<?php

declare(strict_types=1);

namespace App\Controller\Operator;

use App\Service\AdminMetricsService;
use App\Service\OperatorKpiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/operator')]
#[IsGranted('ROLE_ADMIN')]
final class OperatorDashboardController extends AbstractController
{
    public function __construct(
        private readonly AdminMetricsService $metricsService,
        private readonly OperatorKpiService $kpiService,
    ) {}

    #[Route('', name: 'operator_dashboard')]
    public function dashboard(): Response
    {
        $metrics = $this->metricsService->collect();

        return $this->render('operator/dashboard.html.twig', [
            'metrics' => $metrics,
        ]);
    }

    #[Route('/dashboard/live', name: 'operator_dashboard_live', methods: ['GET'])]
    public function live(): Response
    {
        return $this->redirect('/app/admin/operator-dashboard');
    }

    #[Route('/dashboard/kpis', name: 'operator_dashboard_kpis', methods: ['GET'])]
    public function kpis(): JsonResponse
    {
        return new JsonResponse($this->kpiService->collectKpis());
    }
}
