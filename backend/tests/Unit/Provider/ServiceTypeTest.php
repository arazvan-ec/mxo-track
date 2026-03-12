<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\ServiceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceType::class)]
final class ServiceTypeTest extends TestCase
{
    #[Test]
    public function it_has_five_cases(): void
    {
        $cases = ServiceType::cases();
        self::assertCount(5, $cases);
    }

    #[Test]
    public function it_has_correct_string_values(): void
    {
        self::assertSame('route_optimizer', ServiceType::RouteOptimizer->value);
        self::assertSame('routing_engine', ServiceType::RoutingEngine->value);
        self::assertSame('gps_provider', ServiceType::GpsProvider->value);
        self::assertSame('realtime_publisher', ServiceType::RealtimePublisher->value);
        self::assertSame('sms_notifier', ServiceType::SmsNotifier->value);
    }
}
