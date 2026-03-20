<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Application\Route\BuildRoutesInput;
use App\Application\Route\RoutePlanningService;
use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\RouteRepository;
use App\Service\AddressRiskService;
use App\Service\DriverScoringService;
use App\Service\ShipmentClusteringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[SymfonyRoute('/admin/route-planner')]
#[IsGranted('ROLE_ADMIN')]
class RoutePlannerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteRepository $routeRepository,
        private readonly RoutePlanningService $routePlanningService,
        private readonly DriverScoringService $driverScoringService,
        private readonly ShipmentClusteringService $clusteringService,
        private readonly AddressRiskService $addressRiskService,
    ) {}

    #[SymfonyRoute('', name: 'admin_route_planner_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirect('/app/admin/route-planner');
    }

    /**
     * Return unassigned shipments with coordinates for a given customer.
     */
    #[SymfonyRoute('/shipments', name: 'admin_route_planner_shipments', methods: ['GET'])]
    public function shipments(Request $request): JsonResponse
    {
        $customerId = $request->query->getString('customer_id', '');

        // Subquery: shipment IDs that are assigned to route stops of non-deleted routes.
        // The SoftDeleteFilter on Route automatically adds "r.deleted_at IS NULL",
        // so shipments from soft-deleted routes are treated as unassigned.
        $assignedDql = $this->em->createQueryBuilder()
            ->select('IDENTITY(rs_sub.shipment)')
            ->from(RouteStop::class, 'rs_sub')
            ->join('rs_sub.route', 'r_sub')
            ->where('rs_sub.shipment IS NOT NULL')
            ->getDQL();

        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Shipment::class, 's')
            ->where('s.latitude IS NOT NULL')
            ->andWhere('s.longitude IS NOT NULL')
            ->andWhere(sprintf('s.id NOT IN (%s)', $assignedDql));

        if ($customerId !== '') {
            $customer = $this->em->getRepository(Customer::class)->findOneBy(['publicId' => $customerId]);
            if ($customer !== null) {
                $qb->andWhere('s.customer = :customer')->setParameter('customer', $customer);
            }
        }

        $shipments = $qb->orderBy('s.id', 'DESC')->getQuery()->getResult();

        $data = [];
        foreach ($shipments as $shipment) {
            $addressRisk = $this->addressRiskService->checkAddress($shipment->getAddress() ?? '');
            $data[] = [
                'publicId' => $shipment->getPublicIdString(),
                'reference' => $shipment->getReference(),
                'address' => $shipment->getAddress(),
                'recipientName' => $shipment->getRecipientName(),
                'lat' => $shipment->getLatitude(),
                'lng' => $shipment->getLongitude(),
                'totalWeightKg' => $shipment->getTotalWeightKg(),
                'totalVolumeM3' => $shipment->getTotalVolumeM3(),
                'totalParcels' => $shipment->getTotalParcels(),
                'addressRisk' => $addressRisk,
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Return active vehicles.
     */
    #[SymfonyRoute('/vehicles', name: 'admin_route_planner_vehicles', methods: ['GET'])]
    public function vehicles(): JsonResponse
    {
        $vehicles = $this->em->createQueryBuilder()
            ->select('v')
            ->from(Vehicle::class, 'v')
            ->where('v.isActive = true')
            ->andWhere('v.deletedAt IS NULL')
            ->orderBy('v.name', 'ASC')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($vehicles as $vehicle) {
            $data[] = [
                'publicId' => $vehicle->getPublicIdString(),
                'name' => $vehicle->getName(),
                'maxWeightKg' => $vehicle->getMaxWeightKg(),
                'maxVolumeM3' => $vehicle->getMaxVolumeM3(),
                'maxParcels' => $vehicle->getMaxParcels(),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Return customer locations for origin selection.
     */
    #[SymfonyRoute('/locations', name: 'admin_route_planner_locations', methods: ['GET'])]
    public function locations(Request $request): JsonResponse
    {
        $customerId = $request->query->getString('customer_id', '');

        $qb = $this->em->createQueryBuilder()
            ->select('l')
            ->from(CustomerLocation::class, 'l')
            ->where('l.isActive = true')
            ->andWhere('l.latitude IS NOT NULL')
            ->andWhere('l.longitude IS NOT NULL')
            ->orderBy('l.name', 'ASC');

        if ($customerId !== '') {
            $customer = $this->em->getRepository(Customer::class)->findOneBy(['publicId' => $customerId]);
            if ($customer !== null) {
                $qb->andWhere('l.customer = :customer')->setParameter('customer', $customer);
            }
        }

        $locations = $qb->getQuery()->getResult();

        $data = [];
        foreach ($locations as $location) {
            $data[] = [
                'publicId' => $location->getPublicIdString(),
                'name' => $location->getName(),
                'address' => $location->getAddress(),
                'isDefault' => $location->isDefault(),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Cluster shipments by geographic zone using k-means++.
     */
    #[SymfonyRoute('/cluster', name: 'admin_route_planner_cluster', methods: ['POST'])]
    public function cluster(Request $request): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('route-planner-confirm', $csrfToken)) {
            return new JsonResponse(['error' => 'Token CSRF invalido.'], 403);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalido.'], 400);
        }

        $shipmentIds = $payload['shipment_ids'] ?? [];
        $numClusters = max(1, (int) ($payload['num_clusters'] ?? 3));

        if (!\is_array($shipmentIds) || $shipmentIds === []) {
            return new JsonResponse(['error' => 'Se requiere al menos un envio.'], 400);
        }

        $shipmentRepo = $this->em->getRepository(Shipment::class);
        $shipmentData = [];

        foreach ($shipmentIds as $pubId) {
            try {
                $shipment = $shipmentRepo->findOneBy(['publicId' => Ulid::fromString((string) $pubId)]);
            } catch (\Throwable) {
                continue;
            }
            if ($shipment !== null && $shipment->getLatitude() !== null && $shipment->getLongitude() !== null) {
                $shipmentData[] = [
                    'id' => $shipment->getPublicIdString(),
                    'lat' => $shipment->getLatitude(),
                    'lng' => $shipment->getLongitude(),
                ];
            }
        }

        if ($shipmentData === []) {
            return new JsonResponse(['error' => 'No se encontraron envios con coordenadas validas.'], 400);
        }

        $clusters = $this->clusteringService->cluster($shipmentData, $numClusters);

        return new JsonResponse(['clusters' => $clusters]);
    }

    /**
     * Build optimized routes from selected shipments and vehicles (preview).
     */
    #[SymfonyRoute('/preview', name: 'admin_route_planner_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('route-planner-confirm', $csrfToken)) {
            return new JsonResponse(['error' => 'Token CSRF invalido.'], 403);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalido.'], 400);
        }

        $shipmentIds = $payload['shipment_ids'] ?? [];
        $vehicleIds = $payload['vehicle_ids'] ?? [];
        $originPublicId = $payload['origin_public_id'] ?? null;
        $maxStopsPerRoute = (int) ($payload['max_stops_per_route'] ?? 30);

        if (\count($shipmentIds) === 0) {
            return new JsonResponse(['error' => 'Se requiere al menos un envio.'], 400);
        }

        if (\count($vehicleIds) === 0) {
            return new JsonResponse(['error' => 'Se requiere al menos un vehiculo.'], 400);
        }

        $input = new BuildRoutesInput(
            shipmentPublicIds: $shipmentIds,
            vehiclePublicIds: $vehicleIds,
            originPublicId: $originPublicId !== '' ? $originPublicId : null,
            maxStopsPerRoute: $maxStopsPerRoute,
        );

        try {
            $result = $this->routePlanningService->buildRoutes($input);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Error al generar rutas: ' . $e->getMessage()], 500);
        }

        return new JsonResponse($result->toArray());
    }

    /**
     * Suggest drivers for a given route, ranked by multi-criteria score.
     */
    #[SymfonyRoute('/suggest-drivers', name: 'admin_route_planner_suggest_drivers', methods: ['GET'])]
    public function suggestDrivers(Request $request): JsonResponse
    {
        $routePublicId = $request->query->getString('route_id', '');

        if ($routePublicId === '') {
            return new JsonResponse(['error' => 'Se requiere el parametro route_id.'], 400);
        }

        $route = $this->routeRepository->findOneByPublicId($routePublicId);

        if (!$route instanceof Route) {
            return new JsonResponse(['error' => 'Ruta no encontrada.'], 404);
        }

        $scores = $this->driverScoringService->scoreDriversForRoute($route);

        $result = [];
        foreach ($scores as $entry) {
            /** @var User $driver */
            $driver = $entry['driver'];
            $breakdown = $entry['breakdown'];

            $topCriterion = $this->getTopCriterion($breakdown);

            $result[] = [
                'driver_public_id' => $driver->getPublicIdString(),
                'driver_name' => $driver->getName() ?? $driver->getEmail(),
                'driver_email' => $driver->getEmail(),
                'score' => $entry['score'],
                'breakdown' => $breakdown,
                'top_criterion' => $topCriterion,
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * Confirm route planning and assign drivers to routes.
     */
    #[SymfonyRoute('/confirm', name: 'admin_route_planner_confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('route-planner-confirm', $csrfToken)) {
            return new JsonResponse(['error' => 'Token CSRF invalido.'], 403);
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalido.'], 400);
        }

        /** @var array<string, string> $driverAssignments */
        $driverAssignments = $payload['driver_assignments'] ?? [];

        $assigned = 0;
        $errors = [];

        foreach ($driverAssignments as $routePublicId => $driverPublicId) {
            if (!\is_string($routePublicId) || !\is_string($driverPublicId) || $driverPublicId === '') {
                continue;
            }

            $route = $this->routeRepository->findOneByPublicId($routePublicId);
            if (!$route instanceof Route) {
                $errors[] = sprintf('Ruta "%s" no encontrada.', $routePublicId);
                continue;
            }

            $driver = $this->em->getRepository(User::class)->findOneBy([
                'publicId' => $driverPublicId,
            ]);

            if (!$driver instanceof User) {
                $errors[] = sprintf('Conductor "%s" no encontrado.', $driverPublicId);
                continue;
            }

            if (!$driver->hasRole('ROLE_DRIVER')) {
                $errors[] = sprintf('El usuario "%s" no tiene el rol de conductor.', $driverPublicId);
                continue;
            }

            $route->setDriver($driver);
            $assigned++;
        }

        $this->em->flush();

        return new JsonResponse([
            'ok' => true,
            'assigned' => $assigned,
            'errors' => $errors,
        ]);
    }

    /**
     * @param array{zone: float, rating: float, workload: float, skills: float} $breakdown
     */
    private function getTopCriterion(array $breakdown): string
    {
        $labels = [
            'zone' => 'Zona',
            'rating' => 'Valoracion',
            'workload' => 'Disponibilidad',
            'skills' => 'Habilidades',
        ];

        $maxKey = 'zone';
        $maxVal = 0.0;

        foreach ($breakdown as $key => $val) {
            if ($val > $maxVal) {
                $maxVal = $val;
                $maxKey = $key;
            }
        }

        return $labels[$maxKey] ?? $maxKey;
    }
}
