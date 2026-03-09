<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Route\BuildRoutesInput;
use App\Application\Route\RoutePlanningService;
use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Service\RouteBuilder;
use App\Service\RouteCapacityValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/route-planner')]
#[IsGranted('ROLE_ADMIN')]
final class RoutePlannerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RoutePlanningService $planningService,
        private readonly RouteBuilder $routeBuilder,
        private readonly RouteCapacityValidator $capacityValidator,
    ) {}

    #[Route('', name: 'admin_route_planner', methods: ['GET'])]
    public function index(): Response
    {
        $customers = $this->em->getRepository(Customer::class)->findBy([], ['name' => 'ASC']);
        $vehicles = $this->em->getRepository(Vehicle::class)->findBy(['isActive' => true], ['name' => 'ASC']);
        $locations = $this->em->getRepository(CustomerLocation::class)->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->render('admin/route_planner/index.html.twig', [
            'customers' => $customers,
            'vehicles_json' => json_encode($this->serializeVehicles($vehicles)),
            'locations_json' => json_encode($this->serializeLocations($locations)),
        ]);
    }

    #[Route('/shipments', name: 'admin_route_planner_shipments', methods: ['GET'])]
    public function shipments(Request $request): JsonResponse
    {
        $customerPublicId = $request->query->get('customer');

        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Shipment::class, 's')
            ->leftJoin(RouteStop::class, 'rs', 'WITH', 'rs.shipment = s')
            ->where('rs.id IS NULL')
            ->orderBy('s.createdAt', 'DESC');

        if ($customerPublicId) {
            $customer = $this->em->getRepository(Customer::class)
                ->findOneBy(['publicId' => $customerPublicId]);

            if ($customer) {
                $qb->andWhere('s.customer = :customer')
                    ->setParameter('customer', $customer);
            }
        }

        $shipments = $qb->getQuery()->getResult();

        $data = array_map(fn(Shipment $s) => [
            'publicId' => $s->getPublicIdString(),
            'reference' => $s->getReference(),
            'recipientName' => $s->getRecipientName(),
            'address' => $s->getAddress(),
            'latitude' => $s->getLatitude(),
            'longitude' => $s->getLongitude(),
            'weightKg' => $s->getTotalWeightKg(),
            'volumeM3' => $s->getTotalVolumeM3(),
            'parcels' => $s->getTotalParcels(),
            'priority' => $s->getPriority()->name,
            'requiredSkills' => array_map(fn($sk) => $sk->label(), $s->getRequiredSkills()),
            'hasCoordinates' => $s->getLatitude() !== null && $s->getLongitude() !== null,
        ], $shipments);

        return new JsonResponse($data);
    }

    #[Route('/preview', name: 'admin_route_planner_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!$payload || empty($payload['shipment_ids']) || empty($payload['vehicle_ids'])) {
            return new JsonResponse(['error' => 'Se requieren envios y vehiculos'], 400);
        }

        $shipmentIds = $payload['shipment_ids'];
        $vehicleIds = $payload['vehicle_ids'];
        $originId = $payload['origin_id'] ?? null;
        $maxStops = (int) ($payload['max_stops_per_route'] ?? 30);

        // Load entities
        $shipments = $this->em->getRepository(Shipment::class)
            ->createQueryBuilder('s')
            ->where('s.publicId IN (:ids)')
            ->setParameter('ids', $shipmentIds)
            ->getQuery()
            ->getResult();

        $vehicles = $this->em->getRepository(Vehicle::class)
            ->createQueryBuilder('v')
            ->where('v.publicId IN (:ids)')
            ->setParameter('ids', $vehicleIds)
            ->getQuery()
            ->getResult();

        if (\count($shipments) === 0 || \count($vehicles) === 0) {
            return new JsonResponse(['error' => 'No se encontraron envios o vehiculos validos'], 400);
        }

        $customer = $shipments[0]->getCustomer();

        $origin = null;
        if ($originId) {
            $origin = $this->em->getRepository(CustomerLocation::class)
                ->findOneBy(['publicId' => $originId]);
        }

        // Build routes WITHOUT flushing (preview mode)
        $results = $this->routeBuilder->buildRoutes($shipments, $vehicles, $customer, $origin, $maxStops);

        // Serialize preview results
        $routes = [];
        foreach ($results as $result) {
            $route = $result['route'];
            $vehicle = $route->getVehicle();
            $validation = $result['validation'];

            $stops = [];
            foreach ($result['stops'] as $stop) {
                if ($stop->isOrigin()) {
                    continue;
                }
                $stops[] = [
                    'sequence' => $stop->getSequence(),
                    'shipmentPublicId' => $stop->getShipment()?->getPublicIdString(),
                    'recipientName' => $stop->getRecipientName(),
                    'address' => $stop->getAddress(),
                    'latitude' => $stop->getLatitude(),
                    'longitude' => $stop->getLongitude(),
                    'weightKg' => $stop->getShipment()?->getTotalWeightKg(),
                ];
            }

            $routes[] = [
                'vehicleName' => $vehicle?->getName(),
                'vehiclePublicId' => $vehicle?->getPublicIdString(),
                'distanceKm' => round($route->getTotalDistanceKm() ?? 0, 1),
                'durationMinutes' => $route->getEstimatedDurationMinutes(),
                'capacity' => [
                    'weightKg' => $validation['totalWeightKg'] ?? 0,
                    'maxWeightKg' => $vehicle?->getMaxWeightKg(),
                    'weightPct' => $validation['weightUtilization'] ?? null,
                    'volumeM3' => $validation['totalVolumeM3'] ?? 0,
                    'maxVolumeM3' => $vehicle?->getMaxVolumeM3(),
                    'volumePct' => $validation['volumeUtilization'] ?? null,
                    'parcels' => $validation['totalParcels'] ?? 0,
                    'maxParcels' => $vehicle?->getMaxParcels(),
                    'parcelPct' => $validation['parcelUtilization'] ?? null,
                    'valid' => $validation['valid'] ?? true,
                ],
                'stops' => $stops,
            ];
        }

        // Find unassigned shipments
        $assignedShipmentIds = [];
        foreach ($results as $result) {
            foreach ($result['stops'] as $stop) {
                $shipment = $stop->getShipment();
                if ($shipment) {
                    $assignedShipmentIds[] = $shipment->getPublicIdString();
                }
            }
        }

        $unassigned = [];
        foreach ($shipments as $shipment) {
            if (!\in_array($shipment->getPublicIdString(), $assignedShipmentIds, true)) {
                $reason = 'No asignado por el optimizador';
                if ($shipment->getLatitude() === null) {
                    $reason = 'Sin coordenadas';
                }
                $unassigned[] = [
                    'shipmentPublicId' => $shipment->getPublicIdString(),
                    'reference' => $shipment->getReference(),
                    'reason' => $reason,
                ];
            }
        }

        // Detach preview entities so they are NOT persisted
        foreach ($results as $result) {
            $this->em->detach($result['route']);
            foreach ($result['stops'] as $stop) {
                $this->em->detach($stop);
            }
        }

        return new JsonResponse([
            'routes' => $routes,
            'unassigned' => $unassigned,
        ]);
    }

    #[Route('/confirm', name: 'admin_route_planner_confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!$payload || empty($payload['shipment_ids']) || empty($payload['vehicle_ids'])) {
            return new JsonResponse(['error' => 'Se requieren envios y vehiculos'], 400);
        }

        $input = new BuildRoutesInput(
            shipmentPublicIds: $payload['shipment_ids'],
            vehiclePublicIds: $payload['vehicle_ids'],
            originPublicId: $payload['origin_id'] ?? null,
            maxStopsPerRoute: (int) ($payload['max_stops_per_route'] ?? 30),
        );

        $result = $this->planningService->buildRoutes($input);

        return new JsonResponse([
            'ok' => true,
            'routesCreated' => $result->routesCreated,
            'redirectUrl' => $this->generateUrl('admin_routes_index'),
        ]);
    }

    /**
     * @param Vehicle[] $vehicles
     * @return list<array<string, mixed>>
     */
    private function serializeVehicles(array $vehicles): array
    {
        return array_map(fn(Vehicle $v) => [
            'publicId' => $v->getPublicIdString(),
            'name' => $v->getName(),
            'maxWeightKg' => $v->getMaxWeightKg(),
            'maxVolumeM3' => $v->getMaxVolumeM3(),
            'maxParcels' => $v->getMaxParcels(),
            'skills' => array_map(fn($sk) => $sk->label(), $v->getSkills()),
        ], $vehicles);
    }

    /**
     * @param CustomerLocation[] $locations
     * @return list<array<string, mixed>>
     */
    private function serializeLocations(array $locations): array
    {
        return array_map(fn(CustomerLocation $l) => [
            'publicId' => $l->getPublicIdString(),
            'name' => $l->getName(),
            'address' => $l->getAddress(),
            'latitude' => $l->getLatitude(),
            'longitude' => $l->getLongitude(),
            'customerPublicId' => $l->getCustomer()->getPublicIdString(),
        ], $locations);
    }
}
