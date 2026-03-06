<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\WindowViolation;
use App\Entity\Route;
use App\Entity\RouteStop;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Detects delivery window violations by comparing estimated arrival times
 * against the configured delivery windows on route stops.
 */
final class WindowViolationDetector
{
    private const float DEFAULT_AVG_SPEED_KMH = 30.0;
    private const int SERVICE_TIME_SECONDS = 120; // 2 minutes per stop
    private const int EARLY_THRESHOLD_SECONDS = 1800; // 30 minutes

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RouteOptimizationService $optimizationService,
    ) {}

    /**
     * Detects window violations for all stops on a route.
     *
     * Uses cumulative travel time from route start time (or now if not set)
     * plus haversine-based duration estimates between consecutive stops.
     *
     * @return list<WindowViolation>
     */
    public function detectViolations(Route $route): array
    {
        $stops = $this->getOrderedStops($route);

        if (\count($stops) === 0) {
            return [];
        }

        $startTime = $route->getStartAt() ?? new DateTimeImmutable();
        $violations = [];
        $accumulatedSeconds = 0;
        $previousStop = null;

        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                $previousStop = $stop;
                continue;
            }

            // Calculate travel time from previous stop
            if ($previousStop !== null) {
                $travelSeconds = $this->estimateTravelSeconds($previousStop, $stop);
                $accumulatedSeconds += $travelSeconds;
            }

            $eta = $startTime->modify('+' . $accumulatedSeconds . ' seconds');

            // Check for window violations
            $violation = $this->checkWindow($stop, $eta);
            if ($violation !== null) {
                $violations[] = $violation;
            }

            // Add service time for this stop
            $accumulatedSeconds += self::SERVICE_TIME_SECONDS;
            $previousStop = $stop;
        }

        return $violations;
    }

    private function checkWindow(RouteStop $stop, DateTimeImmutable $eta): ?WindowViolation
    {
        $windowStart = $stop->getDeliveryWindowStart();
        $windowEnd = $stop->getDeliveryWindowEnd();

        // No window configured — nothing to check
        if ($windowStart === null && $windowEnd === null) {
            return null;
        }

        // Extract time-of-day from the ETA to compare with time-only window fields.
        // DeliveryWindow fields are stored as time_immutable (date portion is 1970-01-01).
        $etaTimeOnly = DateTimeImmutable::createFromFormat('H:i:s', $eta->format('H:i:s'));
        if ($etaTimeOnly === false) {
            return null;
        }

        // Check LATE: ETA is after window end
        if ($windowEnd !== null) {
            $endTimeOnly = DateTimeImmutable::createFromFormat('H:i:s', $windowEnd->format('H:i:s'));
            if ($endTimeOnly !== false && $etaTimeOnly > $endTimeOnly) {
                $lateMinutes = (int) ceil(($etaTimeOnly->getTimestamp() - $endTimeOnly->getTimestamp()) / 60);

                return new WindowViolation(
                    stopSequence: $stop->getSequence(),
                    stopAddress: $stop->getAddress(),
                    windowStart: $windowStart,
                    windowEnd: $windowEnd,
                    estimatedArrival: $eta,
                    type: 'LATE',
                    message: sprintf(
                        'Estimated arrival at %s is %d min after delivery window closes at %s.',
                        $eta->format('H:i'),
                        $lateMinutes,
                        $windowEnd->format('H:i'),
                    ),
                );
            }
        }

        // Check EARLY: ETA is more than 30 min before window start
        if ($windowStart !== null) {
            $startTimeOnly = DateTimeImmutable::createFromFormat('H:i:s', $windowStart->format('H:i:s'));
            if ($startTimeOnly !== false) {
                $earlyThreshold = $startTimeOnly->modify('-' . self::EARLY_THRESHOLD_SECONDS . ' seconds');
                if ($etaTimeOnly < $earlyThreshold) {
                    $earlyMinutes = (int) ceil(($startTimeOnly->getTimestamp() - $etaTimeOnly->getTimestamp()) / 60);

                    return new WindowViolation(
                        stopSequence: $stop->getSequence(),
                        stopAddress: $stop->getAddress(),
                        windowStart: $windowStart,
                        windowEnd: $windowEnd,
                        estimatedArrival: $eta,
                        type: 'EARLY',
                        message: sprintf(
                            'Estimated arrival at %s is %d min before delivery window opens at %s.',
                            $eta->format('H:i'),
                            $earlyMinutes,
                            $windowStart->format('H:i'),
                        ),
                    );
                }
            }
        }

        return null;
    }

    /**
     * Estimates travel time in seconds between two stops using haversine distance.
     */
    private function estimateTravelSeconds(RouteStop $from, RouteStop $to): int
    {
        if ($from->getLatitude() === null || $from->getLongitude() === null
            || $to->getLatitude() === null || $to->getLongitude() === null) {
            return 0;
        }

        $distanceKm = $this->optimizationService->calculateDistance(
            $from->getLatitude(),
            $from->getLongitude(),
            $to->getLatitude(),
            $to->getLongitude(),
        );

        if (self::DEFAULT_AVG_SPEED_KMH <= 0.0) {
            return 0;
        }

        return (int) ceil(($distanceKm / self::DEFAULT_AVG_SPEED_KMH) * 3600);
    }

    /**
     * @return list<RouteStop>
     */
    private function getOrderedStops(Route $route): array
    {
        /** @var list<RouteStop> */
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
