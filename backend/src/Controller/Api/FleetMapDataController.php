<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Application\Fleet\FleetOverviewService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class FleetMapDataController extends AbstractController
{
    #[Route('/api/fleet/map-data', name: 'api_fleet_map_data', methods: ['GET'])]
    public function __invoke(FleetOverviewService $fleetOverview): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = $fleetOverview->getFleetMapData($user);

        return $this->json([
            'vehicles' => $data->vehicles,
            'routes' => $data->routes,
        ]);
    }
}
