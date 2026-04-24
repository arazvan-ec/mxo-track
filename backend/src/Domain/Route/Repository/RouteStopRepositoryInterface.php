<?php

declare(strict_types=1);

namespace App\Domain\Route\Repository;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;

interface RouteStopRepositoryInterface
{
    public function findOneByPublicId(string $publicId): ?RouteStop;

    /**
     * @return list<RouteStop> Ordered by sequence ASC
     */
    public function findByRoute(Route $route): array;

    /**
     * Count stops per route (total + delivered) for a batch of routes.
     * Used by list endpoints projecting progress indicators.
     *
     * @param list<Route> $routes
     * @return array<string, array{total:int, delivered:int}> keyed by route id
     */
    public function countsByRoutes(array $routes): array;

    /**
     * For each route, return the first PENDING stop projected as a plain array
     * suitable for JSON serialization. Returns null entries via missing keys.
     *
     * @param list<Route> $routes
     * @return array<string, array{sequence:int, address:string, recipientName:?string, windowStart:?string, windowEnd:?string}> keyed by route id
     */
    public function findNextPendingStopsByRoutes(array $routes): array;

    /**
     * For each route, return a 24-element int array counting deliveries binned
     * by hour in the given timezone, restricted to the given day (local date).
     *
     * @param list<Route> $routes
     * @return array<string, list<int>> keyed by route id
     */
    public function findDeliveryHistogramsByRoutes(array $routes, \DateTimeZone $tz, \DateTimeImmutable $day): array;

    public function save(RouteStop $stop): void;

    public function remove(RouteStop $stop): void;

    public function flush(): void;
}
