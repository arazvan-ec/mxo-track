<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use App\Entity\CustomerScopedEntityInterface;
use App\Entity\RealtimeEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RealtimeEvent::class)]
final class RealtimeEventTest extends TestCase
{
    #[Test]
    public function constructorSetsAllFields(): void
    {
        $customer = new Customer('Test Customer');
        $topic = '/vehicles/abc123/position';
        $data = ['lat' => 40.0, 'lng' => -3.7];
        $eventType = 'position_update';

        $event = new RealtimeEvent($customer, $topic, $data, $eventType);

        self::assertSame($customer, $event->getCustomer());
        self::assertSame($topic, $event->getTopic());
        self::assertSame($data, $event->getData());
        self::assertSame($eventType, $event->getEventType());
    }

    #[Test]
    public function implementsCustomerScopedEntityInterface(): void
    {
        $customer = new Customer('Test Customer');
        $event = new RealtimeEvent($customer, '/topic', []);

        self::assertInstanceOf(CustomerScopedEntityInterface::class, $event);
    }

    #[Test]
    public function initializesCreatedAtOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $customer = new Customer('Test Customer');
        $event = new RealtimeEvent($customer, '/topic', ['key' => 'value']);
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $event->getCreatedAt());
        self::assertLessThanOrEqual($after, $event->getCreatedAt());
    }

    #[Test]
    public function eventTypeDefaultsToNull(): void
    {
        $customer = new Customer('Test Customer');
        $event = new RealtimeEvent($customer, '/topic', []);

        self::assertNull($event->getEventType());
    }
}
