<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AddressRisk;
use App\Enum\RouteStopStatus;
use App\Repository\AddressRiskRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class AddressRiskService
{
    private const MAX_LAST_EXCEPTION_CODES = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AddressRiskRepository $addressRiskRepository,
    ) {
    }

    /**
     * Look up the risk profile for a given address.
     */
    public function checkAddress(string $address): ?AddressRisk
    {
        $normalized = $this->normalizeAddress($address);
        $hash = hash('sha256', $normalized);

        return $this->addressRiskRepository->findByAddressHash($hash);
    }

    /**
     * Recalculate ALL address risk scores from RouteStop history.
     *
     * Returns the number of addresses updated.
     */
    public function updateRiskScores(): int
    {
        $conn = $this->entityManager->getConnection();

        // Query all RouteStops with DELIVERED or EXCEPTION status, grouped by address
        $sql = <<<'SQL'
            SELECT
                rs.address,
                COUNT(*) AS total_deliveries,
                COUNT(*) FILTER (WHERE rs.status = :exception) AS exception_count
            FROM route_stop rs
            WHERE rs.status IN (:delivered, :exception)
              AND rs.is_origin = false
            GROUP BY rs.address
            SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'delivered' => RouteStopStatus::DELIVERED->value,
            'exception' => RouteStopStatus::EXCEPTION->value,
        ]);

        // For addresses with exceptions, fetch the last N exception codes
        $exceptionCodesSql = <<<'SQL'
            SELECT rs.exception_code
            FROM route_stop rs
            WHERE rs.address = :address
              AND rs.status = :exception
              AND rs.exception_code IS NOT NULL
            ORDER BY rs.id DESC
            LIMIT :limit
            SQL;

        $updatedCount = 0;

        foreach ($rows as $row) {
            $address = $row['address'];
            $totalDeliveries = (int) $row['total_deliveries'];
            $exceptionCount = (int) $row['exception_count'];

            $normalized = $this->normalizeAddress($address);
            $hash = hash('sha256', $normalized);
            $exceptionRate = $totalDeliveries > 0 ? $exceptionCount / $totalDeliveries : 0.0;

            // Fetch last exception codes if there are exceptions
            $lastExceptionCodes = null;
            if ($exceptionCount > 0) {
                $codeRows = $conn->fetchAllAssociative($exceptionCodesSql, [
                    'address' => $address,
                    'exception' => RouteStopStatus::EXCEPTION->value,
                    'limit' => self::MAX_LAST_EXCEPTION_CODES,
                ]);
                $lastExceptionCodes = array_column($codeRows, 'exception_code');
            }

            // Upsert: find existing or create new
            $addressRisk = $this->addressRiskRepository->findByAddressHash($hash);

            if ($addressRisk === null) {
                $addressRisk = new AddressRisk($hash, $address);
                $this->entityManager->persist($addressRisk);
            }

            $addressRisk->setTotalDeliveries($totalDeliveries);
            $addressRisk->setExceptionCount($exceptionCount);
            $addressRisk->setExceptionRate($exceptionRate);
            $addressRisk->setLastExceptionCodes($lastExceptionCodes);
            $addressRisk->setLastUpdated(new DateTimeImmutable());

            $updatedCount++;
        }

        $this->entityManager->flush();

        return $updatedCount;
    }

    /**
     * Normalize an address string: lowercase, trim, collapse whitespace, remove accents.
     */
    public function normalizeAddress(string $address): string
    {
        $address = trim($address);
        $address = mb_strtolower($address, 'UTF-8');

        // Remove accents using transliterator
        if (\function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $address);
            if ($transliterated !== false) {
                $address = $transliterated;
            }
        }

        // Collapse whitespace
        $address = (string) preg_replace('/\s+/', ' ', $address);

        return $address;
    }
}
