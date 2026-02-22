<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;

final class BillingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array{total_shipments: int, total_delivered: int, total_exceptions: int, billable_deliveries: int}
     */
    public function getCustomerSummary(
        Customer $customer,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        $conn = $this->em->getConnection();

        $totalShipments = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM shipment WHERE customer_id = :cid AND created_at BETWEEN :from AND :to',
            ['cid' => $customer->getId(), 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d 23:59:59')]
        );

        $delivered = (int) $conn->fetchOne(
            "SELECT COUNT(DISTINCT se.shipment_id) FROM shipment_event se
             JOIN shipment s ON s.id = se.shipment_id
             WHERE s.customer_id = :cid AND se.event_type = 'DELIVERED'
             AND se.created_at BETWEEN :from AND :to",
            ['cid' => $customer->getId(), 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d 23:59:59')]
        );

        $exceptions = (int) $conn->fetchOne(
            "SELECT COUNT(DISTINCT se.shipment_id) FROM shipment_event se
             JOIN shipment s ON s.id = se.shipment_id
             WHERE s.customer_id = :cid AND se.event_type = 'EXCEPTION'
             AND se.created_at BETWEEN :from AND :to",
            ['cid' => $customer->getId(), 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d 23:59:59')]
        );

        return [
            'total_shipments' => $totalShipments,
            'total_delivered' => $delivered,
            'total_exceptions' => $exceptions,
            'billable_deliveries' => $delivered,
        ];
    }
}
