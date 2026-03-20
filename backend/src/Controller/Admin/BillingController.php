<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Service\BillingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/billing')]
#[IsGranted('ROLE_ADMIN')]
final class BillingController extends AbstractController
{
    public function __construct(
        private readonly BillingService $billingService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_billing_index')]
    public function index(Request $request): Response
    {
        $from = $request->query->get('from')
            ? new \DateTimeImmutable($request->query->get('from'))
            : new \DateTimeImmutable('first day of this month');
        $to = $request->query->get('to')
            ? new \DateTimeImmutable($request->query->get('to'))
            : new \DateTimeImmutable('today');

        $customers = $this->em->getRepository(Customer::class)->findBy(['isActive' => true]);

        $rows = [];
        foreach ($customers as $customer) {
            $summary = $this->billingService->getCustomerSummary($customer, $from, $to);
            $rows[] = [
                'customer' => $customer,
                ...$summary,
            ];
        }

        return $this->render('admin/billing/index.html.twig', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ]);
    }

    #[Route('/export.csv', name: 'admin_billing_export')]
    public function exportCsv(Request $request): StreamedResponse
    {
        $from = $request->query->get('from')
            ? new \DateTimeImmutable($request->query->get('from'))
            : new \DateTimeImmutable('first day of this month');
        $to = $request->query->get('to')
            ? new \DateTimeImmutable($request->query->get('to'))
            : new \DateTimeImmutable('today');

        $customers = $this->em->getRepository(Customer::class)->findBy(['isActive' => true]);

        return new StreamedResponse(function () use ($customers, $from, $to) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Cliente', 'Envios', 'Entregados', 'Excepciones', 'Facturables', 'Km Ahorrados', 'Tiempo Ahorrado (min)', 'Ahorro %', 'Rutas con Metricas']);

            foreach ($customers as $customer) {
                $s = $this->billingService->getCustomerSummary($customer, $from, $to);
                fputcsv($handle, [
                    $customer->getName(),
                    $s['total_shipments'],
                    $s['total_delivered'],
                    $s['total_exceptions'],
                    $s['billable_deliveries'],
                    $s['total_km_saved'] ?? '',
                    $s['total_time_saved_minutes'] ?? '',
                    $s['avg_savings_percent'] ?? '',
                    $s['routes_with_metrics'],
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="billing_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.csv"',
        ]);
    }
}
