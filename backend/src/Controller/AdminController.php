<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminMetricsService;
use App\Service\SystemHealthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(
        AdminMetricsService $metricsService,
        SystemHealthService $systemHealthService,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] string $mercurePublicUrl,
    ): Response {
        $metrics = $metricsService->collect();
        $health = $systemHealthService->check();

        return $this->render('admin/dashboard.html.twig', [
            'kpis' => [
                'shipments_by_status' => [],
                'vehicles_without_signal' => 0,
                'active_routes' => $metrics['active_routes'],
                'pending_stops' => $metrics['pending_stops'],
                'pods_today' => 0,
                'import_runs_today' => $metrics['import_runs_today'],
                'positions_ingested_last_hour' => $metrics['positions_ingested_last_hour'],
            ],
            'health' => $health,
            'mercure_public_url' => $mercurePublicUrl,
        ]);
    }

    #[Route('/health', name: 'admin_health', methods: ['GET'])]
    public function health(AdminMetricsService $metricsService, SystemHealthService $systemHealthService): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'health' => $systemHealthService->check(),
            'metrics' => $metricsService->collect(),
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    #[Route('/health/live', name: 'admin_health_live', methods: ['GET'])]
    public function healthLive(SystemHealthService $systemHealthService): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'live' => $systemHealthService->checkLive(),
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
