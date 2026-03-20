<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use App\Enum\VehicleSkill;
use App\Notification\DeliveryRatingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Multi-criteria driver scoring service for intelligent route assignment.
 *
 * Scores drivers based on:
 * - Zone affinity (25%): how well the driver knows the delivery area
 * - Rating (20%): average customer rating from past deliveries
 * - Workload (15%): number of active routes this week (fewer = better)
 * - Skills match (20%): percentage of required vehicle skills the driver's vehicle has
 * - Availability (20%): whether the driver is available on the route date
 */
final class DriverScoringService
{
    /** @var array<string, float> Weight for each scoring criterion (must sum to 1.0) */
    private const array WEIGHTS = [
        'zone' => 0.25,
        'rating' => 0.20,
        'workload' => 0.15,
        'skills' => 0.20,
        'availability' => 0.20,
    ];

    /** Maximum active routes per week before workload score drops to 0. */
    private const int MAX_WEEKLY_ROUTES = 10;

    /** Maximum possible customer rating. */
    private const float MAX_RATING = 5.0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DriverAffinityService $affinityService,
        private readonly DeliveryRatingService $ratingService,
        private readonly DriverAvailabilityService $availabilityService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Score all available drivers for a given route.
     *
     * @return list<array{driver: User, score: float, breakdown: array{zone: float, rating: float, workload: float, skills: float, availability: float}}>
     */
    public function scoreDriversForRoute(Route $route): array
    {
        $drivers = $this->getAvailableDrivers();

        if (\count($drivers) === 0) {
            return [];
        }

        $routeDate = $route->getStartAt() ?? new \DateTimeImmutable('today');
        $routeZoneIds = $this->getRouteZoneIds($route);
        $requiredSkills = $this->getRequiredSkillsForRoute($route);

        $scores = [];
        foreach ($drivers as $driver) {
            $driverId = (int) $driver->getId();

            $zoneScore = $this->calculateZoneScore($driverId, $routeZoneIds);
            $ratingScore = $this->calculateRatingScore($driverId);
            $workloadScore = $this->calculateWorkloadScore($driver);
            $skillsScore = $this->calculateSkillsScore($driver, $requiredSkills);
            $availabilityScore = $this->availabilityService->isDriverAvailable($driver, $routeDate) ? 100.0 : 10.0;

            $totalScore = round(
                $zoneScore * self::WEIGHTS['zone']
                + $ratingScore * self::WEIGHTS['rating']
                + $workloadScore * self::WEIGHTS['workload']
                + $skillsScore * self::WEIGHTS['skills']
                + $availabilityScore * self::WEIGHTS['availability'],
                1,
            );

            $scores[] = [
                'driver' => $driver,
                'score' => $totalScore,
                'breakdown' => [
                    'zone' => round($zoneScore, 1),
                    'rating' => round($ratingScore, 1),
                    'workload' => round($workloadScore, 1),
                    'skills' => round($skillsScore, 1),
                    'availability' => round($availabilityScore, 1),
                ],
            ];
        }

        // Sort by total score descending
        usort($scores, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scores;
    }

    /**
     * Get all active users with ROLE_DRIVER.
     *
     * @return list<User>
     */
    private function getAvailableDrivers(): array
    {
        return $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where("JSON_TEXT(u.roles) LIKE :driverRole")
            ->andWhere('u.isActive = true')
            ->setParameter('driverRole', '%ROLE_DRIVER%')
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Extract zone IDs from route stop coordinates.
     *
     * @return list<int>
     */
    private function getRouteZoneIds(Route $route): array
    {
        // Collect unique zone identifiers from route stop coordinates
        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->andWhere('s.isOrigin = false')
            ->andWhere('s.latitude IS NOT NULL')
            ->andWhere('s.longitude IS NOT NULL')
            ->setParameter('route', $route)
            ->getQuery()
            ->getResult();

        // For zone affinity, we collect the stop coordinates.
        // The DriverAffinityService uses zone IDs from the ML sidecar,
        // but we don't have direct zone-ID mapping here. We return empty
        // and let calculateZoneScore use driver-level affinity scores instead.
        return [];
    }

    /**
     * Calculate zone affinity score (0-100) for a driver on a route.
     *
     * @param list<int> $routeZoneIds
     */
    private function calculateZoneScore(int $driverId, array $routeZoneIds): float
    {
        try {
            $affinities = $this->affinityService->getAffinityScores([$driverId]);

            if (\count($affinities) === 0) {
                // No affinity data: return neutral score
                return 50.0;
            }

            // Use the average affinity score across all zones as a general measure
            $totalScore = 0.0;
            foreach ($affinities as $affinity) {
                $totalScore += $affinity['score'];
            }

            $avgScore = $totalScore / \count($affinities);

            // Affinity score is 0.0-1.0, convert to 0-100
            return min(100.0, $avgScore * 100.0);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to fetch zone affinity for driver {driverId}: {error}', [
                'driverId' => $driverId,
                'error' => $e->getMessage(),
            ]);

            // Return neutral score on failure
            return 50.0;
        }
    }

    /**
     * Calculate rating score (0-100) based on average customer rating.
     */
    private function calculateRatingScore(int $driverId): float
    {
        $avgRating = $this->ratingService->getAverageRatingForDriver($driverId);

        if ($avgRating <= 0.0) {
            // No ratings: return neutral score
            return 50.0;
        }

        // Convert 0-5 rating scale to 0-100
        return min(100.0, ($avgRating / self::MAX_RATING) * 100.0);
    }

    /**
     * Calculate workload score (0-100). Fewer active routes = higher score.
     */
    private function calculateWorkloadScore(User $driver): float
    {
        $weekStart = new \DateTimeImmutable('monday this week 00:00:00');
        $weekEnd = new \DateTimeImmutable('sunday this week 23:59:59');

        $activeRoutes = (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Route::class, 'r')
            ->where('r.driver = :driver')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('(r.startAt >= :weekStart OR r.startAt IS NULL)')
            ->andWhere('(r.startAt <= :weekEnd OR r.startAt IS NULL)')
            ->setParameter('driver', $driver)
            ->setParameter('statuses', [RouteStatus::PLANNED, RouteStatus::ACTIVE])
            ->setParameter('weekStart', $weekStart)
            ->setParameter('weekEnd', $weekEnd)
            ->getQuery()
            ->getSingleScalarResult();

        if ($activeRoutes >= self::MAX_WEEKLY_ROUTES) {
            return 0.0;
        }

        // Linear scale: 0 routes = 100, MAX routes = 0
        return round((1 - $activeRoutes / self::MAX_WEEKLY_ROUTES) * 100.0, 1);
    }

    /**
     * Calculate skills match score (0-100) based on required vs available vehicle skills.
     *
     * Looks at the vehicle most recently assigned to this driver.
     *
     * @param VehicleSkill[] $requiredSkills
     */
    private function calculateSkillsScore(User $driver, array $requiredSkills): float
    {
        if (\count($requiredSkills) === 0) {
            // No skills required: perfect match
            return 100.0;
        }

        // Find the most recent vehicle assigned to a route for this driver
        $vehicle = $this->getDriverVehicle($driver);

        if ($vehicle === null) {
            return 0.0;
        }

        $vehicleSkills = $vehicle->getSkills();
        $vehicleSkillValues = array_map(static fn(VehicleSkill $s) => $s->value, $vehicleSkills);

        $matched = 0;
        foreach ($requiredSkills as $required) {
            if (\in_array($required->value, $vehicleSkillValues, true)) {
                $matched++;
            }
        }

        return round(($matched / \count($requiredSkills)) * 100.0, 1);
    }

    /**
     * Collect all unique required skills from the shipments attached to a route's stops.
     *
     * @return VehicleSkill[]
     */
    private function getRequiredSkillsForRoute(Route $route): array
    {
        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->andWhere('s.isOrigin = false')
            ->andWhere('s.shipment IS NOT NULL')
            ->setParameter('route', $route)
            ->getQuery()
            ->getResult();

        $skills = [];
        foreach ($stops as $stop) {
            $shipment = $stop->getShipment();
            if ($shipment !== null) {
                foreach ($shipment->getRequiredSkills() as $skill) {
                    $skills[$skill->value] = $skill;
                }
            }
        }

        return array_values($skills);
    }

    /**
     * Get the vehicle most recently used by a driver (from their latest route).
     */
    private function getDriverVehicle(User $driver): ?Vehicle
    {
        $result = $this->em->createQueryBuilder()
            ->select('r')
            ->from(Route::class, 'r')
            ->where('r.driver = :driver')
            ->andWhere('r.vehicle IS NOT NULL')
            ->setParameter('driver', $driver)
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result?->getVehicle();
    }
}
