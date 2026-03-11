<?php

declare(strict_types=1);

namespace App\Provider\Routing;

enum RoutingProvider: string
{
    case Osrm = 'osrm';
    case Haversine = 'haversine';
    case GoogleDirections = 'google_directions';
}
