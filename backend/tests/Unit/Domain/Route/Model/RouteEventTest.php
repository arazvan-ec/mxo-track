<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Route\Model;

use App\Domain\Route\Model\RouteEvent;
use App\Domain\Route\ValueObject\RouteId;
use App\Enum\RouteEventType;
use PHPUnit\Framework\TestCase;

final class RouteEventTest extends TestCase
{
    public function testCreateEvent(): void
    {
        $routeId = new RouteId('01J0000000000000000000TEST');

        $event = new RouteEvent(
            routeId: $routeId,
            eventType: RouteEventType::CREATED,
            actorType: 'user',
            actorUserId: 42,
            payload: ['name' => 'Morning route'],
        );

        self::assertSame($routeId, $event->routeId());
        self::assertSame(RouteEventType::CREATED, $event->eventType());
        self::assertSame('user', $event->actorType());
        self::assertSame(42, $event->actorUserId());
        self::assertSame(['name' => 'Morning route'], $event->payload());
        self::assertNull($event->snapshotMetrics());
        self::assertInstanceOf(\DateTimeImmutable::class, $event->createdAt());
    }

    public function testOccurredAtFallsBackToCreatedAt(): void
    {
        $event = new RouteEvent(
            routeId: new RouteId('01J0000000000000000000TEST'),
            eventType: RouteEventType::CREATED,
            actorType: 'system',
        );

        self::assertSame($event->createdAt(), $event->occurredAt());
    }

    public function testOccurredAtUsesExplicitValue(): void
    {
        $occurred = new \DateTimeImmutable('2026-03-16 10:00:00');

        $event = new RouteEvent(
            routeId: new RouteId('01J0000000000000000000TEST'),
            eventType: RouteEventType::OPTIMIZED,
            actorType: 'system',
            occurredAt: $occurred,
        );

        self::assertSame($occurred, $event->occurredAt());
    }

    public function testReconstituteRestoresAllProperties(): void
    {
        $routeId = new RouteId('01J0000000000000000000TEST');
        $occurred = new \DateTimeImmutable('2026-03-16 10:00:00');
        $created = new \DateTimeImmutable('2026-03-16 10:00:01');

        $event = RouteEvent::reconstitute(
            routeId: $routeId,
            eventType: RouteEventType::ASSIGNED,
            actorType: 'user',
            actorUserId: 5,
            payload: ['driver_id' => 10],
            snapshotMetrics: ['distance_km' => 42.5],
            occurredAt: $occurred,
            createdAt: $created,
        );

        self::assertSame($routeId, $event->routeId());
        self::assertSame(RouteEventType::ASSIGNED, $event->eventType());
        self::assertSame('user', $event->actorType());
        self::assertSame(5, $event->actorUserId());
        self::assertSame(['driver_id' => 10], $event->payload());
        self::assertSame(['distance_km' => 42.5], $event->snapshotMetrics());
        self::assertSame($occurred, $event->occurredAt());
        self::assertSame($created, $event->createdAt());
    }
}
