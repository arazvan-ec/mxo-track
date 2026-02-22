<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use App\Entity\Customer;
use App\Service\ReportingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/customer/reports')]
#[IsGranted('ROLE_CUSTOMER')]
class CustomerReportController extends AbstractController
{
    public function __construct(
        private readonly ReportingService $reportingService,
    ) {}

    #[Route('', name: 'customer_reports_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $customer = $this->getCustomerOrDeny();

        [$from, $to] = $this->parseDateRange($request);

        if ($from === null) {
            $from = new \DateTimeImmutable('-30 days');
        }
        if ($to === null) {
            $to = new \DateTimeImmutable('now');
        }

        $report = $this->reportingService->getCustomerReport($customer, $from, $to);
        $deliveryReport = $this->reportingService->getDeliveryReport($from, $to, $customer);

        return $this->render('customer/report/index.html.twig', [
            'customer' => $customer,
            'report' => $report,
            'delivery_report' => $deliveryReport,
            'from' => $from,
            'to' => $to,
        ]);
    }

    #[Route('/export.csv', name: 'customer_reports_export', methods: ['GET'])]
    public function export(Request $request): StreamedResponse
    {
        $customer = $this->getCustomerOrDeny();

        [$from, $to] = $this->parseDateRange($request);

        if ($from === null) {
            $from = new \DateTimeImmutable('-30 days');
        }
        if ($to === null) {
            $to = new \DateTimeImmutable('now');
        }

        $report = $this->reportingService->getCustomerReport($customer, $from, $to);
        $deliveryReport = $this->reportingService->getDeliveryReport($from, $to, $customer);

        $response = new StreamedResponse(function () use ($customer, $report, $deliveryReport, $from, $to): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Reporte de ' . $customer->getName()]);
            fputcsv($handle, ['Periodo: ' . $from->format('Y-m-d') . ' a ' . $to->format('Y-m-d')]);
            fputcsv($handle, []);

            // Customer summary
            fputcsv($handle, ['Resumen']);
            fputcsv($handle, ['Metrica', 'Valor']);
            fputcsv($handle, ['Total envios', (string) $report['total_shipments']]);
            fputcsv($handle, ['Entregados', (string) $report['delivered']]);
            fputcsv($handle, ['Excepciones', (string) $report['exceptions']]);
            fputcsv($handle, ['Pendientes', (string) $report['pending']]);
            fputcsv($handle, ['Tasa de completado (%)', (string) $report['completion_rate']]);
            fputcsv($handle, []);

            // By driver breakdown
            if (\count($deliveryReport['by_driver']) > 0) {
                fputcsv($handle, ['Por Transportista']);
                fputcsv($handle, ['Nombre', 'Email', 'Entregas', 'Excepciones', 'Rutas']);
                foreach ($deliveryReport['by_driver'] as $row) {
                    fputcsv($handle, [
                        $row['driver_name'],
                        $row['driver_email'],
                        (string) $row['deliveries'],
                        (string) $row['exceptions'],
                        (string) $row['routes'],
                    ]);
                }
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="reporte_cliente.csv"');

        return $response;
    }

    private function getCustomerOrDeny(): Customer
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $customer = $user->getCustomer();

        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException('No tiene un cliente asociado.');
        }

        return $customer;
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
