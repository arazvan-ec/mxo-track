<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\DemandPrediction;
use App\Entity\RouteStop;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;

final class DemandPredictionService
{
    private const int LOOKBACK_WEEKS = 4;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function predictNextDay(): DemandPrediction
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');

        return $this->predictForDate($tomorrow);
    }

    /**
     * @return list<DemandPrediction>
     */
    public function predictNextWeek(): array
    {
        $predictions = [];
        $today = new \DateTimeImmutable('today');

        for ($i = 1; $i <= 7; $i++) {
            $date = $today->modify("+{$i} days");
            $predictions[] = $this->predictForDate($date);
        }

        return $predictions;
    }

    private function predictForDate(\DateTimeImmutable $date): DemandPrediction
    {
        // PostgreSQL EXTRACT(DOW FROM ...) returns 0=Sunday, 1=Monday, ..., 6=Saturday
        $dow = (int) $date->format('w'); // PHP 'w' matches PostgreSQL DOW: 0=Sunday..6=Saturday

        $dailyCounts = $this->getDeliveryCountsByDayOfWeek($dow);
        $avgStopsPerRoute = $this->getAvgStopsPerRoute();

        $dataPoints = count($dailyCounts);
        $confidence = match (true) {
            $dataPoints >= 4 => 'high',
            $dataPoints >= 2 => 'medium',
            default => 'low',
        };

        if ($dataPoints === 0) {
            return new DemandPrediction(
                date: $date,
                dayOfWeek: $date->format('l'),
                predictedDeliveries: 0,
                minDeliveries: 0,
                maxDeliveries: 0,
                recommendedVehicles: 0,
                confidence: $confidence,
            );
        }

        $sum = array_sum($dailyCounts);
        $predicted = (int) round($sum / $dataPoints);
        $min = min($dailyCounts);
        $max = max($dailyCounts);

        $recommendedVehicles = $avgStopsPerRoute > 0
            ? (int) ceil($predicted / $avgStopsPerRoute)
            : 0;

        return new DemandPrediction(
            date: $date,
            dayOfWeek: $date->format('l'),
            predictedDeliveries: $predicted,
            minDeliveries: $min,
            maxDeliveries: $max,
            recommendedVehicles: $recommendedVehicles,
            confidence: $confidence,
        );
    }

    /**
     * Get delivery counts for each occurrence of the given day-of-week in the last N weeks.
     *
     * Uses native SQL because Doctrine DQL does not support EXTRACT() or DATE casting.
     *
     * @return list<int> One count per week that had at least one delivery on that day
     */
    private function getDeliveryCountsByDayOfWeek(int $dow): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d weeks', self::LOOKBACK_WEEKS));

        $conn = $this->em->getConnection();
        $sql = <<<'SQL'
            SELECT CAST(rs.delivered_at AS DATE) AS delivery_date, COUNT(rs.id) AS cnt
            FROM route_stop rs
            WHERE rs.status = :delivered
              AND rs.is_origin = false
              AND rs.delivered_at >= :since
              AND EXTRACT(DOW FROM rs.delivered_at) = :dow
            GROUP BY delivery_date
            SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'delivered' => RouteStopStatus::DELIVERED->value,
            'since' => $since->format('Y-m-d H:i:s'),
            'dow' => $dow,
        ]);

        return array_map(static fn(array $row) => (int) $row['cnt'], $rows);
    }

    /**
     * Average number of delivered stops per completed route in the lookback period.
     */
    private function getAvgStopsPerRoute(): float
    {
        $since = new \DateTimeImmutable(sprintf('-%d weeks', self::LOOKBACK_WEEKS));

        $result = $this->em->createQueryBuilder()
            ->select(
                'COUNT(rs.id) as total_stops',
                'COUNT(DISTINCT r.id) as total_routes',
            )
            ->from(RouteStop::class, 'rs')
            ->join('rs.route', 'r')
            ->where('rs.status = :delivered')
            ->andWhere('rs.isOrigin = false')
            ->andWhere('r.status = :done')
            ->andWhere('r.endAt >= :since')
            ->setParameter('delivered', RouteStopStatus::DELIVERED)
            ->setParameter('done', RouteStatus::DONE)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        $totalStops = (int) ($result['total_stops'] ?? 0);
        $totalRoutes = (int) ($result['total_routes'] ?? 0);

        return $totalRoutes > 0 ? $totalStops / $totalRoutes : 0.0;
    }
}
