<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Evaluates delivery risk based on historical address data.
 *
 * Checks whether an address (or nearby addresses) has a history of
 * failed deliveries (exceptions).
 */
final class AddressRiskService
{
    /** Exception rate above this threshold flags the address as risky. */
    private const float RISK_THRESHOLD = 0.3;

    /** Minimum number of past deliveries at the address to consider it. */
    private const int MIN_SAMPLES = 3;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Check whether an address has a high historical failure rate.
     *
     * @return array{is_risky: bool, exception_rate: float, sample_count: int}
     */
    public function checkAddress(string $address): array
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(*)                                                     AS total,
                COUNT(*) FILTER (WHERE rs.status = 'EXCEPTION')              AS exceptions
            FROM route_stop rs
            WHERE LOWER(rs.address) = LOWER(:address)
              AND rs.status IN ('DELIVERED', 'EXCEPTION')
              AND rs.is_origin = false
        SQL;

        try {
            $conn = $this->em->getConnection();
            $result = $conn->executeQuery($sql, ['address' => $address])->fetchAssociative();

            $total = (int) ($result['total'] ?? 0);
            $exceptions = (int) ($result['exceptions'] ?? 0);

            if ($total < self::MIN_SAMPLES) {
                return ['is_risky' => false, 'exception_rate' => 0.0, 'sample_count' => $total];
            }

            $rate = $exceptions / $total;

            return [
                'is_risky' => $rate >= self::RISK_THRESHOLD,
                'exception_rate' => round($rate, 4),
                'sample_count' => $total,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('AddressRiskService query failed', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);

            return ['is_risky' => false, 'exception_rate' => 0.0, 'sample_count' => 0];
        }
    }
}
