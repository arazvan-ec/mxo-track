<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminMetricsService;
use App\Service\SystemHealthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->redirect('/app/admin/dashboard', Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/health', name: 'admin_health', methods: ['GET'])]
    public function health(AdminMetricsService $metricsService, SystemHealthService $systemHealthService): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'health' => $systemHealthService->check(),
            'live' => $systemHealthService->checkLive(),
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
