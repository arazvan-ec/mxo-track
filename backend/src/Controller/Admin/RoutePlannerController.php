<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\Vehicle;
use App\Service\RouteBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/route-planner')]
#[IsGranted('ROLE_ADMIN')]
class RoutePlannerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteBuilder $routeBuilder,
    ) {}

    #[SymfonyRoute('', name: 'admin_route_planner_index', methods: ['GET'])]
    public function index(): Response
    {
        $customers = $this->em->getRepository(Customer::class)
            ->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $vehicles = $this->em->getRepository(Vehicle::class)
            ->createQueryBuilder('v')
            ->where('v.isActive = true')
            ->andWhere('v.deletedAt IS NULL')
            ->orderBy('v.name', 'ASC')
            ->getQuery()
            ->getResult();

        $locations = $this->em->getRepository(CustomerLocation::class)
            ->createQueryBuilder('l')
            ->where('l.isActive = true')
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/route_planner/index.html.twig', [
            'customers' => $customers,
            'vehicles' => $vehicles,
            'locations' => $locations,
        ]);
    }

    #[SymfonyRoute('/shipments', name: 'admin_route_planner_shipments', methods: ['GET'])]
    public function shipments(Request $request): JsonResponse
    {
        $customerPublicId = $request->query->getString('customer', '');

        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Shipment::class, 's')
            ->leftJoin(RouteStop::class, 'rs', 'WITH', 'rs.shipment = s')
            ->where('rs.id IS NULL')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.latitude IS NOT NULL')
            ->andWhere('s.longitude IS NOT NULL')
            ->orderBy('s.createdAt', 'DESC');

        if ($customerPublicId !== '') {
            $customer = $this->em->getRepository(Customer::class)
                ->findOneBy(['publicId' => $customerPublicId]);

            if ($customer === null) {
                return new JsonResponse(['shipments' => []]);
            }

            $qb->andWhere('s.customer = :customer')
                ->setParameter('customer', $customer);
        }

        $shipments = $qb->setMaxResults(200)->getQuery()->getResult();

        $data = [];
        foreach ($shipments as $shipment) {
            $data[] = [
                'publicId' => $shipment->getPublicIdString(),
                'reference' => $shipment->getReference(),
                'address' => $shipment->getAddress(),
                'recipientName' => $shipment->getRecipientName(),
                'latitude' => $shipment->getLatitude(),
                'longitude' => $shipment->getLongitude(),
                'totalWeightKg' => $shipment->getTotalWeightKg(),
                'totalVolumeM3' => $shipment->getTotalVolumeM3(),
                'totalParcels' => $shipment->getTotalParcels(),
            ];
        }

        return new JsonResponse(['shipments' => $data]);
    }

    #[SymfonyRoute('/preview', name: 'admin_route_planner_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'JSON invalido.'], 400);
        }

        $shipmentIds = $payload['shipment_ids'] ?? [];
        $vehicleIds = $payload['vehicle_ids'] ?? [];
        $originPublicId = $payload['origin_public_id'] ?? null;
        $maxStopsPerRoute = (int) ($payload['max_stops_per_route'] ?? 30);

        if (\count($shipmentIds) === 0 || \count($vehicleIds) === 0) {
            return new JsonResponse(['error' => 'Se requieren envios y vehiculos.'], 400);
        }

        /** @var list<Shipment> $shipments */
        $shipments = $this->em->getRepository(Shipment::class)
            ->createQueryBuilder('s')
            ->where('s.publicId IN (:ids)')
            ->setParameter('ids', $shipmentIds)
            ->getQuery()
            ->getResult();

        if (\count($shipments) === 0) {
            return new JsonResponse(['error' => 'No se encontraron envios validos.'], 400);
        }

        /** @var list<Vehicle> $vehicles */
        $vehicles = $this->em->getRepository(Vehicle::class)
            ->createQueryBuilder('v')
            ->where('v.publicId IN (:ids)')
            ->setParameter('ids', $vehicleIds)
            ->getQuery()
            ->getResult();

        if (\count($vehicles) === 0) {
            return new JsonResponse(['error' => 'No se encontraron vehiculos validos.'], 400);
        }

        $customer = $shipments[0]->getCustomer();

        $origin = null;
        if ($originPublicId !== null && $originPublicId !== '') {
            $origin = $this->em->getRepository(CustomerLocation::class)
                ->findOneBy(['publicId' => $originPublicId]);
        }

        try {
            $results = $this->routeBuilder->buildRoutes(
                $shipments,
                $vehicles,
                $customer,
                $origin,
                $maxStopsPerRoute,
            );
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Error al optimizar rutas: ' . $e->getMessage()], 500);
        }

        // Build preview response without flushing - detach persisted entities
        $response = [];
        foreach ($results as $result) {
            /** @var Route $route */
            $route = $result['route'];
            /** @var list<RouteStop> $stops */
            $stops = $result['stops'];

            $stopsData = [];
            foreach ($stops as $stop) {
                $stopsData[] = [
                    'sequence' => $stop->getSequence(),
                    'address' => $stop->getAddress(),
                    'latitude' => $stop->getLatitude(),
                    'longitude' => $stop->getLongitude(),
                    'recipientName' => $stop->getRecipientName(),
                    'isOrigin' => $stop->isOrigin(),
                    'shipmentPublicId' => $stop->getShipment()?->getPublicIdString(),
                ];
            }

            $response[] = [
                'name' => $route->getName(),
                'vehicleName' => $route->getVehicle()?->getName(),
                'vehiclePublicId' => $route->getVehicle()?->getPublicIdString(),
                'totalDistanceKm' => $route->getTotalDistanceKm(),
                'estimatedDurationMinutes' => $route->getEstimatedDurationMinutes(),
                'stopsCount' => \count($stops),
                'stops' => $stopsData,
                'validation' => $result['validation'],
            ];

            // Detach preview entities so they are not persisted
            foreach ($stops as $stop) {
                $this->em->detach($stop);
            }
            $this->em->detach($route);
        }

        return new JsonResponse(['routes' => $response]);
    }

    #[SymfonyRoute('/confirm', name: 'admin_route_planner_confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->addFlash('error', 'Datos invalidos.');

            return $this->redirectToRoute('admin_route_planner_index');
        }

        $shipmentIds = $payload['shipment_ids'] ?? [];
        $vehicleIds = $payload['vehicle_ids'] ?? [];
        $originPublicId = $payload['origin_public_id'] ?? null;
        $maxStopsPerRoute = (int) ($payload['max_stops_per_route'] ?? 30);

        if (\count($shipmentIds) === 0 || \count($vehicleIds) === 0) {
            $this->addFlash('error', 'Se requieren envios y vehiculos.');

            return $this->redirectToRoute('admin_route_planner_index');
        }

        /** @var list<Shipment> $shipments */
        $shipments = $this->em->getRepository(Shipment::class)
            ->createQueryBuilder('s')
            ->where('s.publicId IN (:ids)')
            ->setParameter('ids', $shipmentIds)
            ->getQuery()
            ->getResult();

        if (\count($shipments) === 0) {
            $this->addFlash('error', 'No se encontraron envios validos.');

            return $this->redirectToRoute('admin_route_planner_index');
        }

        /** @var list<Vehicle> $vehicles */
        $vehicles = $this->em->getRepository(Vehicle::class)
            ->createQueryBuilder('v')
            ->where('v.publicId IN (:ids)')
            ->setParameter('ids', $vehicleIds)
            ->getQuery()
            ->getResult();

        if (\count($vehicles) === 0) {
            $this->addFlash('error', 'No se encontraron vehiculos validos.');

            return $this->redirectToRoute('admin_route_planner_index');
        }

        $customer = $shipments[0]->getCustomer();

        $origin = null;
        if ($originPublicId !== null && $originPublicId !== '') {
            $origin = $this->em->getRepository(CustomerLocation::class)
                ->findOneBy(['publicId' => $originPublicId]);
        }

        try {
            $results = $this->routeBuilder->buildRoutes(
                $shipments,
                $vehicles,
                $customer,
                $origin,
                $maxStopsPerRoute,
            );

            $this->em->flush();
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Error al crear rutas: ' . $e->getMessage());

            return $this->redirectToRoute('admin_route_planner_index');
        }

        $this->addFlash('success', sprintf('%d ruta(s) creada(s) correctamente.', \count($results)));

        return new JsonResponse([
            'success' => true,
            'routesCreated' => \count($results),
            'redirectUrl' => $this->generateUrl('admin_routes_index'),
        ]);
    }
}
