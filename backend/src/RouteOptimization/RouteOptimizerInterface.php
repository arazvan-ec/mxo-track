<?php

declare(strict_types=1);

namespace App\RouteOptimization;

/**
 * Port interface for Vehicle Routing Problem (VRP) optimization.
 *
 * Implementations solve the assignment (which vehicle gets which jobs)
 * and sequencing (optimal stop order) problems.
 */
interface RouteOptimizerInterface
{
    /**
     * Optimizes the assignment and sequencing of jobs across vehicles.
     *
     * @param list<OptimizableVehicle> $vehicles Available vehicles with capacity constraints
     * @param list<OptimizableJob>     $jobs     Delivery jobs to assign and sequence
     */
    public function optimize(array $vehicles, array $jobs): OptimizationResult;
}
