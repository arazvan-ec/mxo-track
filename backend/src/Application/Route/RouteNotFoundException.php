<?php

declare(strict_types=1);

namespace App\Application\Route;

final class RouteNotFoundException extends \RuntimeException
{
    public function __construct(string $routePublicId)
    {
        parent::__construct(sprintf('Route "%s" not found.', $routePublicId));
    }
}
