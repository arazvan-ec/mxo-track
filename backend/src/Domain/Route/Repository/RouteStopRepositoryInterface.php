<?php

declare(strict_types=1);

namespace App\Domain\Route\Repository;

use App\Domain\Route\Model\RouteStop;
use App\Domain\Route\ValueObject\RouteId;
use App\Domain\Route\ValueObject\StopId;

interface RouteStopRepositoryInterface
{
    public function findById(StopId $id): ?RouteStop;

    /** @return list<RouteStop> */
    public function findByRoute(RouteId $routeId): array;

    public function save(RouteStop $stop): void;

    /** @param list<RouteStop> $stops */
    public function saveAll(array $stops): void;

    public function remove(RouteStop $stop): void;

    public function nextSequence(RouteId $routeId): int;
}
