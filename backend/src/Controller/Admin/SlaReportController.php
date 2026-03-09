<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Service\SlaMetricsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports/sla')]
#[IsGranted('ROLE_ADMIN')]
class SlaReportController extends AbstractController
{
    public function __construct(
        private readonly SlaMetricsService $slaMetricsService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_reports_sla', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $customers = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->where('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        [$from, $to, $customer] = $this->parseFilters($request, $customers);

        $sla = $this->slaMetricsService->calculateSla($customer, $from, $to);

        return $this->render('admin/reports/sla.html.twig', [
            'sla' => $sla,
            'customers' => $customers,
            'selected_customer_id' => $customer?->getId(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    #[Route('/data', name: 'admin_reports_sla_data', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        $customers = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->where('c.isActive = true')
            ->getQuery()
            ->getResult();

        [$from, $to, $customer] = $this->parseFilters($request, $customers);

        $sla = $this->slaMetricsService->calculateSla($customer, $from, $to);

        return $this->json([
            'sla' => $sla,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);
    }

    /**
     * Export SLA report as a printable HTML page (PDF-ready).
     *
     * TODO: Integrate DomPDF to return actual PDF response once the library is installed via composer.
     * For now, renders an HTML view suitable for printing / saving as PDF from the browser.
     */
    #[Route('/export', name: 'admin_reports_sla_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $customers = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->where('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        [$from, $to, $customer] = $this->parseFilters($request, $customers);

        $sla = $this->slaMetricsService->calculateSla($customer, $from, $to);

        return $this->render('admin/reports/sla_export.html.twig', [
            'sla' => $sla,
            'customer_name' => $customer?->getName() ?? 'Todos los clientes',
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * @param list<Customer> $customers
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: ?Customer}
     */
    private function parseFilters(Request $request, array $customers): array
    {
        $fromStr = $request->query->getString('from', '');
        $toStr = $request->query->getString('to', '');
        $period = $request->query->getString('period', 'month');
        $customerIdStr = $request->query->getString('customer_id', '');

        // Determine date range from period preset or custom dates
        if ($fromStr !== '' && $toStr !== '') {
            try {
                $from = new \DateTimeImmutable($fromStr);
                $from = $from->setTime(0, 0, 0);
            } catch (\Exception) {
                $from = new \DateTimeImmutable('-30 days');
                $from = $from->setTime(0, 0, 0);
            }

            try {
                $to = new \DateTimeImmutable($toStr);
                $to = $to->setTime(23, 59, 59);
            } catch (\Exception) {
                $to = new \DateTimeImmutable('now');
                $to = $to->setTime(23, 59, 59);
            }
        } elseif ($period === 'week') {
            $from = (new \DateTimeImmutable('-7 days'))->setTime(0, 0, 0);
            $to = (new \DateTimeImmutable('now'))->setTime(23, 59, 59);
        } else {
            // Default: last 30 days
            $from = (new \DateTimeImmutable('-30 days'))->setTime(0, 0, 0);
            $to = (new \DateTimeImmutable('now'))->setTime(23, 59, 59);
        }

        // Find customer by ID
        $customer = null;
        if ($customerIdStr !== '') {
            $customerId = (int) $customerIdStr;
            foreach ($customers as $c) {
                if ($c->getId() === $customerId) {
                    $customer = $c;
                    break;
                }
            }
        }

        return [$from, $to, $customer];
    }
}
