<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Model\RouteStop;
use App\Entity\AddressRisk;
use App\Enum\RouteStopStatus;
use App\Repository\AddressRiskRepository;
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
        private readonly AddressRiskRepository $addressRiskRepository,
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

    /**
     * Update or create AddressRisk entries from completed route stops.
     *
     * Groups stops by address (skipping origins and non-terminal statuses),
     * then increments delivery/exception counters on the corresponding
     * AddressRisk record.
     *
     * @param RouteStop[] $stops
     */
    public function updateFromRouteStops(array $stops): void
    {
        $terminalStatuses = [RouteStopStatus::DELIVERED, RouteStopStatus::EXCEPTION];

        // Group relevant stops by lowercase address
        /** @var array<string, RouteStop[]> $grouped */
        $grouped = [];
        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                continue;
            }
            if (!\in_array($stop->getStatus(), $terminalStatuses, true)) {
                continue;
            }
            $key = mb_strtolower($stop->getAddress());
            $grouped[$key][] = $stop;
        }

        if ($grouped === []) {
            return;
        }

        $dirty = false;

        foreach ($grouped as $normalizedAddress => $addressStops) {
            $hash = md5($normalizedAddress);
            $originalAddress = $addressStops[0]->getAddress();

            $newTotal = \count($addressStops);
            $newExceptions = 0;
            $exceptionCodes = [];

            foreach ($addressStops as $stop) {
                if ($stop->getStatus() === RouteStopStatus::EXCEPTION) {
                    $newExceptions++;
                    $code = $stop->getExceptionCode();
                    if ($code !== null) {
                        $exceptionCodes[] = $code->value;
                    }
                }
            }

            $risk = $this->addressRiskRepository->findByAddressHash($hash);

            if ($risk === null) {
                $risk = new AddressRisk($hash, $originalAddress);
                $risk->setTotalDeliveries($newTotal);
                $risk->setExceptionCount($newExceptions);
                $this->em->persist($risk);
            } else {
                $risk->setTotalDeliveries($risk->getTotalDeliveries() + $newTotal);
                $risk->setExceptionCount($risk->getExceptionCount() + $newExceptions);
            }

            $total = $risk->getTotalDeliveries();
            $risk->setExceptionRate($total > 0 ? $risk->getExceptionCount() / $total : 0.0);
            $risk->setLastExceptionCodes($exceptionCodes !== [] ? $exceptionCodes : null);
            $risk->setLastUpdated(new \DateTimeImmutable());

            $dirty = true;
        }

        if ($dirty) {
            $this->em->flush();
        }
    }
}
