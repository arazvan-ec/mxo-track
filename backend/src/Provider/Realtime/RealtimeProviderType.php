<?php

declare(strict_types=1);

namespace App\Provider\Realtime;

enum RealtimeProviderType: string
{
    case Mercure = 'mercure';
    case HttpPolling = 'http_polling';
}
