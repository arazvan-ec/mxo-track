<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Fleet\FleetOverviewService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class FleetMapController extends AbstractController
{
    #[SymfonyRoute('/fleet/map', name: 'fleet_map', methods: ['GET'])]
    public function __invoke(
        FleetOverviewService $fleetOverview,
        #[Autowire('%env(MERCURE_PUBLIC_URL)%')] string $mercurePublicUrl,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $mapData = $fleetOverview->getFleetMapData($user);

        return $this->render('tracking/map.html.twig', [
            'mercure_public_url' => $mercurePublicUrl,
            'vehicles_json' => json_encode($mapData->vehicles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
            'routes_json' => json_encode($mapData->routes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_THROW_ON_ERROR),
        ]);
    }

    #[SymfonyRoute('/api/fleet/summary', name: 'api_fleet_summary', methods: ['GET'])]
    public function summary(FleetOverviewService $fleetOverview): JsonResponse
    {
        return $this->json($fleetOverview->getFleetSummary()->toArray());
    }
}
