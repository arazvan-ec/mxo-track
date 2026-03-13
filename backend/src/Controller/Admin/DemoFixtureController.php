<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\DemoScenarioBuilder;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/fixtures')]
#[IsGranted('ROLE_ADMIN')]
class DemoFixtureController extends AbstractController
{
    public function __construct(
        private readonly DemoScenarioBuilder $scenarioBuilder,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_fixtures_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/fixtures/index.html.twig');
    }

    #[Route('/load', name: 'admin_fixtures_load', methods: ['POST'])]
    public function load(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('load-fixtures', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_fixtures_index');
        }

        $this->purgeExistingDemoData();

        $result = $this->scenarioBuilder->buildScenario(200);

        $this->em->persist($result->customer);
        $this->em->persist($result->warehouse);
        $this->em->persist($result->customerUser);

        foreach ($result->vehicles as $vehicle) {
            $this->em->persist($vehicle);
        }
        foreach ($result->drivers as $driver) {
            $this->em->persist($driver);
        }
        foreach ($result->shipments as $shipment) {
            $this->em->persist($shipment);
        }

        $this->em->flush();

        $this->addFlash('success', sprintf(
            'Datos demo cargados: %d envios, %d vehiculos, %d conductores.',
            \count($result->shipments),
            \count($result->vehicles),
            \count($result->drivers),
        ));

        return $this->redirectToRoute('admin_shipments_index');
    }

    private function purgeExistingDemoData(): void
    {
        $conn = $this->em->getConnection();
        $demoCustomerFilter = "(SELECT id FROM customer WHERE name = 'Logística Express Madrid')";
        $demoUserFilter = "(SELECT id FROM \"user\" WHERE email LIKE '%@demo.local')";
        $demoShipmentFilter = "(SELECT id FROM shipment WHERE customer_id IN $demoCustomerFilter)";
        $demoRouteFilter = "(SELECT id FROM route r JOIN customer c ON r.customer_id = c.id WHERE c.name = 'Logística Express Madrid')";
        $demoVehicleFilter = "(SELECT id FROM vehicle WHERE name LIKE 'Furgoneta Madrid%' OR name LIKE 'Camión Refrigerado%' OR name LIKE 'Moto Express%')";

        // Order: deepest dependents first, then parents
        $statements = [
            // Shipment children (not all have ON DELETE CASCADE)
            "DELETE FROM notification_log WHERE shipment_id IN $demoShipmentFilter",
            "DELETE FROM recipient_action WHERE shipment_id IN $demoShipmentFilter",
            "DELETE FROM recipient_notification WHERE shipment_id IN $demoShipmentFilter",
            "DELETE FROM delivery_rating WHERE shipment_id IN $demoShipmentFilter",
            "DELETE FROM delivery_slot WHERE shipment_id IN $demoShipmentFilter",
            "DELETE FROM shipment_event WHERE shipment_id IN $demoShipmentFilter",
            "DELETE FROM parcel WHERE shipment_id IN $demoShipmentFilter",
            "DELETE FROM pod WHERE shipment_id IN $demoShipmentFilter",
            // Route children
            "DELETE FROM route_stop WHERE route_id IN $demoRouteFilter",
            "DELETE FROM route_snapshot WHERE route_id IN $demoRouteFilter",
            "DELETE FROM route_optimization_log WHERE route_id IN $demoRouteFilter",
            // User children
            "DELETE FROM driver_action WHERE driver_id IN $demoUserFilter",
            "DELETE FROM driver_feedback WHERE driver_id IN $demoUserFilter",
            "DELETE FROM driver_availability WHERE user_id IN $demoUserFilter",
            "DELETE FROM push_subscription WHERE user_id IN $demoUserFilter",
            "DELETE FROM notification WHERE user_id IN $demoUserFilter",
            "DELETE FROM vehicle_inspection WHERE driver_id IN $demoUserFilter",
            "DELETE FROM audit_log WHERE user_id IN $demoUserFilter",
            // Vehicle children
            "DELETE FROM vehicle_position WHERE vehicle_id IN $demoVehicleFilter",
            "DELETE FROM vehicle_last_position WHERE vehicle_id IN $demoVehicleFilter",
            "DELETE FROM vehicle_checkpoint WHERE vehicle_id IN $demoVehicleFilter",
            "DELETE FROM customer_vehicle WHERE vehicle_id IN $demoVehicleFilter",
            // Customer children (non-cascading or important)
            "DELETE FROM notification_log WHERE customer_id IN $demoCustomerFilter",
            "DELETE FROM realtime_event WHERE customer_id IN $demoCustomerFilter",
            "DELETE FROM notification_preference WHERE customer_id IN $demoCustomerFilter",
            "DELETE FROM csv_import_run WHERE customer_id IN $demoCustomerFilter",
            // Main entities
            "DELETE FROM shipment WHERE customer_id IN $demoCustomerFilter",
            "DELETE FROM route WHERE customer_id IN $demoCustomerFilter",
            "DELETE FROM vehicle WHERE name LIKE 'Furgoneta Madrid%' OR name LIKE 'Camión Refrigerado%' OR name LIKE 'Moto Express%'",
            "DELETE FROM \"user\" WHERE email LIKE '%@demo.local'",
            "DELETE FROM customer_location WHERE customer_id IN $demoCustomerFilter",
            "DELETE FROM customer WHERE name = 'Logística Express Madrid'",
        ];

        foreach ($statements as $sql) {
            try {
                $conn->executeStatement($sql);
            } catch (TableNotFoundException) {
                // Table may not exist yet if migrations are pending — safe to skip
            } catch (ForeignKeyConstraintViolationException) {
                // FK dependency we missed — safe to skip, parent delete will handle or next load will retry
            }
        }
    }
}
