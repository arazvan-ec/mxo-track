<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\Gps\GpsProviderType;
use App\Provider\Realtime\RealtimeProviderType;
use App\Provider\RouteOptimizer\RouteOptimizerProvider;
use App\Provider\Routing\RoutingProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProviderEnumsTest extends TestCase
{
    #[Test]
    public function route_optimizer_provider_has_correct_cases(): void
    {
        self::assertSame('vroom', RouteOptimizerProvider::Vroom->value);
        self::assertSame('greedy', RouteOptimizerProvider::Greedy->value);
        self::assertCount(2, RouteOptimizerProvider::cases());
    }

    #[Test]
    public function routing_provider_has_correct_cases(): void
    {
        self::assertSame('osrm', RoutingProvider::Osrm->value);
        self::assertSame('google_directions', RoutingProvider::GoogleDirections->value);
        self::assertCount(2, RoutingProvider::cases());
    }

    #[Test]
    public function gps_provider_type_has_correct_cases(): void
    {
        self::assertSame('traccar', GpsProviderType::Traccar->value);
        self::assertSame('webhook', GpsProviderType::Webhook->value);
        self::assertCount(2, GpsProviderType::cases());
    }

    #[Test]
    public function realtime_provider_type_has_correct_cases(): void
    {
        self::assertSame('mercure', RealtimeProviderType::Mercure->value);
        self::assertSame('http_polling', RealtimeProviderType::HttpPolling->value);
        self::assertCount(2, RealtimeProviderType::cases());
    }
}
