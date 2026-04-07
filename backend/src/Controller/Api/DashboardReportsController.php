<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\ReportingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DashboardReportsController extends AbstractController
{
    #[Route('/api/admin/dashboard-reports', name: 'api_admin_dashboard_reports', methods: ['GET'])]
    public function __invoke(ReportingService $reportingService): JsonResponse
    {
        return $this->json([
            'daily_deliveries' => $reportingService->getDailyDeliveries(7),
            'top_drivers' => $reportingService->getTopDrivers(5, 7),
        ]);
    }
}
