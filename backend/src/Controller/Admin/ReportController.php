<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\User;
use App\Service\ReportingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_ADMIN')]
class ReportController extends AbstractController
{
    public function __construct(
        private readonly ReportingService $reportingService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_reports_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/report/index.html.twig');
    }

    #[Route('/deliveries', name: 'admin_reports_deliveries', methods: ['GET'])]
    public function deliveries(Request $request): Response
    {
        [$from, $to] = $this->parseDateRange($request);

        $report = $this->reportingService->getDeliveryReport($from, $to);
        $trendData = $this->reportingService->getTrendData(
            $request->query->getString('period', 'week'),
            12,
        );
        $statusDistribution = $this->reportingService->getStopStatusDistribution($from, $to);

        return $this->render('admin/report/deliveries.html.twig', [
            'report' => $report,
            'trend_data' => $trendData,
            'status_distribution' => $statusDistribution,
            'from' => $from,
            'to' => $to,
            'period' => $request->query->getString('period', 'week'),
        ]);
    }

    #[Route('/drivers', name: 'admin_reports_drivers', methods: ['GET'])]
    public function drivers(Request $request): Response
    {
        [$from, $to] = $this->parseDateRange($request);

        // Default to last 30 days if no range specified
        if ($from === null) {
            $from = new \DateTimeImmutable('-30 days');
        }
        if ($to === null) {
            $to = new \DateTimeImmutable('now');
        }

        $ranking = $this->reportingService->getDriverRanking($from, $to);

        return $this->render('admin/report/drivers.html.twig', [
            'ranking' => $ranking,
            'from' => $from,
            'to' => $to,
        ]);
    }

    #[Route('/customers', name: 'admin_reports_customers', methods: ['GET'])]
    public function customers(Request $request): Response
    {
        [$from, $to] = $this->parseDateRange($request);

        // Default to last 30 days if no range specified
        if ($from === null) {
            $from = new \DateTimeImmutable('-30 days');
        }
        if ($to === null) {
            $to = new \DateTimeImmutable('now');
        }

        $customers = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->where('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        $customerReports = [];
        foreach ($customers as $customer) {
            $customerReports[] = [
                'customer' => $customer,
                'report' => $this->reportingService->getCustomerReport($customer, $from, $to),
            ];
        }

        return $this->render('admin/report/customers.html.twig', [
            'customer_reports' => $customerReports,
            'from' => $from,
            'to' => $to,
        ]);
    }

    #[Route('/export/deliveries.csv', name: 'admin_reports_export_deliveries', methods: ['GET'])]
    public function exportDeliveries(Request $request): StreamedResponse
    {
        [$from, $to] = $this->parseDateRange($request);
        $report = $this->reportingService->getDeliveryReport($from, $to);

        $response = new StreamedResponse(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Summary section
            fputcsv($handle, ['Reporte de Entregas']);
            fputcsv($handle, []);
            fputcsv($handle, ['Metrica', 'Valor']);
            fputcsv($handle, ['Total entregas', (string) $report['total_deliveries']]);
            fputcsv($handle, ['Total excepciones', (string) $report['total_exceptions']]);
            fputcsv($handle, ['Tasa de exito (%)', (string) $report['success_rate']]);
            fputcsv($handle, ['Promedio entregas por ruta', (string) $report['avg_deliveries_per_route']]);
            fputcsv($handle, []);

            // By driver section
            fputcsv($handle, ['Por Transportista']);
            fputcsv($handle, ['Nombre', 'Email', 'Entregas', 'Excepciones', 'Rutas']);
            foreach ($report['by_driver'] as $row) {
                fputcsv($handle, [
                    $row['driver_name'],
                    $row['driver_email'],
                    (string) $row['deliveries'],
                    (string) $row['exceptions'],
                    (string) $row['routes'],
                ]);
            }
            fputcsv($handle, []);

            // By customer section
            fputcsv($handle, ['Por Cliente']);
            fputcsv($handle, ['Cliente', 'Entregas', 'Excepciones', 'Rutas']);
            foreach ($report['by_customer'] as $row) {
                fputcsv($handle, [
                    $row['customer_name'],
                    (string) $row['deliveries'],
                    (string) $row['exceptions'],
                    (string) $row['routes'],
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="reporte_entregas.csv"');

        return $response;
    }

    #[Route('/export/drivers.csv', name: 'admin_reports_export_drivers', methods: ['GET'])]
    public function exportDrivers(Request $request): StreamedResponse
    {
        [$from, $to] = $this->parseDateRange($request);

        if ($from === null) {
            $from = new \DateTimeImmutable('-30 days');
        }
        if ($to === null) {
            $to = new \DateTimeImmutable('now');
        }

        $ranking = $this->reportingService->getDriverRanking($from, $to);

        $response = new StreamedResponse(function () use ($ranking): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Rendimiento de Transportistas']);
            fputcsv($handle, []);
            fputcsv($handle, ['Nombre', 'Email', 'Rutas completadas', 'Entregas', 'Excepciones', 'Tasa de exito (%)']);
            foreach ($ranking as $row) {
                fputcsv($handle, [
                    $row['driver_name'],
                    $row['driver_email'],
                    (string) $row['routes_completed'],
                    (string) $row['deliveries'],
                    (string) $row['exceptions'],
                    (string) $row['success_rate'],
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="reporte_transportistas.csv"');

        return $response;
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private function parseDateRange(Request $request): array
    {
        $fromStr = $request->query->getString('from', '');
        $toStr = $request->query->getString('to', '');

        $from = null;
        $to = null;

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

        return [$from, $to];
    }
}
