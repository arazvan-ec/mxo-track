<?php

declare(strict_types=1);

namespace App\RouteOptimization;

use Psr\Log\LoggerInterface;

/**
 * Null adapter for the RouteOptimizer port.
 * Returns all jobs as unassigned. Useful for testing.
 */
final class NullRouteOptimizer implements RouteOptimizerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function optimize(array $vehicles, array $jobs): OptimizationResult
    {
        $this->logger->debug('NullRouteOptimizer::optimize called.', [
            'vehicleCount' => \count($vehicles),
            'jobCount' => \count($jobs),
        ]);

        return new OptimizationResult(
            routes: [],
            unassignedJobIds: array_map(static fn(OptimizableJob $j) => $j->id, $jobs),
        );
    }
}
