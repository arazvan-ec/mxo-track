<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Fleet\FleetOverviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class FleetMapController extends AbstractController
{
    #[SymfonyRoute('/fleet/map', name: 'fleet_map', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->redirect('/app/admin/fleet-map');
    }

    #[SymfonyRoute('/api/fleet/summary', name: 'api_fleet_summary', methods: ['GET'])]
    public function summary(FleetOverviewService $fleetOverview): JsonResponse
    {
        return $this->json($fleetOverview->getFleetSummary()->toArray());
    }
}
