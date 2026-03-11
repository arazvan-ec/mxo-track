<?php

declare(strict_types=1);

namespace App\Provider\RouteOptimizer;

enum RouteOptimizerProvider: string
{
    case Vroom = 'vroom';
    case Greedy = 'greedy';
}
