<?php

declare(strict_types=1);

namespace App\Domain\Route\Repository;

use App\Domain\Route\Model\RouteEvent;
use App\Domain\Route\ValueObject\RouteId;

interface RouteEventRepositoryInterface
{
    /** @return list<RouteEvent> */
    public function findByRoute(RouteId $routeId): array;

    public function save(RouteEvent $event): void;
}
