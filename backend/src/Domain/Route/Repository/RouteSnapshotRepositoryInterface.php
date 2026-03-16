<?php

declare(strict_types=1);

namespace App\Domain\Route\Repository;

use App\Domain\Route\Model\RouteSnapshot;
use App\Domain\Route\ValueObject\RouteId;

interface RouteSnapshotRepositoryInterface
{
    public function findByRoute(RouteId $routeId): ?RouteSnapshot;

    public function save(RouteSnapshot $snapshot): void;
}
