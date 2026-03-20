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

    public function save(RouteStop $stop): void;

    public function remove(RouteStop $stop): void;

    public function flush(): void;
}
