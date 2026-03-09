<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ZonePerformanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/zone-trends')]
#[IsGranted('ROLE_OPERATOR')]
class ZonePerformanceController extends AbstractController
{
    public function __construct(
        private readonly ZonePerformanceService $zonePerformanceService,
    ) {}

    #[Route('', name: 'admin_zone_trends', methods: ['GET'])]
    public function index(): Response
    {
        $summary = $this->zonePerformanceService->getZoneSummary();

        return $this->render('admin/reports/zone_trends.html.twig', [
            'summary' => $summary,
        ]);
    }

    #[Route('/data', name: 'admin_zone_trends_data', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        $from = null;
        $to = null;

        $fromStr = $request->query->getString('from', '');
        $toStr = $request->query->getString('to', '');

        if ($fromStr !== '') {
            try {
                $from = new \DateTimeImmutable($fromStr);
                $from = $from->setTime(0, 0, 0);
            } catch (\Exception) {
                $from = null;
            }
        }

        if ($toStr !== '') {
            try {
                $to = new \DateTimeImmutable($toStr);
                $to = $to->setTime(23, 59, 59);
            } catch (\Exception) {
                $to = null;
            }
        }

        $trends = $this->zonePerformanceService->getWeeklyTrends($from, $to);
        $summary = $this->zonePerformanceService->getZoneSummary();

        return $this->json([
            'trends' => $trends,
            'summary' => $summary,
        ]);
    }
}
