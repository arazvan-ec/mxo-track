<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Route;
use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\Repository\RouteSnapshotRepositoryInterface;
use App\Service\RouteDeviationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteDeviationService::class)]
final class RouteDeviationServiceTest extends TestCase
{
    private RouteDeviationService $service;
    private RouteSnapshotRepositoryInterface $snapshotRepo;

    protected function setUp(): void
    {
        $this->snapshotRepo = $this->createMock(RouteSnapshotRepositoryInterface::class);
        $this->service = new RouteDeviationService($this->snapshotRepo);
    }

    #[Test]
    public function returnsNullWhenNoSnapshot(): void
    {
        $route = $this->createRoute();
        $this->snapshotRepo->method('findByRoute')->willReturn(null);

        $result = $this->service->checkDeviation($route, 40.416, -3.703);

        self::assertNull($result);
    }

    #[Test]
    public function returnsNullWhenNoPolyline(): void
    {
        $route = $this->createRoute();
        $snapshot = $this->createMock(RouteSnapshot::class);
        $snapshot->method('getPolyline')->willReturn(null);
        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        $result = $this->service->checkDeviation($route, 40.416, -3.703);

        self::assertNull($result);
    }

    #[Test]
    public function detectsOnRouteWhenNearPolyline(): void
    {
        $route = $this->createRoute();

        // Polyline encoding for a straight line:
        // (40.416, -3.703) to (40.420, -3.700) — about 500m
        // We'll use Google's encoding of these two points
        $snapshot = $this->createMock(RouteSnapshot::class);
        // Encoded polyline for (40.416, -3.703), (40.420, -3.700)
        $snapshot->method('getPolyline')->willReturn($this->encodePolyline([
            [40.416, -3.703],
            [40.420, -3.700],
        ]));
        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        // Position right on the line
        $result = $this->service->checkDeviation($route, 40.418, -3.7015);

        self::assertNotNull($result);
        self::assertFalse($result->isDeviated);
        self::assertLessThan(500, $result->distanceMeters);
    }

    #[Test]
    public function detectsDeviationWhenFarFromPolyline(): void
    {
        $route = $this->createRoute();

        $snapshot = $this->createMock(RouteSnapshot::class);
        $snapshot->method('getPolyline')->willReturn($this->encodePolyline([
            [40.416, -3.703],
            [40.420, -3.700],
        ]));
        $this->snapshotRepo->method('findByRoute')->willReturn($snapshot);

        // Position ~1km away from the line
        $result = $this->service->checkDeviation($route, 40.416, -3.715);

        self::assertNotNull($result);
        self::assertTrue($result->isDeviated);
        self::assertGreaterThan(500, $result->distanceMeters);
    }

    private function createRoute(): Route
    {
        $route = new Route('Test Route');
        $route->initializePublicId();

        return $route;
    }

    /**
     * Encode points to Google polyline format for testing.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    private function encodePolyline(array $points): string
    {
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as [$lat, $lng]) {
            $latE5 = (int) round($lat * 1e5);
            $lngE5 = (int) round($lng * 1e5);

            $encoded .= $this->encodeValue($latE5 - $prevLat);
            $encoded .= $this->encodeValue($lngE5 - $prevLng);

            $prevLat = $latE5;
            $prevLng = $lngE5;
        }

        return $encoded;
    }

    private function encodeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $encoded = '';

        while ($value >= 0x20) {
            $encoded .= \chr((0x20 | ($value & 0x1F)) + 63);
            $value >>= 5;
        }

        $encoded .= \chr($value + 63);

        return $encoded;
    }
}
