<?php

declare(strict_types=1);

namespace App\Infrastructure\MapView\Controller;

use App\Application\Fleet\FleetOverviewService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class FleetMapDataController extends AbstractController
{
    #[Route('/api/fleet/map-data', name: 'api_fleet_map_data', methods: ['GET'])]
    public function __invoke(FleetOverviewService $fleetService): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $fleetData = $fleetService->getFleetMapData($user);

        return $this->json($fleetData);
    }
}
