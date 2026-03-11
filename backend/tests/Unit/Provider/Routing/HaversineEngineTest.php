<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Routing;

use App\Provider\Routing\HaversineConfig;
use App\Provider\Routing\HaversineEngine;
use App\Routing\Coordinate;
use App\Routing\MultiWaypointRouteResult;
use App\Routing\RouteResult;
use App\Routing\RoutingEngineInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HaversineEngine::class)]
final class HaversineEngineTest extends TestCase
{
    #[Test]
    public function it_implements_routing_engine_interface(): void
    {
        $engine = new HaversineEngine(new HaversineConfig());
        self::assertInstanceOf(RoutingEngineInterface::class, $engine);
    }

    #[Test]
    public function route_calculates_haversine_with_correction_factor(): void
    {
        // Madrid Sol (40.4168, -3.7038) -> Atocha (40.4065, -3.6933)
        // Haversine ~1.33 km, with factor 1.3 -> ~1.73 km
        $engine = new HaversineEngine(new HaversineConfig(correctionFactor: 1.3, averageSpeedKmh: 30.0));

        $result = $engine->route(40.4168, -3.7038, 40.4065, -3.6933);

        self::assertInstanceOf(RouteResult::class, $result);
        // Haversine distance between these points is ~1.45km
        // With 1.3 correction: ~1.88km
        self::assertEqualsWithDelta(1.88, $result->distanceKm, 0.15);
        // Duration at 30 km/h: 1.88 km / 30 * 3600 ~ 226 seconds
        self::assertGreaterThan(150, $result->durationSeconds);
        self::assertLessThan(300, $result->durationSeconds);
    }

    #[Test]
    public function route_returns_zero_for_same_point(): void
    {
        $engine = new HaversineEngine(new HaversineConfig());
        $result = $engine->route(40.4168, -3.7038, 40.4168, -3.7038);

        self::assertEqualsWithDelta(0.0, $result->distanceKm, 0.001);
        self::assertEqualsWithDelta(0.0, $result->durationSeconds, 0.001);
    }

    #[Test]
    public function route_with_waypoints_returns_legs(): void
    {
        $engine = new HaversineEngine(new HaversineConfig(correctionFactor: 1.3, averageSpeedKmh: 30.0));

        $waypoints = [
            new Coordinate(40.4168, -3.7038), // Sol
            new Coordinate(40.4065, -3.6933), // Atocha
            new Coordinate(40.4232, -3.7095), // Gran Via
        ];

        $result = $engine->routeWithWaypoints($waypoints);

        self::assertInstanceOf(MultiWaypointRouteResult::class, $result);
        self::assertCount(2, $result->legs);
        self::assertGreaterThan(0, $result->totalDistanceKm);
        self::assertGreaterThan(0, $result->totalDurationSeconds);

        // Total should be sum of legs
        $legDistanceSum = array_sum(array_map(fn ($l) => $l->distanceKm, $result->legs));
        self::assertEqualsWithDelta($result->totalDistanceKm, $legDistanceSum, 0.001);
    }

    #[Test]
    public function route_with_waypoints_returns_empty_for_less_than_two_points(): void
    {
        $engine = new HaversineEngine(new HaversineConfig());
        $result = $engine->routeWithWaypoints([new Coordinate(40.4168, -3.7038)]);

        self::assertEqualsWithDelta(0.0, $result->totalDistanceKm, 0.001);
        self::assertSame([], $result->legs);
    }

    #[Test]
    public function correction_factor_is_configurable(): void
    {
        $engine1 = new HaversineEngine(new HaversineConfig(correctionFactor: 1.0));
        $engine2 = new HaversineEngine(new HaversineConfig(correctionFactor: 2.0));

        $r1 = $engine1->route(40.4168, -3.7038, 40.4065, -3.6933);
        $r2 = $engine2->route(40.4168, -3.7038, 40.4065, -3.6933);

        self::assertEqualsWithDelta($r1->distanceKm * 2.0, $r2->distanceKm, 0.01);
    }
}
