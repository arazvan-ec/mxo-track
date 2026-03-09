<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Route\BuildRoutesInput;
use App\Application\Route\RoutePlanningService;
use App\Entity\Shipment;
use App\Service\ShipmentClusteringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/route-planner')]
#[IsGranted('ROLE_ADMIN')]
class RoutePlannerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShipmentClusteringService $clusteringService,
        private readonly RoutePlanningService $routePlanningService,
    ) {}

    #[Route('', name: 'admin_route_planner_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/route_planner/index.html.twig');
    }

    /**
     * Cluster shipments by geographic zone using k-means.
     *
     * Input JSON: {shipment_ids: [...], num_clusters: N}
     * Returns JSON with clusters and colours.
     */
    #[Route('/cluster', name: 'admin_route_planner_cluster', methods: ['POST'])]
    public function cluster(Request $request): JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalido.'], Response::HTTP_BAD_REQUEST);
        }

        $shipmentIds = $payload['shipment_ids'] ?? [];
        $numClusters = (int) ($payload['num_clusters'] ?? 3);

        if (!\is_array($shipmentIds) || $shipmentIds === []) {
            return new JsonResponse(['error' => 'Se requiere al menos un shipment_id.'], Response::HTTP_BAD_REQUEST);
        }

        if ($numClusters < 1) {
            return new JsonResponse(['error' => 'num_clusters debe ser >= 1.'], Response::HTTP_BAD_REQUEST);
        }

        // Convert public IDs to ULIDs and fetch shipments
        $ulids = [];
        foreach ($shipmentIds as $sid) {
            try {
                $ulids[] = Ulid::fromString((string) $sid);
            } catch (\Throwable) {
                // skip invalid IDs
            }
        }

        if ($ulids === []) {
            return new JsonResponse(['error' => 'Ninguno de los shipment_ids es valido.'], Response::HTTP_BAD_REQUEST);
        }

        $shipments = $this->em->getRepository(Shipment::class)
            ->createQueryBuilder('s')
            ->where('s.publicId IN (:ids)')
            ->setParameter('ids', $ulids)
            ->getQuery()
            ->getResult();

        // Build input array for the clustering service
        $points = [];
        foreach ($shipments as $shipment) {
            if ($shipment->getLatitude() === null || $shipment->getLongitude() === null) {
                continue;
            }

            $points[] = [
                'id' => $shipment->getPublicIdString(),
                'lat' => $shipment->getLatitude(),
                'lng' => $shipment->getLongitude(),
            ];
        }

        if ($points === []) {
            return new JsonResponse(['error' => 'Ninguno de los envios tiene coordenadas.'], Response::HTTP_BAD_REQUEST);
        }

        $clusters = $this->clusteringService->cluster($points, $numClusters);

        return new JsonResponse([
            'ok' => true,
            'clusters' => $clusters,
            'totalShipments' => \count($points),
            'numClusters' => \count($clusters),
        ]);
    }

    /**
     * Preview route building with optional cluster hints.
     *
     * Input JSON: {shipment_ids: [...], vehicle_ids: [...], origin_id: "...", max_stops: N, cluster_hints: [...]}
     * The cluster_hints are passed as metadata to RouteBuilder via BuildRoutesInput.
     */
    #[Route('/preview', name: 'admin_route_planner_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalido.'], Response::HTTP_BAD_REQUEST);
        }

        $shipmentIds = $payload['shipment_ids'] ?? [];
        $vehicleIds = $payload['vehicle_ids'] ?? [];

        if (!\is_array($shipmentIds) || $shipmentIds === []) {
            return new JsonResponse(['error' => 'Se requiere al menos un shipment_id.'], Response::HTTP_BAD_REQUEST);
        }

        if (!\is_array($vehicleIds) || $vehicleIds === []) {
            return new JsonResponse(['error' => 'Se requiere al menos un vehicle_id.'], Response::HTTP_BAD_REQUEST);
        }

        $clusterHints = null;
        if (isset($payload['cluster_hints']) && \is_array($payload['cluster_hints'])) {
            $clusterHints = $payload['cluster_hints'];
        }

        $input = new BuildRoutesInput(
            shipmentPublicIds: array_map('strval', $shipmentIds),
            vehiclePublicIds: array_map('strval', $vehicleIds),
            originPublicId: isset($payload['origin_id']) ? (string) $payload['origin_id'] : null,
            maxStopsPerRoute: (int) ($payload['max_stops'] ?? 30),
            clusterHints: $clusterHints,
        );

        try {
            $result = $this->routePlanningService->buildRoutes($input);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'ok' => true,
            'result' => $result->toArray(),
            'clusterHintsApplied' => $clusterHints !== null,
        ]);
    }
}
