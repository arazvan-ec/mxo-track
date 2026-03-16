<?php

declare(strict_types=1);

namespace App\Domain\Route\Repository;

use App\Domain\Route\Model\Route;
use App\Domain\Route\ValueObject\RouteId;

interface RouteRepositoryInterface
{
    public function findById(RouteId $id): ?Route;

    public function save(Route $route): void;

    public function remove(Route $route): void;
}
