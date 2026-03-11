<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\RouteOptimizer;

use App\Provider\RouteOptimizer\GreedyOptimizer;
use App\RouteOptimization\OptimizableJob;
use App\RouteOptimization\OptimizableVehicle;
use App\RouteOptimization\OptimizationResult;
use App\RouteOptimization\RouteOptimizerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GreedyOptimizer::class)]
final class GreedyOptimizerTest extends TestCase
{
    #[Test]
    public function it_implements_interface(): void
    {
        self::assertInstanceOf(RouteOptimizerInterface::class, new GreedyOptimizer());
    }

    #[Test]
    public function single_vehicle_three_jobs_returns_one_route(): void
    {
        $vehicle = new OptimizableVehicle(id: 'v1', startLatitude: 40.4168, startLongitude: -3.7038);
        $jobs = [
            new OptimizableJob(id: 'j1', latitude: 40.4065, longitude: -3.6933), // Atocha
            new OptimizableJob(id: 'j2', latitude: 40.4232, longitude: -3.7095), // Gran Via
            new OptimizableJob(id: 'j3', latitude: 40.4155, longitude: -3.7074), // Puerta del Sol area
        ];

        $result = (new GreedyOptimizer())->optimize([$vehicle], $jobs);

        self::assertInstanceOf(OptimizationResult::class, $result);
        self::assertCount(1, $result->routes);
        self::assertSame('v1', $result->routes[0]->vehicleId);

        // All 3 jobs should be assigned as steps of type 'job'
        $jobSteps = array_filter($result->routes[0]->steps, fn($s) => $s->type === 'job');
        self::assertCount(3, $jobSteps);
        self::assertSame([], $result->unassignedJobIds);
    }

    #[Test]
    public function respects_weight_capacity(): void
    {
        $vehicle = new OptimizableVehicle(id: 'v1', maxWeightKg: 10.0);
        $jobs = [
            new OptimizableJob(id: 'j1', latitude: 40.40, longitude: -3.70, weightKg: 5.0),
            new OptimizableJob(id: 'j2', latitude: 40.41, longitude: -3.71, weightKg: 5.0),
            new OptimizableJob(id: 'j3', latitude: 40.42, longitude: -3.72, weightKg: 5.0), // exceeds
        ];

        $result = (new GreedyOptimizer())->optimize([$vehicle], $jobs);

        // 2 jobs should fit (10kg total), 1 should be unassigned
        $jobSteps = array_filter($result->routes[0]->steps, fn($s) => $s->type === 'job');
        self::assertCount(2, $jobSteps);
        self::assertCount(1, $result->unassignedJobIds);
    }

    #[Test]
    public function respects_volume_capacity(): void
    {
        $vehicle = new OptimizableVehicle(id: 'v1', maxVolumeM3: 1.0);
        $jobs = [
            new OptimizableJob(id: 'j1', latitude: 40.40, longitude: -3.70, volumeM3: 0.6),
            new OptimizableJob(id: 'j2', latitude: 40.41, longitude: -3.71, volumeM3: 0.6), // exceeds
        ];

        $result = (new GreedyOptimizer())->optimize([$vehicle], $jobs);

        $jobSteps = array_filter($result->routes[0]->steps, fn($s) => $s->type === 'job');
        self::assertCount(1, $jobSteps);
        self::assertCount(1, $result->unassignedJobIds);
    }

    #[Test]
    public function two_vehicles_distribute_jobs(): void
    {
        $vehicles = [
            new OptimizableVehicle(id: 'v1', maxWeightKg: 5.0, startLatitude: 40.40, startLongitude: -3.70),
            new OptimizableVehicle(id: 'v2', maxWeightKg: 5.0, startLatitude: 40.42, startLongitude: -3.72),
        ];
        $jobs = [
            new OptimizableJob(id: 'j1', latitude: 40.40, longitude: -3.70, weightKg: 3.0),
            new OptimizableJob(id: 'j2', latitude: 40.41, longitude: -3.71, weightKg: 3.0),
            new OptimizableJob(id: 'j3', latitude: 40.42, longitude: -3.72, weightKg: 3.0),
            new OptimizableJob(id: 'j4', latitude: 40.43, longitude: -3.73, weightKg: 3.0),
        ];

        $result = (new GreedyOptimizer())->optimize($vehicles, $jobs);

        // Total weight 12kg, each vehicle 5kg -> at most 1 job each (3kg) leaves 2kg remaining.
        // Can't fit a second (3kg > 2kg). So 2 jobs assigned, 2 unassigned.
        $totalAssigned = 0;
        foreach ($result->routes as $route) {
            $totalAssigned += count(array_filter($route->steps, fn($s) => $s->type === 'job'));
        }
        self::assertSame(4, $totalAssigned + count($result->unassignedJobIds));
    }

    #[Test]
    public function no_jobs_returns_empty(): void
    {
        $vehicle = new OptimizableVehicle(id: 'v1');
        $result = (new GreedyOptimizer())->optimize([$vehicle], []);

        self::assertSame([], $result->routes);
        self::assertSame([], $result->unassignedJobIds);
    }

    #[Test]
    public function no_vehicles_all_unassigned(): void
    {
        $jobs = [
            new OptimizableJob(id: 'j1', latitude: 40.40, longitude: -3.70),
        ];

        $result = (new GreedyOptimizer())->optimize([], $jobs);

        self::assertSame([], $result->routes);
        self::assertSame(['j1'], $result->unassignedJobIds);
    }
}
