<?php

declare(strict_types=1);

namespace App\Domain\Route\Repository;

use App\Domain\Route\Model\RouteSnapshot;
use App\Entity\Route;

interface RouteSnapshotRepositoryInterface
{
    public function findByRoute(Route $route): ?RouteSnapshot;

    /**
     * @param list<Route> $routes
     * @return array<int, RouteSnapshot> Keyed by route ID
     */
    public function findByRoutes(array $routes): array;

    public function save(RouteSnapshot $snapshot): void;
}
