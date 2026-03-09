<?php

declare(strict_types=1);

namespace App\Application\Route;

final class RouteNotOwnedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Driver does not own this route.');
    }
}
