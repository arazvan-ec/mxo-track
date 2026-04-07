<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\AdminMetricsService;
use App\Service\ReportingService;
use App\Service\SystemHealthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    #[Route('/api/admin/dashboard', name: 'api_admin_dashboard', methods: ['GET'])]
    public function __invoke(
        AdminMetricsService $metricsService,
        SystemHealthService $systemHealthService,
        ReportingService $reportingService,
    ): JsonResponse {
        return $this->json([
            'health' => $systemHealthService->check(),
            'live' => $systemHealthService->checkLive(),
            'metrics' => $metricsService->collect(),
            'daily_deliveries' => $reportingService->getDailyDeliveries(7),
            'top_drivers' => $reportingService->getTopDrivers(5, 7),
            'generated_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }
}
