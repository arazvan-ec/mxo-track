<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteOptimizationLog;
use App\Entity\RoutePerformanceMetric;
use App\Entity\RouteSnapshot;
use App\Entity\RouteStop;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Builds a RoutePerformanceMetric from a completed route's data.
 *
 * Gathers stats from Route, RouteSnapshot, RouteOptimizationLog, and RouteStops
 * to produce an immutable analytical record.
 */
final class RoutePerformanceMetricFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createFromRoute(Route $route): ?RoutePerformanceMetric
    {
        $customer = $route->getCustomer();
        if ($customer === null) {
            return null;
        }

        $stops = $this->entityManager->getRepository(RouteStop::class)->findBy(
            ['route' => $route],
            ['sequence' => 'ASC'],
        );

        // Count stop statuses (excluding origin)
        $totalStops = 0;
        $deliveredCount = 0;
        $exceptionCount = 0;
        $skippedCount = 0;

        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                continue;
            }
            $totalStops++;

            match ($stop->getStatus()) {
                RouteStopStatus::DELIVERED => $deliveredCount++,
                RouteStopStatus::EXCEPTION => $exceptionCount++,
                RouteStopStatus::SKIPPED => $skippedCount++,
                RouteStopStatus::PENDING => null,
            };
        }

        // Get optimizer info from the most recent optimization log
        $optimizerUsed = 'unknown';
        $optimizationLog = $this->entityManager->getRepository(RouteOptimizationLog::class)
            ->findOneBy(['route' => $route], ['createdAt' => 'DESC']);
        if ($optimizationLog !== null) {
            $optimizerUsed = $optimizationLog->getOptimizerUsed();
        }

        // Get snapshot for before/after distance data
        $snapshot = $this->entityManager->getRepository(RouteSnapshot::class)
            ->findOneBy(['route' => $route]);

        // Calculate metrics
        $plannedDistanceKm = $route->getTotalDistanceKm() !== null
            ? (string) $route->getTotalDistanceKm()
            : null;

        $plannedDurationMinutes = $route->getEstimatedDurationMinutes();

        // Actual duration from route start/end times
        $actualDurationMinutes = null;
        if ($route->getStartAt() !== null && $route->getEndAt() !== null) {
            $diff = $route->getEndAt()->getTimestamp() - $route->getStartAt()->getTimestamp();
            $actualDurationMinutes = (int) round($diff / 60);
        }

        // Actual distance from snapshot (if tracked)
        $actualDistanceKm = null;

        // Km saved from snapshot optimization data
        $kmSaved = null;
        if ($snapshot !== null && $snapshot->getDistanceBeforeKm() !== null && $snapshot->getDistanceAfterKm() !== null) {
            $kmSaved = (string) round($snapshot->getDistanceBeforeKm() - $snapshot->getDistanceAfterKm(), 2);
        }

        // Time saved
        $timeSavedMinutes = null;
        if ($plannedDurationMinutes !== null && $actualDurationMinutes !== null) {
            $timeSavedMinutes = $plannedDurationMinutes - $actualDurationMinutes;
        }

        // Delivery success rate
        $deliverySuccessRate = $totalStops > 0
            ? (string) round(($deliveredCount / $totalStops) * 100, 1)
            : null;

        // Plan accuracy (how close actual duration was to planned)
        $planAccuracyPercent = null;
        if ($plannedDurationMinutes !== null && $plannedDurationMinutes > 0 && $actualDurationMinutes !== null) {
            $planAccuracyPercent = (string) round(
                (1 - abs($actualDurationMinutes - $plannedDurationMinutes) / $plannedDurationMinutes) * 100,
                1,
            );
        }

        return new RoutePerformanceMetric(
            route: $route,
            customer: $customer,
            optimizerUsed: $optimizerUsed,
            totalStops: $totalStops,
            deliveredCount: $deliveredCount,
            exceptionCount: $exceptionCount,
            skippedCount: $skippedCount,
            plannedDistanceKm: $plannedDistanceKm,
            plannedDurationMinutes: $plannedDurationMinutes,
            actualDistanceKm: $actualDistanceKm,
            actualDurationMinutes: $actualDurationMinutes,
            deliverySuccessRate: $deliverySuccessRate,
            kmSaved: $kmSaved,
            timeSavedMinutes: $timeSavedMinutes,
            planAccuracyPercent: $planAccuracyPercent,
        );
    }
}
