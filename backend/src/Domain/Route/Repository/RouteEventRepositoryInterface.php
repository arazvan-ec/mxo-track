<?php

declare(strict_types=1);

namespace App\Domain\Route\Repository;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteEvent;

interface RouteEventRepositoryInterface
{
    /**
     * @return list<RouteEvent> Ordered by occurredAt ASC (for event replay)
     */
    public function findByRoute(Route $route): array;

    public function save(RouteEvent $event): void;

    public function flush(): void;
}
