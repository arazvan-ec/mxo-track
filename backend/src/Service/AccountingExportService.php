<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;

final class AccountingExportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BillingService $billingService,
    ) {}

    /**
     * Generate CSV content for a customer's shipments in the given date range.
     */
    public function exportCsv(Customer $customer, \DateTimeInterface $from, \DateTimeInterface $to): string
    {
        $rows = $this->queryShipments($customer, $from, $to);

        $handle = fopen('php://memory', 'r+');
        fputcsv($handle, ['Fecha', 'Referencia', 'Destinatario', 'Tipo Servicio', 'Estado', 'Peso (kg)']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['created_at'],
                $row['reference'],
                $row['recipient_name'] ?? '',
                $row['service_type'],
                $row['status'],
                $row['total_weight_kg'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Return a summary array for a customer in the given date range.
     *
     * @return array{total_shipments: int, total_delivered: int, total_exceptions: int, billable_deliveries: int}
     */
    public function exportSummary(Customer $customer, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->billingService->getCustomerSummary($customer, $from, $to);
    }

    /**
     * @return list<array{created_at: string, reference: string, recipient_name: ?string, service_type: string, status: string, total_weight_kg: ?string}>
     */
    private function queryShipments(Customer $customer, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $conn = $this->em->getConnection();

        return $conn->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    s.created_at::date::text AS created_at,
                    s.reference,
                    s.recipient_name,
                    s.service_type,
                    COALESCE(
                        (SELECT se.event_type
                         FROM shipment_event se
                         WHERE se.shipment_id = s.id
                         ORDER BY se.created_at DESC
                         LIMIT 1),
                        'PENDING'
                    ) AS status,
                    s.total_weight_kg
                FROM shipment s
                WHERE s.customer_id = :cid
                  AND s.created_at BETWEEN :from AND :to
                ORDER BY s.created_at ASC
                SQL,
            [
                'cid' => $customer->getId(),
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d 23:59:59'),
            ]
        );
    }
}
