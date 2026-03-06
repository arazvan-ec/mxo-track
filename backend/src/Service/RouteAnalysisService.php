<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\RouteAnalysisResult;
use App\Dto\StopAnalysis;
use App\Entity\Route as RouteEntity;
use App\Entity\RouteStop;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;

final class RouteAnalysisService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function analyzeRouteExecution(RouteEntity $route): RouteAnalysisResult
    {
        if ($route->getStatus() !== RouteStatus::DONE) {
            throw new \RuntimeException('Route must be in DONE status to analyze execution.');
        }

        // Load stops ordered by planned sequence, excluding origin
        /** @var list<RouteStop> $stops */
        $stops = $this->em->getRepository(RouteStop::class)->findBy(
            ['route' => $route],
            ['sequence' => 'ASC'],
        );

        $deliveryStops = array_values(array_filter($stops, static fn (RouteStop $s) => !$s->isOrigin()));

        // Determine actual delivery order by sorting delivered stops by deliveredAt
        $deliveredStops = array_filter($deliveryStops, static fn (RouteStop $s) => $s->getDeliveredAt() !== null);
        $deliveredByTime = array_values($deliveredStops);
        usort($deliveredByTime, static fn (RouteStop $a, RouteStop $b) => $a->getDeliveredAt() <=> $b->getDeliveredAt());

        // Build actual order map: stop internal id => 1-based actual order
        $actualOrderMap = [];
        foreach ($deliveredByTime as $index => $stop) {
            $actualOrderMap[spl_object_id($stop)] = $index + 1;
        }

        // Calculate service times between consecutive deliveries
        $serviceTimeMap = [];
        for ($i = 1, $count = \count($deliveredByTime); $i < $count; $i++) {
            $prev = $deliveredByTime[$i - 1]->getDeliveredAt();
            $curr = $deliveredByTime[$i]->getDeliveredAt();
            $serviceTimeMap[spl_object_id($deliveredByTime[$i])] = (float) ($curr->getTimestamp() - $prev->getTimestamp());
        }

        // Build stop analyses
        $inPlannedOrder = 0;
        $totalDelivered = 0;
        $totalServiceTime = 0.0;
        $serviceTimeCount = 0;
        $exceptionCount = 0;
        $stopAnalyses = [];
        $maxServiceTime = 0.0;
        $problematicAddresses = [];

        foreach ($deliveryStops as $index => $stop) {
            $plannedSeq = $index + 1;
            $actualOrder = $actualOrderMap[spl_object_id($stop)] ?? null;
            $sequenceDeviation = $actualOrder !== null ? ($actualOrder - $plannedSeq) : null;

            $serviceTime = $serviceTimeMap[spl_object_id($stop)] ?? null;
            if ($serviceTime !== null) {
                $totalServiceTime += $serviceTime;
                $serviceTimeCount++;
                if ($serviceTime > $maxServiceTime) {
                    $maxServiceTime = $serviceTime;
                }
                if ($serviceTime > 600.0) {
                    $problematicAddresses[] = $stop->getAddress();
                }
            }

            if ($actualOrder !== null) {
                $totalDelivered++;
                if ($actualOrder === $plannedSeq) {
                    $inPlannedOrder++;
                }
            }

            if ($stop->getStatus() === RouteStopStatus::EXCEPTION) {
                $exceptionCount++;
            }

            $stopAnalyses[] = new StopAnalysis(
                plannedSequence: $plannedSeq,
                actualOrder: $actualOrder,
                address: $stop->getAddress(),
                status: $stop->getStatus()->value,
                deliveredAt: $stop->getDeliveredAt()?->format(\DATE_ATOM),
                actualServiceTimeSeconds: $serviceTime,
                sequenceDeviation: $sequenceDeviation,
                exceptionCode: $stop->getExceptionCode()?->value,
                exceptionNotes: $stop->getExceptionNotes(),
            );
        }

        // Route-level metrics
        $actualDurationMinutes = null;
        if ($route->getStartAt() !== null && $route->getEndAt() !== null) {
            $actualDurationMinutes = (int) round(
                ($route->getEndAt()->getTimestamp() - $route->getStartAt()->getTimestamp()) / 60,
            );
        }

        $sequenceAdherence = $totalDelivered > 0
            ? round(($inPlannedOrder / $totalDelivered) * 100, 1)
            : 0.0;

        $avgServiceTime = $serviceTimeCount > 0
            ? round($totalServiceTime / $serviceTimeCount, 1)
            : null;

        // Recommendations
        $recommendations = [];
        $totalStops = \count($deliveryStops);

        if ($avgServiceTime !== null && $avgServiceTime > 360.0) {
            $recommendations[] = sprintf(
                'Average service time (%.0fs) exceeds 6 minutes. Consider adjusting optimizer service time parameter.',
                $avgServiceTime,
            );
        }

        if ($sequenceAdherence < 70.0) {
            $recommendations[] = sprintf(
                'Sequence adherence is low (%.1f%%). Consider reviewing time windows or driver routing constraints.',
                $sequenceAdherence,
            );
        }

        foreach ($problematicAddresses as $address) {
            $recommendations[] = sprintf(
                'Stop at "%s" had service time exceeding 10 minutes. Investigate possible access or delivery issues.',
                $address,
            );
        }

        if ($totalStops > 0 && ($exceptionCount / $totalStops) > 0.2) {
            $recommendations[] = sprintf(
                'High exception rate (%.0f%%). Review shipment data quality and customer availability.',
                ($exceptionCount / $totalStops) * 100,
            );
        }

        return new RouteAnalysisResult(
            routePublicId: (string) $route->getPublicId(),
            routeName: $route->getName(),
            vehicleName: $route->getVehicle()?->getName(),
            driverName: $route->getDriver()?->getName(),
            actualDurationMinutes: $actualDurationMinutes,
            sequenceAdherence: $sequenceAdherence,
            avgActualServiceTimeSeconds: $avgServiceTime,
            stops: $stopAnalyses,
            recommendations: $recommendations,
        );
    }
}
