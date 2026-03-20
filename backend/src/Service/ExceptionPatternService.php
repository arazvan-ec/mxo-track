<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Model\RouteStop;
use App\Entity\ShipmentEvent;
use App\Enum\RouteStopStatus;
use App\Enum\ShipmentEventType;
use Doctrine\ORM\EntityManagerInterface;

final class ExceptionPatternService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Obtiene patrones de excepciones clasificadas por IA, agregados por subcategoria.
     *
     * @return array<int, array{subcategory: string, count: int, percentage: float}>
     */
    public function getPatterns(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('se.payload')
            ->from(ShipmentEvent::class, 'se')
            ->where('se.eventType = :eventType')
            ->setParameter('eventType', ShipmentEventType::EXCEPTION);

        if ($from !== null) {
            $qb->andWhere('se.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('se.createdAt <= :to')
                ->setParameter('to', $to);
        }

        /** @var array<int, array{payload: array}> $results */
        $results = $qb->getQuery()->getResult();

        $subcategoryCounts = [];
        $total = 0;

        foreach ($results as $result) {
            $payload = $result['payload'];

            if (!isset($payload['ai_classification']['subcategory'])) {
                continue;
            }

            $subcategory = $payload['ai_classification']['subcategory'];
            $subcategoryCounts[$subcategory] = ($subcategoryCounts[$subcategory] ?? 0) + 1;
            $total++;
        }

        if ($total === 0) {
            return [];
        }

        arsort($subcategoryCounts);

        $patterns = [];
        foreach ($subcategoryCounts as $subcategory => $count) {
            $patterns[] = [
                'subcategory' => $subcategory,
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        }

        return $patterns;
    }

    /**
     * Obtiene las subcategorias mas frecuentes.
     *
     * @return array<int, array{subcategory: string, count: int, percentage: float}>
     */
    public function getTopSubcategories(int $limit = 5): array
    {
        $patterns = $this->getPatterns();

        return array_slice($patterns, 0, $limit);
    }

    /**
     * Analyze exception patterns within a date range (route-stop based analysis for AI assistant).
     *
     * @return array{
     *     total_exceptions: int,
     *     by_exception_code: list<array{code: string, count: int}>,
     *     by_driver: list<array{driver_name: string, driver_email: string, exceptions: int}>,
     *     by_time_of_day: list<array{hour_range: string, count: int}>,
     *     top_addresses: list<array{address: string, count: int}>
     * }
     */
    public function analyzePatterns(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
    ): array {
        // Total exceptions
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(rs.id)')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :exception')
            ->andWhere('rs.isOrigin = false')
            ->setParameter('exception', RouteStopStatus::EXCEPTION);

        if ($from !== null) {
            $qb->andWhere('r.startAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('r.startAt <= :to')->setParameter('to', $to);
        }

        $totalExceptions = (int) $qb->getQuery()->getSingleScalarResult();

        // By exception code
        $codeQb = $this->em->createQueryBuilder()
            ->select('rs.exceptionCode as code', 'COUNT(rs.id) as cnt')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :exception')
            ->andWhere('rs.isOrigin = false')
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->groupBy('rs.exceptionCode')
            ->orderBy('cnt', 'DESC');

        if ($from !== null) {
            $codeQb->andWhere('r.startAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $codeQb->andWhere('r.startAt <= :to')->setParameter('to', $to);
        }

        $byCode = array_map(fn(array $row) => [
            'code' => (string) ($row['code'] ?? 'sin_codigo'),
            'count' => (int) $row['cnt'],
        ], $codeQb->getQuery()->getResult());

        // By driver
        $driverQb = $this->em->createQueryBuilder()
            ->select('COALESCE(d.name, d.email) as driver_name', 'd.email as driver_email', 'COUNT(rs.id) as exceptions')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->join('r.driver', 'd')
            ->where('rs.status = :exception')
            ->andWhere('rs.isOrigin = false')
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->groupBy('d.id, d.name, d.email')
            ->orderBy('exceptions', 'DESC')
            ->setMaxResults(10);

        if ($from !== null) {
            $driverQb->andWhere('r.startAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $driverQb->andWhere('r.startAt <= :to')->setParameter('to', $to);
        }

        $byDriver = array_map(fn(array $row) => [
            'driver_name' => (string) $row['driver_name'],
            'driver_email' => (string) $row['driver_email'],
            'exceptions' => (int) $row['exceptions'],
        ], $driverQb->getQuery()->getResult());

        // Top addresses with exceptions
        $addrQb = $this->em->createQueryBuilder()
            ->select('rs.address', 'COUNT(rs.id) as cnt')
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :exception')
            ->andWhere('rs.isOrigin = false')
            ->setParameter('exception', RouteStopStatus::EXCEPTION)
            ->groupBy('rs.address')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(10);

        if ($from !== null) {
            $addrQb->andWhere('r.startAt >= :from')->setParameter('from', $from);
        }
        if ($to !== null) {
            $addrQb->andWhere('r.startAt <= :to')->setParameter('to', $to);
        }

        $topAddresses = array_map(fn(array $row) => [
            'address' => (string) $row['address'],
            'count' => (int) $row['cnt'],
        ], $addrQb->getQuery()->getResult());

        return [
            'total_exceptions' => $totalExceptions,
            'by_exception_code' => $byCode,
            'by_driver' => $byDriver,
            'by_time_of_day' => [], // Simplified for MVP
            'top_addresses' => $topAddresses,
        ];
    }
}
