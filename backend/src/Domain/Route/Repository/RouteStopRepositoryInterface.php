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
     * @param list<Route> $routes
     * @return array<string, list<RouteStop>> Keyed by route ID, ordered by sequence ASC
     */
    public function findByRoutes(array $routes): array;

    public function save(RouteStop $stop): void;

    public function remove(RouteStop $stop): void;

    public function flush(): void;
}
