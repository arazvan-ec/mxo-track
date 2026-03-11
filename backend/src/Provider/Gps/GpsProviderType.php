<?php

declare(strict_types=1);

namespace App\Provider\Gps;

enum GpsProviderType: string
{
    case Traccar = 'traccar';
    case Webhook = 'webhook';
}
